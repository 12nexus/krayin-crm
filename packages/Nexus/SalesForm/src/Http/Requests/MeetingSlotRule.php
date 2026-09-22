<?php

namespace Nexus\SalesForm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Nexus\SalesForm\Services\MeetingSlots;

/**
 * Rejects a meeting time that overlaps another open sales meeting, on submit,
 * before anything is written. The booking re-checks under a lock as well, which
 * is what actually closes the race; this is what gives the rep a clear message
 * next to the time field.
 */
class MeetingSlotRule
{
    public static function check(FormRequest $request, Validator $validator): void
    {
        if ($validator->errors()->hasAny(['meeting_date', 'meeting_time', 'timezone'])) {
            return;
        }

        $slots = app(MeetingSlots::class);

        $window = $slots->window(
            $request->input('meeting_date'),
            $request->input('meeting_time'),
            $request->input('timezone')
        );

        if (! $window) {
            return;
        }

        /**
         * On an existing lead, its own open meeting is the one being replaced,
         * so it never blocks the new time.
         */
        $leadId = (int) $request->route('id');

        $ignore = $leadId ? $slots->openMeetingIds($leadId) : [];

        try {
            $slots->assertFree(
                $window[0],
                $window[1],
                config('sales_form.timezones')[$request->input('timezone')] ?? null,
                $ignore
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        }
    }
}
