<?php

namespace Nexus\SalesForm\Services;

use Carbon\Carbon;

/**
 * Builds a Google Calendar "create event" link that arrives fully populated, so
 * the recipient only has to pick which calendar it lands in.
 */
class CalendarLink
{
    const BASE = 'https://calendar.google.com/calendar/render';

    /**
     * @param  Carbon  $startLocal  Wall-clock start time as the agent experiences it
     * @param  string  $timezone    IANA zone that $startLocal is expressed in
     * @param  array   $guests      Email addresses to invite
     * @param  ?string $calendarId  Target calendar, so the recipient is not asked to pick one
     */
    public function build(
        string $title,
        Carbon $startLocal,
        string $timezone,
        int $durationMinutes,
        array $guests = [],
        string $details = '',
        string $location = '',
        ?string $calendarId = null
    ): string {
        $end = $startLocal->copy()->addMinutes($durationMinutes);

        /**
         * Local wall-clock times plus `ctz` rather than UTC: Google then resolves
         * the instant itself, and the event reads correctly for the agent's region
         * no matter what timezone the person clicking the link is in.
         */
        $params = [
            'action' => 'TEMPLATE',
            'text'   => $title,
            'dates'  => $startLocal->format('Ymd\THis').'/'.$end->format('Ymd\THis'),
            'ctz'    => $timezone,
        ];

        if ($details !== '') {
            $params['details'] = $details;
        }

        if ($location !== '') {
            $params['location'] = $location;
        }

        $guests = array_values(array_filter(array_unique(array_map(
            fn ($email) => strtolower(trim((string) $email)),
            $guests
        )), fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));

        if ($guests) {
            $params['add'] = implode(',', $guests);
        }

        /**
         * Opens the composer with this calendar already selected, so the recipient
         * only has to save. Google ignores `src` and falls back to the primary
         * calendar if the signed-in account cannot write to it, so a stale or
         * mistyped id degrades to today's behaviour rather than failing.
         */
        $calendarId = $calendarId ?: config('sales_form.notify.calendar_id');

        if ($calendarId) {
            $params['src'] = $calendarId;
        }

        return self::BASE.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
