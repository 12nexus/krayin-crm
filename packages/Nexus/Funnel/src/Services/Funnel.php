<?php

namespace Nexus\Funnel\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Nexus\SalesForm\Services\LeadBuilder;
use Nexus\SalesForm\Services\LeadFields;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Contracts\Lead;
use Webkul\User\Models\User;

/**
 * The moves a lead makes through the sales funnel, each recorded on the lead as
 * a note so its history reads as what happened and who did it.
 */
class Funnel
{
    public function __construct(
        protected LeadFields $fields,
        protected LeadBuilder $leadBuilder,
        protected ActivityRepository $activityRepository,
    ) {}

    public function isArchived(Lead $lead): bool
    {
        return $lead->pipeline?->name === config('funnel.archive_pipeline');
    }

    public function stageCode(Lead $lead): ?string
    {
        return $lead->stage?->code;
    }

    public function markValid(Lead $lead, User $by): void
    {
        $this->fields->set($lead->id, ['lead_validity' => config('funnel.validity.valid')]);

        $this->note($lead, $by, "Marked as a valid lead by {$by->name}.");
    }

    /**
     * Archive the lead: off the board and out of the funnel, but on record and
     * restorable. The stage it left is kept in the note.
     */
    public function markInvalid(Lead $lead, User $by, ?string $reason): void
    {
        $archive = DB::table('lead_pipelines')->where('name', config('funnel.archive_pipeline'))->first();

        $stageId = $archive
            ? DB::table('lead_pipeline_stages')
                ->where('lead_pipeline_id', $archive->id)
                ->where('code', config('funnel.archive_stage'))
                ->value('id')
            : null;

        if (! $stageId) {
            throw new \RuntimeException('The archive pipeline is missing; run the migrations.');
        }

        $from = $lead->stage?->name ?? 'unknown stage';

        $lead->fill([
            'lead_pipeline_id'       => $archive->id,
            'lead_pipeline_stage_id' => $stageId,
            'closed_at'              => now(),
        ])->save();

        $this->fields->set($lead->id, ['lead_validity' => config('funnel.validity.invalid')]);

        $this->closeOpenMeetings($lead, 'cancelled, lead invalid');

        $reason = trim((string) $reason);

        $this->note($lead, $by, "Marked INVALID and archived by {$by->name} (was in {$from}).".
            ($reason !== '' ? "\nReason: {$reason}" : ''));
    }

    /**
     * Bring an archived lead back into the funnel at New Lead. Archiving
     * cancelled its meetings, so the rep books a fresh one from there.
     */
    public function restore(Lead $lead, User $by): void
    {
        $pipeline = DB::table('lead_pipelines')->where('name', config('funnel.pipeline'))->first();

        if (! $pipeline) {
            throw new \RuntimeException('The sales pipeline is missing.');
        }

        $stage = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipeline->id)
            ->where('code', 'new')
            ->first();

        $lead->fill([
            'lead_pipeline_id'       => $pipeline->id,
            'lead_pipeline_stage_id' => $stage->id,
            'closed_at'              => null,
        ])->save();

        $this->fields->set($lead->id, ['lead_validity' => config('funnel.validity.pending')]);

        $this->note($lead, $by, "Restored from the archive by {$by->name}, back to {$stage->name}.");
    }

    public function meetingHeld(Lead $lead, User $by): void
    {
        $this->fields->set($lead->id, ['meeting_appeared' => config('funnel.appeared.attended')]);

        $this->closeOpenMeetings($lead);

        $this->leadBuilder->moveToStage($lead, 'follow-up');

        $this->note($lead, $by, "Meeting held: the client attended ({$by->name}). Moved to Follow Up.");
    }

    public function noShow(Lead $lead, User $by): void
    {
        $this->fields->set($lead->id, ['meeting_appeared' => config('funnel.appeared.no_show')]);

        $this->closeOpenMeetings($lead, 'no-show');

        $this->leadBuilder->moveToStage($lead, 'no-show');

        $this->note($lead, $by, "The client did not show up for the meeting ({$by->name}). Moved to No Show; reschedule from the lead.");
    }

    /**
     * The lead's most recent meeting: the one booked last, which after any
     * reschedule is the one that counts.
     *
     * @return array{id: int, from: Carbon, local: Carbon, zone: string, label: string, done: bool, upcoming: bool}|null
     */
    public function currentMeeting(Lead $lead, ?string $timezoneLabel = null): ?array
    {
        $meeting = DB::table('activities')
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('lead_activities.lead_id', $lead->id)
            ->where('activities.type', 'meeting')
            ->whereNotNull('activities.schedule_from')
            ->orderByDesc('activities.id')
            ->first(['activities.id', 'activities.schedule_from', 'activities.is_done']);

        if (! $meeting) {
            return null;
        }

        $timezoneLabel ??= $this->fields->get($lead->id)['meeting_timezone'] ?? null;

        return $this->present($meeting, $timezoneLabel);
    }

    /**
     * A meeting row shown in the agent's own zone when the lead records one.
     */
    public function present(object $meeting, ?string $timezoneLabel): array
    {
        $zone = config('sales_form.timezones')[$timezoneLabel] ?? config('app.timezone', 'UTC');

        $from = Carbon::parse($meeting->schedule_from, 'UTC');
        $local = $from->copy()->setTimezone($zone);

        return [
            'id'       => $meeting->id,
            'from'     => $from,
            'local'    => $local,
            'zone'     => $zone,
            'label'    => $local->format('D j M Y · g:i A T'),
            'done'     => (bool) $meeting->is_done,
            'upcoming' => ! $meeting->is_done && $from->isFuture(),
        ];
    }

    /**
     * Close off the lead's open meetings, tagging each with what became of it.
     */
    public function closeOpenMeetings(Lead $lead, ?string $outcome = null): void
    {
        $open = DB::table('activities')
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('lead_activities.lead_id', $lead->id)
            ->where('activities.type', 'meeting')
            ->where('activities.is_done', 0)
            ->get(['activities.id', 'activities.title']);

        foreach ($open as $meeting) {
            DB::table('activities')->where('id', $meeting->id)->update([
                'is_done'    => 1,
                'title'      => $outcome ? $meeting->title.' ('.$outcome.')' : $meeting->title,
                'updated_at' => now(),
            ]);
        }
    }

    protected function note(Lead $lead, User $by, string $comment): void
    {
        $note = $this->activityRepository->create([
            'type'    => 'note',
            'comment' => $comment,
            'user_id' => $by->id,
            'is_done' => 1,
        ]);

        DB::table('lead_activities')->insert(['lead_id' => $lead->id, 'activity_id' => $note->id]);
    }
}
