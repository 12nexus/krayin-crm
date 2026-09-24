<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ensures the CRM shape the sales form writes into exists: the custom lead
 * attributes for every question on the form, the VA sales pipeline, and the two
 * lead sources.
 *
 * Written to be idempotent — the production database was provisioned by hand
 * before this package existed, so every insert is guarded by an existence check
 * and re-running changes nothing.
 */
return new class extends Migration
{
    /**
     * code => [name, type, sort_order, options[]]
     */
    protected array $attributes = [
        'brokerage'           => ['Brokerage', 'text', 30, []],
        'agent_city'          => ['Agent City', 'text', 31, []],
        'agent_state'         => ['Agent State / Province', 'text', 32, []],
        'agent_country'       => ['Agent Country', 'text', 33, []],
        'experience_years'    => ['Experience in Real Estate', 'text', 34, []],
        'license_details'     => ['License / Specialties', 'textarea', 35, []],
        'using_assistant'     => ['Currently Using an Assistant?', 'select', 36, ['No', 'Yes']],
        'assistant_type'      => ['Existing Assistant Type', 'select', 37, ['None', 'Remote VA', 'In-house Assistant', 'Other']],
        'willingness_to_hire' => ['Willingness to Hire a VA', 'select', 38, ['1 - Low', '2 - Medium', '3 - High']],
        'meeting_at'          => ['Meeting Date & Time (agent local)', 'datetime', 39, []],
        'meeting_timezone'    => ['Meeting Timezone', 'select', 40, [
            'EST (Eastern Standard Time)', 'MT (Mountain Time)',
            'PST (Pacific Standard Time)', 'CST (Central Standard Time)',
        ]],
        'meeting_appeared'    => ['Meeting Appeared?', 'select', 41, ['Pending', 'Yes - Attended', 'No - No Show', 'Rescheduled']],
        'appointment_setter'  => ['Appointment Set By', 'text', 42, []],
        'form_submitted_at'   => ['Form Submitted At', 'datetime', 43, []],
        'vicidial_lead_code'  => ['ViciDial Vendor Lead Code', 'text', 44, []],
        'source_notes'        => ['Discovery Notes', 'textarea', 45, []],
    ];

    protected array $stages = [
        ['code' => 'new',               'name' => 'New Lead',          'sort_order' => 1, 'probability' => 10],
        ['code' => 'meeting-scheduled', 'name' => 'Meeting Scheduled', 'sort_order' => 2, 'probability' => 30],
        ['code' => 'meeting-completed', 'name' => 'Meeting Completed', 'sort_order' => 3, 'probability' => 50],
        ['code' => 'proposal-sent',     'name' => 'Proposal Sent',     'sort_order' => 4, 'probability' => 70],
        ['code' => 'follow-up',         'name' => 'Follow Up',         'sort_order' => 5, 'probability' => 40],
        ['code' => 'won',               'name' => 'Won',               'sort_order' => 6, 'probability' => 100],
        ['code' => 'lost',              'name' => 'Lost',              'sort_order' => 7, 'probability' => 0],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->attributes as $code => [$name, $type, $sortOrder, $options]) {
            $attribute = DB::table('attributes')
                ->where('entity_type', 'leads')
                ->where('code', $code)
                ->first();

            if (! $attribute) {
                $attributeId = DB::table('attributes')->insertGetId([
                    'code'            => $code,
                    'name'            => $name,
                    'type'            => $type,
                    'entity_type'     => 'leads',
                    'sort_order'      => $sortOrder,
                    'is_required'     => 0,
                    'is_unique'       => 0,
                    'quick_add'       => 0,
                    'is_user_defined' => 1,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            } else {
                $attributeId = $attribute->id;
            }

            $sort = 1;

            foreach ($options as $option) {
                $exists = DB::table('attribute_options')
                    ->where('attribute_id', $attributeId)
                    ->where('name', $option)
                    ->exists();

                if (! $exists) {
                    DB::table('attribute_options')->insert([
                        'attribute_id' => $attributeId,
                        'name'         => $option,
                        'sort_order'   => $sort,
                    ]);
                }

                $sort++;
            }
        }

        /**
         * Pipeline. Only created when absent; an existing one keeps its stages so
         * a re-run never disturbs live leads.
         */
        $pipeline = DB::table('lead_pipelines')->where('name', '12Nexus VA Sales')->first();

        if (! $pipeline) {
            DB::table('lead_pipelines')->where('is_default', 1)->update(['is_default' => 0]);

            $pipelineId = DB::table('lead_pipelines')->insertGetId([
                'name'        => '12Nexus VA Sales',
                'rotten_days' => 14,
                'is_default'  => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            foreach ($this->stages as $stage) {
                // lead_pipeline_stages has no timestamp columns.
                DB::table('lead_pipeline_stages')->insert(array_merge($stage, [
                    'lead_pipeline_id' => $pipelineId,
                ]));
            }
        }

        foreach (['ViciDial Outbound', 'Meeting Scheduling Form', 'Referral'] as $source) {
            if (! DB::table('lead_sources')->where('name', $source)->exists()) {
                DB::table('lead_sources')->insert([
                    'name'       => $source,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Deliberately not reversible: these rows carry live lead data by the time
     * anyone would roll back, and dropping them would take the leads with them.
     */
    public function down(): void {}
};
