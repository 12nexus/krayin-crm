<?php

namespace Nexus\SalesForm\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Repositories\ActivityRepository;

/**
 * Copies what Google Meet leaves on a sales meeting's calendar event (the
 * "Notes by Gemini" doc, the recording, the transcript) onto the lead, as a
 * note with the links.
 *
 * Each meeting activity is matched to its event in the sales calendar:
 *
 *  1. the event already recorded for it (a meeting moved through the CRM);
 *  2. an event starting at the same minute whose title names the client;
 *  3. otherwise the nearest unclaimed event naming the client within
 *     NEAR_DAYS, for a meeting moved in Google Calendar by hand.
 *
 * Steps 1 and 2 run for every meeting before step 3, so when a meeting was
 * rescheduled the old way (the old activity closed, a new one booked) the
 * event, which moved to the new time, goes to the new meeting, and the old one
 * is reported as superseded. Events with files that match no meeting are then
 * matched to a lead by the client name in their title, when exactly one lead
 * has that person.
 */
class MeetingNotes
{
    const NEAR_DAYS = 14;

    public function __construct(
        protected GoogleCalendar $calendar,
        protected ActivityRepository $activityRepository,
    ) {}

    /**
     * Sync the meetings that started in [from, to) and are over.
     *
     * @return array{rows: array<int, array>, orphans: array<int, array>}
     */
    public function sync(Carbon $from, Carbon $to, array $leadIds = [], bool $dryRun = false): array
    {
        if (! $this->calendar->connected()) {
            throw new \RuntimeException('Google Calendar is not connected (Settings → Google Calendar).');
        }

        $to = $to->copy()->min(now());

        $meetings = $this->meetings($from, $to, $leadIds);

        $events = collect($this->calendar->events(
            $from->copy()->subDays(self::NEAR_DAYS),
            $to->copy()->addDays(self::NEAR_DAYS)->min(now()->addDay())
        ))
            ->filter(fn ($event) => ($event['status'] ?? '') !== 'cancelled' && ! empty($event['start']['dateTime']))
            ->keyBy('id');

        $claimed = [];
        $matches = [];

        // 1 and 2: the recorded event, else one at the same minute naming the client.
        foreach ($meetings as $meeting) {
            $event = null;
            $how = null;

            if ($meeting->google_event_id) {
                $event = $events[$meeting->google_event_id] ?? $this->calendar->event($meeting->google_event_id);

                if ($event && ($event['status'] ?? '') === 'cancelled') {
                    $event = null;
                }

                $how = $event ? 'recorded event' : null;
            }

            if (! $event) {
                $event = $this->sameMinute($events, $meeting);
                $how = $event ? 'same time' : null;
            }

            if ($event && ! isset($claimed[$event['id']])) {
                $claimed[$event['id']] = $meeting->id;
                $matches[$meeting->id] = [$event, $how];
            }
        }

        // 3: the nearest unclaimed event naming the client.
        foreach ($meetings as $meeting) {
            if (isset($matches[$meeting->id]) || ! $this->name($meeting->client)) {
                continue;
            }

            $start = Carbon::parse($meeting->schedule_from, 'UTC');

            $near = $events
                ->reject(fn ($event) => isset($claimed[$event['id']]))
                ->filter(fn ($event) => $this->names($event, $meeting->client))
                ->filter(fn ($event) => abs($this->start($event)->diffInDays($start, false)) <= self::NEAR_DAYS)
                ->sortBy(fn ($event) => abs($this->start($event)->diffInMinutes($start, false)))
                ->first();

            if ($near) {
                $claimed[$near['id']] = $meeting->id;
                $matches[$meeting->id] = [$near, 'client name, '.$this->shift($start, $this->start($near))];
            }
        }

        $rows = [];

        foreach ($meetings as $meeting) {
            [$event, $how] = $matches[$meeting->id] ?? [null, null];

            $row = [
                'lead_id' => $meeting->lead_id,
                'client'  => $meeting->client ?: $meeting->lead_title,
                'owner'   => $meeting->owner,
                'meeting' => $meeting->title,
                'when'    => Carbon::parse($meeting->schedule_from, 'UTC'),
                'event'   => $event['summary'] ?? null,
                'matched' => $how,
                'files'   => [],
            ];

            if (! $event) {
                $rows[] = $row + ['status' => 'not synced', 'reason' => $this->missingReason($meeting)];

                continue;
            }

            if (! $dryRun && $how !== null && ! str_starts_with($how, 'client name') && ! $meeting->google_event_id) {
                DB::table('nexus_meeting_calendar_events')->updateOrInsert(
                    ['activity_id' => $meeting->id],
                    ['google_event_id' => $event['id'], 'created_at' => now(), 'updated_at' => now()]
                );
            }

            $files = $this->files($event);
            $row['files'] = array_column($files, 'kind');

            if (! $files) {
                $rows[] = $row + ['status' => 'not synced', 'reason' => $this->emptyReason($meeting, $event)];

                continue;
            }

            $rows[] = $row + $this->store($meeting->lead_id, $meeting->id, $event, $files, $dryRun);
        }

        // Events with files no meeting claimed: match a lead by the client's name.
        $orphans = [];

        foreach ($events as $event) {
            $start = $this->start($event);

            if (isset($claimed[$event['id']]) || $start->lt($from) || $start->gte($to) || ! ($files = $this->files($event))) {
                continue;
            }

            $client = $this->clientFromTitle($event['summary'] ?? '');
            $leads = $client ? $this->leadsFor($client) : collect();

            if ($leadIds) {
                $leads = $leads->whereIn('lead_id', $leadIds);

                if ($leads->isEmpty()) {
                    continue;
                }
            }

            $orphan = [
                'event'   => $event['summary'] ?? '(no title)',
                'when'    => $start,
                'files'   => array_column($files, 'kind'),
                'lead_id' => null,
            ];

            if ($leads->count() !== 1) {
                $orphans[] = $orphan + [
                    'status' => 'not synced',
                    'reason' => $leads->isEmpty()
                        ? 'no lead for "'.($client ?: $event['summary'] ?? '').'" in the CRM (not a sales meeting, or the client is named differently)'
                        : $leads->count().' leads for "'.$client.'"; cannot tell which',
                ];

                continue;
            }

            $lead = $leads->first();

            $orphans[] = array_merge($orphan, ['lead_id' => $lead->lead_id, 'owner' => $lead->owner])
                + $this->store($lead->lead_id, null, $event, $files, $dryRun)
                + ['matched' => 'client name only (no CRM meeting at that time)'];
        }

        return ['rows' => $rows, 'orphans' => $orphans];
    }

    /**
     * Write the note, or add files that arrived since to the one already written.
     */
    protected function store(int $leadId, ?int $meetingId, array $event, array $files, bool $dryRun): array
    {
        $existing = DB::table('nexus_meeting_notes')
            ->where('google_event_id', $event['id'])
            ->where('lead_id', $leadId)
            ->first();

        $known = $existing ? json_decode($existing->file_ids, true) : [];
        $new = array_values(array_diff(array_column($files, 'id'), $known));

        if ($existing && ! $new) {
            return ['status' => 'already synced', 'reason' => null];
        }

        $status = $existing ? 'updated' : 'synced';
        $reason = $existing ? count($new).' new file(s) added to the note' : null;

        if ($dryRun) {
            return ['status' => $status.' (dry run)', 'reason' => $reason];
        }

        $comment = $this->comment($event, $files);
        $additional = json_encode(['nexus_meet' => [
            'event_id'  => $event['id'],
            'meet_link' => $event['hangoutLink'] ?? null,
            'start'     => $event['start']['dateTime'],
            'files'     => $files,
        ]]);

        DB::transaction(function () use ($existing, $leadId, $meetingId, $event, $files, $comment, $additional) {
            if ($existing) {
                DB::table('activities')->where('id', $existing->note_activity_id)->update([
                    'comment'    => $comment,
                    'additional' => $additional,
                    'updated_at' => now(),
                ]);

                DB::table('nexus_meeting_notes')->where('id', $existing->id)->update([
                    'file_ids'   => json_encode(array_column($files, 'id')),
                    'updated_at' => now(),
                ]);

                return;
            }

            $note = $this->activityRepository->create([
                'type'       => 'note',
                'title'      => 'Meeting notes from Google Meet',
                'comment'    => $comment,
                'additional' => $additional,
                'user_id'    => null,
                'is_done'    => 1,
            ]);

            DB::table('lead_activities')->insert(['lead_id' => $leadId, 'activity_id' => $note->id]);

            DB::table('nexus_meeting_notes')->insert([
                'google_event_id'     => $event['id'],
                'lead_id'             => $leadId,
                'meeting_activity_id' => $meetingId,
                'note_activity_id'    => $note->id,
                'file_ids'            => json_encode(array_column($files, 'id')),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        });

        return ['status' => $status, 'reason' => $reason];
    }

    protected function comment(array $event, array $files): string
    {
        $zone = $event['start']['timeZone'] ?? config('app.timezone', 'UTC');

        $lines = ['From the Google Meet on '.$this->start($event)->setTimezone($zone)->format('D j M Y, g:i A T').':'];

        foreach ($files as $file) {
            $lines[] = '';
            $lines[] = $file['kind'].($file['title'] !== $file['kind'] ? ' ('.$file['title'].')' : '').':';
            $lines[] = $file['url'];
        }

        return implode("\n", $lines);
    }

    /**
     * The files Meet attached to the event, labelled by what they are.
     *
     * @return array<int, array{id: string, kind: string, title: string, url: string, mime: ?string}>
     */
    public function files(array $event): array
    {
        return collect($event['attachments'] ?? [])
            ->filter(fn ($file) => ! empty($file['fileUrl']))
            ->map(function ($file) {
                $title = trim($file['title'] ?? '') ?: 'File';
                $mime = $file['mimeType'] ?? null;

                return [
                    'id'    => $file['fileId'] ?? $file['fileUrl'],
                    'kind'  => match (true) {
                        str_starts_with((string) $mime, 'video/')        => 'Recording',
                        stripos($title, 'gemini') !== false              => 'Notes by Gemini',
                        stripos($title, 'transcript') !== false          => 'Transcript',
                        default                                          => $title,
                    },
                    'title' => $title,
                    'url'   => $file['fileUrl'],
                    'mime'  => $mime,
                ];
            })
            ->values()
            ->all();
    }

    protected function meetings(Carbon $from, Carbon $to, array $leadIds): Collection
    {
        return DB::table('activities')
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->join('leads', 'leads.id', '=', 'lead_activities.lead_id')
            ->leftJoin('persons', 'persons.id', '=', 'leads.person_id')
            ->leftJoin('users', 'users.id', '=', 'leads.user_id')
            ->leftJoin('nexus_meeting_calendar_events', 'nexus_meeting_calendar_events.activity_id', '=', 'activities.id')
            ->where('activities.type', 'meeting')
            ->whereNotNull('activities.schedule_from')
            ->where('activities.schedule_from', '>=', $from->copy()->utc()->format('Y-m-d H:i:s'))
            ->where('activities.schedule_from', '<', $to->copy()->utc()->format('Y-m-d H:i:s'))
            ->when($leadIds, fn ($query) => $query->whereIn('leads.id', $leadIds))
            ->orderBy('activities.schedule_from')
            ->get([
                'activities.id',
                'activities.title',
                'activities.schedule_from',
                'activities.is_done',
                'leads.id as lead_id',
                'leads.title as lead_title',
                'persons.name as client',
                'users.name as owner',
                'nexus_meeting_calendar_events.google_event_id',
            ]);
    }

    protected function sameMinute(Collection $events, object $meeting): ?array
    {
        $start = Carbon::parse($meeting->schedule_from, 'UTC')->startOfMinute();

        $atStart = $events->filter(fn ($event) => $this->start($event)->startOfMinute()->equalTo($start));

        if (! $this->name($meeting->client)) {
            return $atStart->count() === 1 ? $atStart->first() : null;
        }

        return $atStart->first(fn ($event) => $this->names($event, $meeting->client));
    }

    protected function missingReason(object $meeting): string
    {
        return match (true) {
            str_contains($meeting->title, '(rescheduled)') => 'rescheduled: superseded by the meeting it was moved to',
            str_contains($meeting->title, 'cancelled')     => 'cancelled, and no calendar event found',
            ! $this->name($meeting->client)                => 'no calendar event at this time, and the lead has no client name to search by',
            default                                        => 'no calendar event found for this client (not added to the sales calendar, or deleted)',
        };
    }

    protected function emptyReason(object $meeting, array $event): string
    {
        $meet = empty($event['hangoutLink']) ? ' The event has no Google Meet link.' : '';

        return match (true) {
            str_contains($meeting->title, '(no-show)') => 'no-show: the event has no notes or recording.'.$meet,
            default                                    => 'the event has no notes or recording (Gemini was not taking notes, or nobody joined).'.$meet,
        };
    }

    /**
     * Leads whose person has exactly this name, with their owner.
     */
    protected function leadsFor(string $client): Collection
    {
        return DB::table('leads')
            ->join('persons', 'persons.id', '=', 'leads.person_id')
            ->leftJoin('users', 'users.id', '=', 'leads.user_id')
            ->whereRaw('LOWER(TRIM(persons.name)) = ?', [mb_strtolower($client)])
            ->get(['leads.id as lead_id', 'users.name as owner']);
    }

    /**
     * Sales events are titled "Client Name | Virtual Assistant Discovery Call".
     */
    protected function clientFromTitle(string $summary): ?string
    {
        return str_contains($summary, '|') ? (trim(explode('|', $summary)[0]) ?: null) : null;
    }

    protected function names(array $event, ?string $client): bool
    {
        $name = $this->name($client);

        return $name !== null && str_contains(mb_strtolower($event['summary'] ?? ''), $name);
    }

    protected function name(?string $client): ?string
    {
        $name = mb_strtolower(trim((string) $client));

        return $name !== '' ? $name : null;
    }

    protected function start(array $event): Carbon
    {
        return Carbon::parse($event['start']['dateTime'])->utc();
    }

    protected function shift(Carbon $crm, Carbon $calendar): string
    {
        $minutes = (int) $crm->diffInMinutes($calendar, false);

        if ($minutes === 0) {
            return 'same time';
        }

        $span = abs($minutes) >= 1440
            ? round(abs($minutes) / 1440, 1).' day(s)'
            : (abs($minutes) >= 60 ? round(abs($minutes) / 60, 1).' h' : abs($minutes).' min');

        return 'calendar event '.$span.($minutes > 0 ? ' later' : ' earlier');
    }
}
