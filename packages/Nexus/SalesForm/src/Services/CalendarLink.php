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
     */
    public function build(
        string $title,
        Carbon $startLocal,
        string $timezone,
        int $durationMinutes,
        array $guests = [],
        string $details = '',
        string $location = ''
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

        return self::BASE.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
