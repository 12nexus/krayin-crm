<?php

namespace Nexus\SalesForm\Services;

use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

/**
 * The shared sales calendar's day plan, read from its private iCal feed, so a
 * rep booking a meeting can see what is already on the calendar that day.
 *
 * The feed URL is a secret (anyone holding it can read the calendar), so it
 * only ever lives in .env and never reaches the browser. The feed is cached
 * briefly; if Google is unreachable the last good copy is served instead.
 */
class CalendarFeed
{
    const CACHE_FRESH = 'nexus.sales_calendar.ics';

    const CACHE_STALE = 'nexus.sales_calendar.ics.stale';

    public function configured(): bool
    {
        return (bool) config('sales_form.calendar_feed.url');
    }

    /**
     * Events overlapping the given calendar day in the given zone, with times
     * expressed in that zone as labels and minutes from its midnight, so the
     * browser can compare them with a proposed meeting without any timezone
     * arithmetic of its own.
     *
     * @return array{events: array, stale: bool}
     */
    public function day(Carbon $dayStart, bool $withTitles): array
    {
        $dayEnd = $dayStart->copy()->addDay();
        $zone = $dayStart->getTimezone();

        [$ics, $stale] = $this->ics();

        $calendar = Reader::read($ics, Reader::OPTION_FORGIVING);

        if (! $calendar instanceof VCalendar) {
            return ['events' => [], 'stale' => $stale];
        }

        // Expands recurring events into the window and converts every time
        // to UTC, whatever TZID the feed used.
        $expanded = $calendar->expand(
            $dayStart->copy()->utc()->toDateTimeImmutable(),
            $dayEnd->copy()->utc()->toDateTimeImmutable()
        );

        $events = [];

        foreach ($expanded->select('VEVENT') as $event) {
            if (strtoupper((string) $event->STATUS) === 'CANCELLED') {
                continue;
            }

            $allDay = ! $event->DTSTART->hasTime();

            $start = Carbon::instance($event->DTSTART->getDateTime())->setTimezone($zone);

            if (isset($event->DTEND)) {
                $end = Carbon::instance($event->DTEND->getDateTime())->setTimezone($zone);
            } elseif (isset($event->DURATION)) {
                $end = $start->copy()->add($event->DURATION->getDateInterval());
            } else {
                $end = $allDay ? $start->copy()->addDay() : $start->copy();
            }

            if ($end <= $dayStart || $start >= $dayEnd) {
                continue;
            }

            $events[] = [
                'title'     => $withTitles ? trim((string) $event->SUMMARY) ?: 'Busy' : 'Busy',
                'all_day'   => $allDay,
                'busy'      => strtoupper((string) $event->TRANSP) !== 'TRANSPARENT',
                'start'     => $start->format('g:i A'),
                'end'       => $end->format('g:i A'),
                // Clipped to this day, so an event from the night before or
                // running past midnight still lines up on the day's scale.
                'start_min' => max(0, (int) $dayStart->diffInMinutes($start, false)),
                'end_min'   => min(1440, (int) $dayStart->diffInMinutes($end, false)),
            ];
        }

        usort($events, fn ($a, $b) => [$b['all_day'], $a['start_min']] <=> [$a['all_day'], $b['start_min']]);

        return ['events' => $events, 'stale' => $stale];
    }

    /**
     * The raw feed: fresh from cache, else from Google, else the last good copy.
     *
     * @return array{0: string, 1: bool} the ICS text and whether it is stale
     */
    protected function ics(): array
    {
        if ($fresh = Cache::get(self::CACHE_FRESH)) {
            return [$fresh, false];
        }

        try {
            $body = Http::timeout(8)
                ->retry(1, 300)
                ->get(config('sales_form.calendar_feed.url'))
                ->throw()
                ->body();

            if (! str_contains($body, 'BEGIN:VCALENDAR')) {
                throw new \RuntimeException('The calendar feed did not return iCal data.');
            }

            Cache::put(self::CACHE_FRESH, $body, (int) config('sales_form.calendar_feed.cache_seconds'));
            Cache::put(self::CACHE_STALE, $body, now()->addDays(2));

            return [$body, false];
        } catch (\Throwable $e) {
            // Never log the URL itself: it is the calendar's password.
            Log::warning('Sales calendar feed unavailable', ['message' => $e->getMessage()]);

            if ($stale = Cache::get(self::CACHE_STALE)) {
                return [$stale, true];
            }

            throw $e;
        }
    }

    /**
     * The zone to show a day in: the client's timezone once the rep has picked
     * one, else the rep's own browser zone, else the app's.
     */
    public static function zone(?string $label, ?string $browserZone): DateTimeZone
    {
        if ($label && ($iana = config('sales_form.timezones')[$label] ?? null)) {
            return new DateTimeZone($iana);
        }

        if ($browserZone && in_array($browserZone, DateTimeZone::listIdentifiers(), true)) {
            return new DateTimeZone($browserZone);
        }

        return new DateTimeZone(config('app.timezone', 'UTC'));
    }
}
