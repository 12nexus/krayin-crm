<?php

namespace Nexus\SalesForm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webkul\Lead\Contracts\Lead;

/**
 * Puts sales meetings straight onto the shared Sales Team calendar.
 *
 * One calendar event per lead's current meeting:
 *  - a first booking creates the event, with a Google Meet link, and Google
 *    emails the invite to the client, the rep and the administrators;
 *  - a reschedule (or a rebooking after a no-show) moves that same event, so
 *    guests get an update rather than a second invite;
 *  - a held meeting is left on the calendar as history, so a later follow-up
 *    becomes a new event;
 *  - an invalid lead's upcoming event is cancelled.
 *
 * Google being down, or the calendar not being connected, never blocks a
 * booking: the failure is recorded and the admin email falls back to the
 * "Add to Google Calendar" link.
 */
class MeetingCalendarSync
{
    /**
     * html links of events seen while searching, keyed by event id.
     */
    protected array $eventLinks = [];

    public function __construct(
        protected GoogleCalendar $google,
        protected LeadFields $fields,
        protected ClientInvite $invite,
    ) {}

    /**
     * Create or move the calendar event for the lead's current open meeting.
     *
     * @param  string  $kind  discovery | rescheduled | follow-up
     * @return array{status: string, url: ?string, error: ?string}
     */
    public function syncLatestMeeting(Lead $lead, string $kind = 'discovery'): array
    {
        if (! $this->google->connected()) {
            return ['status' => 'not_connected', 'url' => null, 'error' => null];
        }

        $meeting = $this->openMeeting($lead->id);

        if (! $meeting) {
            return ['status' => 'no_meeting', 'url' => null, 'error' => null];
        }

        try {
            $payload = $this->payload($lead, $meeting, $kind);

            $current = DB::table('nexus_calendar_events')
                ->where('lead_id', $lead->id)
                ->where('status', 'active')
                ->whereNotNull('google_event_id')
                ->orderByDesc('id')
                ->first();

            $eventId = $current->google_event_id ?? $this->adoptExistingEvent($lead, $meeting);

            $event = null;

            if ($eventId) {
                try {
                    // A moved event keeps its Meet link; only an insert asks for one.
                    $event = $this->google->patchEvent($eventId, $payload);
                } catch (\Illuminate\Http\Client\RequestException $e) {
                    if (! in_array($e->response?->status(), [404, 410], true)) {
                        throw $e;
                    }
                    // The event was deleted in Google Calendar: book a new one.
                }
            }

            $event ??= $this->insertWithMeet($payload);

            $this->record($lead->id, $meeting->id, $event, $current->id ?? null);

            return ['status' => 'synced', 'url' => $event['htmlLink'] ?? null, 'error' => null];
        } catch (\Throwable $e) {
            $message = $this->describe($e);

            Log::warning('Google Calendar sync failed', ['lead_id' => $lead->id, 'activity_id' => $meeting->id, 'message' => $message]);

            DB::table('nexus_calendar_events')->insert([
                'lead_id'     => $lead->id,
                'activity_id' => $meeting->id,
                'calendar_id' => $this->google->calendarId() ?? '',
                'status'      => 'failed',
                'last_error'  => Str::limit($message, 1000),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            return ['status' => 'failed', 'url' => null, 'error' => $message];
        }
    }

    /**
     * The meeting took place: keep its event as history.
     */
    public function markHeld(Lead $lead): void
    {
        DB::table('nexus_calendar_events')
            ->where('lead_id', $lead->id)
            ->where('status', 'active')
            ->update(['status' => 'held', 'updated_at' => now()]);
    }

    /**
     * The lead is invalid: cancel its upcoming event, which tells the guests.
     * Past events stay on the calendar as history.
     */
    public function cancel(Lead $lead): void
    {
        if (! $this->google->connected()) {
            return;
        }

        $rows = DB::table('nexus_calendar_events')
            ->leftJoin('activities', 'activities.id', '=', 'nexus_calendar_events.activity_id')
            ->where('nexus_calendar_events.lead_id', $lead->id)
            ->where('nexus_calendar_events.status', 'active')
            ->whereNotNull('nexus_calendar_events.google_event_id')
            ->get(['nexus_calendar_events.id', 'nexus_calendar_events.google_event_id', 'activities.schedule_from']);

        foreach ($rows as $row) {
            if ($row->schedule_from && Carbon::parse($row->schedule_from, 'UTC')->isPast()) {
                DB::table('nexus_calendar_events')->where('id', $row->id)->update(['status' => 'held', 'updated_at' => now()]);

                continue;
            }

            try {
                $this->google->deleteEvent($row->google_event_id);

                DB::table('nexus_calendar_events')->where('id', $row->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('Google Calendar cancel failed', ['lead_id' => $lead->id, 'message' => $this->describe($e)]);

                DB::table('nexus_calendar_events')->where('id', $row->id)->update(['last_error' => Str::limit($this->describe($e), 1000), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Link meetings booked before the sync existed to the events already on
     * the calendar for them, so opening or moving them does not create a
     * duplicate. Only open meetings are considered.
     *
     * @return array{linked: int, missing: array<int, string>}
     */
    public function linkExisting(): array
    {
        $linked = 0;
        $missing = [];

        $meetings = DB::table('activities')
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('activities.type', 'meeting')
            ->where('activities.is_done', 0)
            ->whereNotNull('activities.schedule_from')
            ->whereNotIn('lead_activities.lead_id', DB::table('nexus_calendar_events')->where('status', 'active')->select('lead_id'))
            ->get(['activities.id', 'activities.schedule_from', 'lead_activities.lead_id']);

        foreach ($meetings as $meeting) {
            $lead = app(\Webkul\Lead\Repositories\LeadRepository::class)->find($meeting->lead_id);

            if (! $lead) {
                continue;
            }

            $eventId = $this->findEvent($lead, Carbon::parse($meeting->schedule_from, 'UTC'));

            if (! $eventId) {
                $missing[] = $lead->person?->name ?? $lead->title;

                continue;
            }

            DB::table('nexus_calendar_events')->insert([
                'lead_id'         => $lead->id,
                'activity_id'     => $meeting->id,
                'calendar_id'     => $this->google->calendarId(),
                'google_event_id' => $eventId,
                'html_link'       => $this->eventLinks[$eventId] ?? null,
                'status'          => 'active',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $linked++;
        }

        return ['linked' => $linked, 'missing' => $missing];
    }

    /**
     * The calendar link for a meeting, if it is on the calendar.
     */
    public function linkFor(int $activityId): ?string
    {
        return DB::table('nexus_calendar_events')
            ->where('activity_id', $activityId)
            ->whereIn('status', ['active', 'held'])
            ->whereNotNull('html_link')
            ->orderByDesc('id')
            ->value('html_link');
    }

    /**
     * on_calendar | failed | off: where the lead's current meeting stands.
     */
    public function state(int $leadId): string
    {
        $meeting = $this->openMeeting($leadId);

        if (! $meeting) {
            return 'off';
        }

        $row = DB::table('nexus_calendar_events')
            ->where('lead_id', $leadId)
            ->where('activity_id', $meeting->id)
            ->orderByDesc('id')
            ->first();

        return match ($row->status ?? null) {
            'active' => 'on_calendar',
            'failed' => 'failed',
            default  => 'off',
        };
    }

    /**
     * The calendar link for the lead's current meeting, if it is on the calendar.
     */
    public function currentLink(int $leadId): ?string
    {
        return DB::table('nexus_calendar_events')
            ->where('lead_id', $leadId)
            ->where('status', 'active')
            ->whereNotNull('html_link')
            ->orderByDesc('id')
            ->value('html_link');
    }

    protected function openMeeting(int $leadId): ?object
    {
        return DB::table('activities')
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('lead_activities.lead_id', $leadId)
            ->where('activities.type', 'meeting')
            ->where('activities.is_done', 0)
            ->whereNotNull('activities.schedule_from')
            ->orderByDesc('activities.id')
            ->first(['activities.id', 'activities.schedule_from', 'activities.schedule_to']);
    }

    /**
     * An event created by hand (the old "Add to Google Calendar" button) for
     * this lead's previous meeting, found by time and guest, so a reschedule
     * moves it instead of adding a duplicate.
     */
    protected function adoptExistingEvent(Lead $lead, object $meeting): ?string
    {
        $previous = DB::table('activities')
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('lead_activities.lead_id', $lead->id)
            ->where('activities.type', 'meeting')
            ->where('activities.id', '<', $meeting->id)
            ->whereNotNull('activities.schedule_from')
            ->orderByDesc('activities.id')
            ->value('activities.schedule_from');

        return $previous ? $this->findEvent($lead, Carbon::parse($previous, 'UTC')) : null;
    }

    /**
     * An unlinked calendar event within two hours of the given time whose
     * guests include the client, or whose title names them.
     */
    protected function findEvent(Lead $lead, Carbon $around): ?string
    {
        $emails = collect($lead->person?->emails ?? [])->pluck('value')->filter()->map(fn ($e) => strtolower($e))->all();
        $name = strtolower((string) $lead->person?->name);

        $linked = DB::table('nexus_calendar_events')->whereNotNull('google_event_id')->pluck('google_event_id')->all();

        $events = $this->google->listEvents([
            'timeMin'    => $around->copy()->subHours(2)->toIso8601ZuluString(),
            'timeMax'    => $around->copy()->addHours(2)->toIso8601ZuluString(),
            'maxResults' => 50,
        ]);

        foreach ($events as $event) {
            if (in_array($event['id'], $linked, true) || ($event['status'] ?? '') === 'cancelled') {
                continue;
            }

            $guests = collect($event['attendees'] ?? [])->pluck('email')->map(fn ($e) => strtolower($e))->all();

            $matches = array_intersect($emails, $guests)
                || ($name !== '' && str_contains(strtolower($event['summary'] ?? ''), $name));

            if ($matches) {
                $this->eventLinks[$event['id']] = $event['htmlLink'] ?? null;

                return $event['id'];
            }
        }

        return null;
    }

    protected function payload(Lead $lead, object $meeting, string $kind): array
    {
        $lead->loadMissing(['person', 'user']);

        $fields = $this->fields->get($lead->id);
        $zone = config('sales_form.timezones')[$fields['meeting_timezone'] ?? ''] ?? config('app.timezone', 'UTC');

        $start = Carbon::parse($meeting->schedule_from, 'UTC');
        $end = $meeting->schedule_to
            ? Carbon::parse($meeting->schedule_to, 'UTC')
            : $start->copy()->addMinutes((int) config('sales_form.notify.meeting_minutes'));

        $client = $lead->person?->name ?: 'Client';

        $clientEmails = collect($lead->person?->emails ?? [])->pluck('value')->filter()->take(1)->all();

        $admins = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.permission_type', 'all')
            ->where('users.status', 1)
            ->pluck('users.email')
            ->all();

        $guests = collect(array_merge($clientEmails, [$lead->user?->email], $admins))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn ($email) => strtolower($email))
            ->unique()
            ->values()
            ->map(fn ($email) => ['email' => $email])
            ->all();

        return [
            'summary'     => $kind === 'follow-up'
                ? $client.' | Virtual Assistant Follow-up Call'
                : $client.' | Virtual Assistant Discovery Call',
            'description' => $this->invite->description($client, $lead->user?->name, $lead->user?->email),
            'start'       => ['dateTime' => $start->toIso8601ZuluString(), 'timeZone' => $zone],
            'end'         => ['dateTime' => $end->toIso8601ZuluString(), 'timeZone' => $zone],
            'attendees'   => $guests,
            'guestsCanModify' => false,
            'extendedProperties' => ['private' => [
                'nexus_lead_id'     => (string) $lead->id,
                'nexus_activity_id' => (string) $meeting->id,
            ]],
        ];
    }

    /**
     * Insert with a Google Meet link; if the calendar cannot create Meet links,
     * insert without one rather than fail.
     */
    protected function insertWithMeet(array $payload): array
    {
        try {
            return $this->google->insertEvent($payload + ['conferenceData' => ['createRequest' => [
                'requestId'             => (string) Str::uuid(),
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ]]]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response?->status() !== 400) {
                throw $e;
            }

            return $this->google->insertEvent($payload);
        }
    }

    protected function record(int $leadId, int $activityId, array $event, ?int $currentRowId): void
    {
        $row = [
            'lead_id'         => $leadId,
            'activity_id'     => $activityId,
            'calendar_id'     => $this->google->calendarId(),
            'google_event_id' => $event['id'] ?? null,
            'html_link'       => $event['htmlLink'] ?? null,
            'status'          => 'active',
            'last_error'      => null,
            'updated_at'      => now(),
        ];

        if ($currentRowId) {
            DB::table('nexus_calendar_events')->where('id', $currentRowId)->update($row);
        } else {
            DB::table('nexus_calendar_events')->insert($row + ['created_at' => now()]);
        }
    }

    protected function describe(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
            $message = $e->response->json('error.message') ?? $e->response->json('error_description') ?? $e->response->json('error');

            return 'Google responded '.$e->response->status().($message ? ': '.(is_string($message) ? $message : json_encode($message)) : '');
        }

        return $e->getMessage();
    }
}
