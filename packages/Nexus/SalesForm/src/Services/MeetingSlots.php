<?php

namespace Nexus\SalesForm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One sales meeting at a time, across the whole team, so the administrator who
 * sits in on every discovery call is never double-booked.
 *
 * A slot is taken by any open (not done) meeting whose window overlaps the new
 * one. Meetings a reschedule supersedes are closed first, so they never block
 * the time they are being moved away from.
 */
class MeetingSlots
{
    /**
     * Name of the MySQL advisory lock that serialises bookings, so two reps
     * submitting the same slot in the same second cannot both get it.
     */
    const LOCK = 'nexus_sales_meeting_booking';

    /**
     * The meeting occupying any part of [start, end), if there is one.
     */
    public function clash(Carbon $startUtc, Carbon $endUtc, array $ignoreActivityIds = []): ?object
    {
        return DB::table('activities')
            ->leftJoin('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->leftJoin('leads', 'leads.id', '=', 'lead_activities.lead_id')
            ->leftJoin('users', 'users.id', '=', DB::raw('COALESCE(leads.user_id, activities.user_id)'))
            ->where('activities.type', 'meeting')
            ->where('activities.is_done', 0)
            ->where('activities.schedule_from', '<', $endUtc->format('Y-m-d H:i:s'))
            ->where('activities.schedule_to', '>', $startUtc->format('Y-m-d H:i:s'))
            ->when($ignoreActivityIds, fn ($query) => $query->whereNotIn('activities.id', $ignoreActivityIds))
            ->orderBy('activities.schedule_from')
            ->first([
                'activities.id',
                'activities.schedule_from',
                'activities.schedule_to',
                'users.name as owner',
            ]);
    }

    /**
     * Fail validation, on the time field, when the slot is taken. The clash is
     * shown in the zone the rep is booking in, so it reads the way they think.
     *
     * @throws ValidationException
     */
    public function assertFree(
        Carbon $startUtc,
        Carbon $endUtc,
        ?string $timezone = null,
        array $ignoreActivityIds = [],
        string $field = 'meeting_time'
    ): void {
        $clash = $this->clash($startUtc, $endUtc, $ignoreActivityIds);

        if (! $clash) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $this->message($clash, $timezone ?: config('app.timezone', 'UTC')),
        ]);
    }

    /**
     * Run a booking with the slot check and the insert under one lock.
     */
    public function locked(callable $callback): mixed
    {
        if (DB::getDriverName() !== 'mysql') {
            return $callback();
        }

        DB::select('SELECT GET_LOCK(?, 10) AS acquired', [self::LOCK]);

        try {
            return $callback();
        } finally {
            DB::select('SELECT RELEASE_LOCK(?) AS released', [self::LOCK]);
        }
    }

    /**
     * Ids of the open meetings on a lead: the ones a reschedule will replace.
     */
    public function openMeetingIds(int $leadId): array
    {
        return DB::table('activities')
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('lead_activities.lead_id', $leadId)
            ->where('activities.type', 'meeting')
            ->where('activities.is_done', 0)
            ->pluck('activities.id')
            ->all();
    }

    /**
     * The UTC window for a meeting entered as the agent's wall-clock time and
     * one of the form's timezone labels.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function window(?string $date, ?string $time, ?string $timezoneLabel): ?array
    {
        $zone = config('sales_form.timezones')[$timezoneLabel] ?? null;

        if (! $date || ! $time || ! $zone) {
            return null;
        }

        try {
            $start = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time, $zone)->setTimezone('UTC');
        } catch (\Throwable) {
            return null;
        }

        return [$start, $start->copy()->addMinutes((int) config('sales_form.notify.meeting_minutes'))];
    }

    protected function message(object $clash, string $timezone): string
    {
        $from = Carbon::parse($clash->schedule_from, 'UTC')->setTimezone($timezone);
        $to = Carbon::parse($clash->schedule_to, 'UTC')->setTimezone($timezone);

        return sprintf(
            'Another sales meeting is already booked on %s from %s to %s %s%s. Pick a different time.',
            $from->format('D j M'),
            $from->format('g:i A'),
            $to->format('g:i A'),
            $from->format('T'),
            $clash->owner ? ' (booked by '.$clash->owner.')' : ''
        );
    }
}
