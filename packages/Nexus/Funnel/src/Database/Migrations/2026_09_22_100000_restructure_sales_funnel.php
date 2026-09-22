<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reshapes the VA sales pipeline into the funnel the sales team works to:
 *
 *   New Lead → Meeting Scheduled → No Show → Follow Up → Won / Lost
 *
 * - Adds "No Show", and removes "Meeting Completed" and "Proposal Sent". Leads
 *   in either move to Follow Up first; a lead in Meeting Completed had its
 *   meeting, so it is recorded as attended.
 * - Adds an "Archived Leads" pipeline with a single "Invalid" stage, where
 *   invalid leads go: off the board, still on record, restorable.
 * - Adds the `engagement_type` (Part-time / Full-time) and `lead_validity`
 *   custom lead fields.
 * - Limits every Sales Executive to their own leads.
 * - Raises estimated values below the $600 monthly retainer to the retainer.
 *
 * Idempotent: every step checks before it writes, so a re-run changes nothing.
 */
return new class extends Migration
{
    protected array $order = [
        'new'               => ['New Lead', 1, 10],
        'meeting-scheduled' => ['Meeting Scheduled', 2, 30],
        'no-show'           => ['No Show', 3, 20],
        'follow-up'         => ['Follow Up', 4, 50],
        'won'               => ['Won', 5, 100],
        'lost'              => ['Lost', 6, 0],
    ];

    protected array $attributes = [
        'engagement_type' => ['Part-time / Full-time', 46, ['Part-time', 'Full-time']],
        'lead_validity'   => ['Lead Validity', 47, ['Not reviewed', 'Valid', 'Invalid']],
    ];

    public function up(): void
    {
        $now = now();

        $this->addAttributes($now);

        $pipelineId = DB::table('lead_pipelines')->where('name', '12Nexus VA Sales')->value('id');

        if ($pipelineId) {
            $this->reshapePipeline($pipelineId, $now);
        }

        $this->createArchive($now);

        $roleId = DB::table('roles')->where('name', 'Sales Executive')->value('id');

        if ($roleId) {
            DB::table('users')->where('role_id', $roleId)->update(['view_permission' => 'individual']);
        }

        DB::table('leads')
            ->where(fn ($query) => $query->whereNull('lead_value')->orWhere('lead_value', '<', 600))
            ->update(['lead_value' => 600]);
    }

    protected function reshapePipeline(int $pipelineId, $now): void
    {
        $stageId = fn (string $code) => DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('code', $code)
            ->value('id');

        foreach ($this->order as $code => [$name, $sortOrder, $probability]) {
            if (! $stageId($code)) {
                DB::table('lead_pipeline_stages')->insert([
                    'code'             => $code,
                    'name'             => $name,
                    'sort_order'       => $sortOrder,
                    'probability'      => $probability,
                    'lead_pipeline_id' => $pipelineId,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }

        $followUp = $stageId('follow-up');

        foreach (['meeting-completed', 'proposal-sent'] as $retired) {
            $retiredId = $stageId($retired);

            if (! $retiredId) {
                continue;
            }

            $leadIds = DB::table('leads')->where('lead_pipeline_stage_id', $retiredId)->pluck('id');

            if ($retired === 'meeting-completed') {
                foreach ($leadIds as $leadId) {
                    $this->recordAttended($leadId);
                }
            }

            DB::table('leads')
                ->where('lead_pipeline_stage_id', $retiredId)
                ->update(['lead_pipeline_stage_id' => $followUp, 'updated_at' => $now]);

            DB::table('lead_pipeline_stages')->where('id', $retiredId)->delete();
        }

        foreach ($this->order as $code => [$name, $sortOrder, $probability]) {
            DB::table('lead_pipeline_stages')
                ->where('lead_pipeline_id', $pipelineId)
                ->where('code', $code)
                ->update(['sort_order' => $sortOrder, 'updated_at' => $now]);
        }
    }

    /**
     * A lead that reached Meeting Completed had its meeting, unless someone
     * already recorded otherwise.
     */
    protected function recordAttended(int $leadId): void
    {
        $attributeId = DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'meeting_appeared')
            ->value('id');

        if (! $attributeId) {
            return;
        }

        $options = DB::table('attribute_options')->where('attribute_id', $attributeId)->pluck('id', 'name');

        $attended = $options['Yes - Attended'] ?? null;

        if (! $attended) {
            return;
        }

        $current = DB::table('attribute_values')
            ->where('entity_type', 'leads')
            ->where('entity_id', $leadId)
            ->where('attribute_id', $attributeId)
            ->first();

        if (! $current) {
            DB::table('attribute_values')->insert([
                'entity_type'   => 'leads',
                'entity_id'     => $leadId,
                'attribute_id'  => $attributeId,
                'integer_value' => $attended,
                'unique_id'     => $leadId.'|'.$attributeId,
            ]);

            return;
        }

        if (in_array($current->integer_value, [null, $options['Pending'] ?? null])) {
            DB::table('attribute_values')->where('id', $current->id)->update(['integer_value' => $attended]);
        }
    }

    protected function createArchive($now): void
    {
        if (DB::table('lead_pipelines')->where('name', 'Archived Leads')->exists()) {
            return;
        }

        $archiveId = DB::table('lead_pipelines')->insertGetId([
            'name'        => 'Archived Leads',
            // An archived lead is never "rotting".
            'rotten_days' => 36500,
            'is_default'  => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        DB::table('lead_pipeline_stages')->insert([
            'code'             => 'invalid',
            'name'             => 'Invalid',
            'sort_order'       => 1,
            'probability'      => 0,
            'lead_pipeline_id' => $archiveId,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
    }

    protected function addAttributes($now): void
    {
        foreach ($this->attributes as $code => [$name, $sortOrder, $options]) {
            $attributeId = DB::table('attributes')
                ->where('entity_type', 'leads')
                ->where('code', $code)
                ->value('id');

            if (! $attributeId) {
                $attributeId = DB::table('attributes')->insertGetId([
                    'code'            => $code,
                    'name'            => $name,
                    'type'            => 'select',
                    'entity_type'     => 'leads',
                    'sort_order'      => $sortOrder,
                    'is_required'     => 0,
                    'is_unique'       => 0,
                    'quick_add'       => 0,
                    'is_user_defined' => 1,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }

            foreach ($options as $index => $option) {
                $exists = DB::table('attribute_options')
                    ->where('attribute_id', $attributeId)
                    ->where('name', $option)
                    ->exists();

                if (! $exists) {
                    DB::table('attribute_options')->insert([
                        'attribute_id' => $attributeId,
                        'name'         => $option,
                        'sort_order'   => $index + 1,
                    ]);
                }
            }
        }
    }

    /**
     * Not reversible: by the time anyone rolls back, leads will have moved
     * through the new stages and the archive, and undoing it would lose that.
     */
    public function down(): void {}
};
