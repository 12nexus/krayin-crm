<?php

namespace Nexus\Funnel\Listeners;

use Illuminate\Support\Facades\Log;
use Nexus\Funnel\Services\Funnel;
use Nexus\SalesForm\Services\LeadFields;

/**
 * Keeps "Meeting Appeared?" in step when a lead is dragged across the board or
 * moved on the stage bar, rather than through the funnel buttons: dropping it
 * in No Show records a no-show, and moving a pending meeting on to Follow Up
 * records that it took place.
 */
class StageSync
{
    public function __construct(
        protected Funnel $funnel,
        protected LeadFields $fields,
    ) {}

    public function handle($lead): void
    {
        try {
            $lead->loadMissing('stage');

            $code = $lead->stage?->code;

            $appeared = $this->fields->get($lead->id)['meeting_appeared'] ?? null;

            if ($code === 'no-show' && $appeared !== config('funnel.appeared.no_show')) {
                $this->fields->set($lead->id, ['meeting_appeared' => config('funnel.appeared.no_show')]);

                $this->funnel->closeOpenMeetings($lead, 'no-show');
            }

            if ($code === 'follow-up' && in_array($appeared, [null, config('funnel.appeared.pending')], true)) {
                $this->fields->set($lead->id, ['meeting_appeared' => config('funnel.appeared.attended')]);

                $this->funnel->closeOpenMeetings($lead);

                app(\Nexus\SalesForm\Services\MeetingCalendarSync::class)->markHeld($lead);
            }
        } catch (\Throwable $e) {
            Log::warning('Funnel stage sync failed', ['lead_id' => $lead->id ?? null, 'message' => $e->getMessage()]);
        }
    }
}
