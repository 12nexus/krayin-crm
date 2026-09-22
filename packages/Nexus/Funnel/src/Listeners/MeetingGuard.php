<?php

namespace Nexus\Funnel\Listeners;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Nexus\SalesForm\Services\MeetingSlots;

/**
 * Meetings are booked through the sales form's meeting fields, which take the
 * agent's timezone and check for clashes. Krayin's own activity endpoints would
 * bypass both, so:
 *
 *  - creating a meeting through them is refused with a pointer to the lead page;
 *  - moving an existing meeting's time through them is clash-checked.
 *
 * The UI already routes people to the right place; this is the backstop.
 */
class MeetingGuard
{
    public function __construct(protected MeetingSlots $slots) {}

    public function creating(): void
    {
        if (request('type') !== 'meeting') {
            return;
        }

        throw ValidationException::withMessages([
            'type' => trans('funnel::app.errors.meeting-from-lead'),
        ]);
    }

    public function updating($id): void
    {
        if (! request()->filled('schedule_from') || ! request()->filled('schedule_to')) {
            return;
        }

        $type = DB::table('activities')->where('id', $id)->value('type');

        if ($type !== 'meeting') {
            return;
        }

        $timezone = config('app.timezone', 'UTC');

        try {
            $from = Carbon::parse(request('schedule_from'), $timezone)->setTimezone('UTC');
            $to = Carbon::parse(request('schedule_to'), $timezone)->setTimezone('UTC');
        } catch (\Throwable) {
            return;
        }

        $this->slots->assertFree($from, $to, $timezone, [(int) $id], 'schedule_from');
    }
}
