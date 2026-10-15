<?php

namespace Nexus\Phone\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Repositories\ActivityRepository;

/**
 * Matches phone numbers to leads and records the web phone's calls as finished
 * "call" activities on the lead.
 *
 * The activities have no schedule: the funnel's call-back logic only reads call
 * activities with schedule_from set, so a logged call never stands in for a
 * scheduled call back.
 */
class CallLog
{
    public function __construct(protected ActivityRepository $activityRepository) {}

    /**
     * The lead a number belongs to: the person's most recently updated open lead,
     * else their latest lead.
     *
     * @return array{id: int, title: string, owner_id: ?int, owner_name: ?string, person_id: int, person_name: string}|null
     */
    public function leadFor(string $number): ?array
    {
        $digits = $this->digits($number);

        if (strlen($digits) < 7) {
            return null;
        }

        // Numbers are stored as typed ("(512) 842-0504"), so narrow on the last
        // four digits in SQL and compare digit strings here.
        $persons = DB::table('persons')
            ->where('contact_numbers', 'like', '%'.substr($digits, -4).'%')
            ->get(['id', 'name', 'contact_numbers'])
            ->filter(function ($person) use ($digits) {
                foreach (json_decode($person->contact_numbers ?? '[]', true) ?: [] as $entry) {
                    if ($this->digits((string) ($entry['value'] ?? '')) === $digits) {
                        return true;
                    }
                }

                return false;
            });

        if ($persons->isEmpty()) {
            return null;
        }

        $lead = DB::table('leads')
            ->leftJoin('users', 'users.id', '=', 'leads.user_id')
            ->whereIn('leads.person_id', $persons->pluck('id'))
            ->orderByRaw('leads.closed_at IS NULL DESC')
            ->orderByDesc('leads.updated_at')
            ->first(['leads.id', 'leads.title', 'leads.user_id', 'leads.person_id', 'users.name as owner_name']);

        if (! $lead) {
            return null;
        }

        return [
            'id'          => (int) $lead->id,
            'title'       => (string) $lead->title,
            'owner_id'    => $lead->user_id ? (int) $lead->user_id : null,
            'owner_name'  => $lead->owner_name,
            'person_id'   => (int) $lead->person_id,
            'person_name' => (string) $persons->firstWhere('id', $lead->person_id)?->name,
        ];
    }

    /**
     * Record a finished call (idempotent on call_id) and add it to its lead.
     *
     * @return array{logged: bool, lead_id: ?int, activity_id: ?int}
     */
    public function record(array $call): array
    {
        $row = DB::table('nexus_phone_calls')->where('call_id', $call['call_id'])->first();

        $leadId = $call['lead_id'] ?? null;

        if ($leadId && ! DB::table('leads')->where('id', $leadId)->exists()) {
            $leadId = null;
        }

        $leadId ??= $row->lead_id ?? $this->leadFor($call['number'])['id'] ?? null;

        $userId = $call['krayin_user_id'] ?? null;

        if ($userId && ! DB::table('users')->where('id', $userId)->exists()) {
            $userId = null;
        }

        $values = [
            'lead_id'       => $leadId,
            'user_id'       => $userId,
            'direction'     => $call['direction'],
            'number'        => $call['number'],
            'result'        => $call['result'],
            'duration'      => (int) $call['duration'],
            'recording_url' => $call['recording_url'] ?? null,
            'started_at'    => Carbon::parse($call['started_at'])->utc()->format('Y-m-d H:i:s'),
            'updated_at'    => now(),
        ];

        $values['notes'] = trim((string) ($call['notes'] ?? '')) ?: null;

        return DB::transaction(function () use ($row, $call, $values, $leadId) {
            if ($row) {
                DB::table('nexus_phone_calls')->where('id', $row->id)->update($values);
            } else {
                DB::table('nexus_phone_calls')->insert($values + ['call_id' => $call['call_id'], 'created_at' => now()]);
            }

            $row = DB::table('nexus_phone_calls')->where('call_id', $call['call_id'])->first();

            if (! $leadId) {
                return ['logged' => false, 'lead_id' => null, 'activity_id' => null];
            }

            $activityId = $row->activity_id ? (int) $row->activity_id : $this->createActivity($row, $call['user_name'] ?? null);

            if ($row->activity_id) {
                $this->refreshActivity($row, $call['user_name'] ?? null);
            }

            return ['logged' => true, 'lead_id' => (int) $leadId, 'activity_id' => $activityId];
        });
    }

    protected function createActivity(object $row, ?string $userName): int
    {
        $lead = DB::table('leads')->where('id', $row->lead_id)->first(['id', 'user_id', 'person_id']);

        $activity = $this->activityRepository->create([
            'title'   => $this->title($row),
            'type'    => 'call',
            'comment' => $this->comment($row, $userName),
            'user_id' => $row->user_id ?: ($lead->user_id ?? $this->fallbackUserId()),
            'is_done' => 1,
        ]);

        DB::table('lead_activities')->insert(['lead_id' => $lead->id, 'activity_id' => $activity->id]);

        if ($lead->person_id) {
            DB::table('person_activities')->insert(['person_id' => $lead->person_id, 'activity_id' => $activity->id]);
        }

        DB::table('nexus_phone_calls')->where('id', $row->id)->update(['activity_id' => $activity->id]);

        return (int) $activity->id;
    }

    protected function refreshActivity(object $row, ?string $userName): void
    {
        DB::table('activities')->where('id', $row->activity_id)->update([
            'title'      => $this->title($row),
            'comment'    => $this->comment($row, $userName),
            'updated_at' => now(),
        ]);
    }

    protected function title(object $row): string
    {
        $answered = $row->result === 'answered';

        return match (true) {
            $row->direction === 'in' && ! $answered => 'Missed call',
            $row->direction === 'in'                => 'Incoming call · '.$this->duration($row->duration),
            $answered                               => 'Outgoing call · '.$this->duration($row->duration),
            default                                 => 'Outgoing call · '.$row->result,
        };
    }

    protected function comment(object $row, ?string $userName): string
    {
        $who = $userName ?: ($row->user_id ? DB::table('users')->where('id', $row->user_id)->value('name') : null);

        $lines = array_filter([
            ($row->direction === 'in' ? 'From ' : 'To ').$row->number.' via the web phone',
            $who ? ($row->direction === 'in' && $row->result !== 'answered' ? 'Missed' : 'By '.$who) : null,
            $row->started_at ? 'Started '.Carbon::parse($row->started_at, 'UTC')->setTimezone(config('app.timezone', 'UTC'))->format('D j M Y, g:i A T') : null,
            $row->recording_url ? 'Recording: '.$row->recording_url : null,
        ]);

        $notes = trim((string) $row->notes);

        return implode("\n", $lines).($notes !== '' ? "\n\nNotes:\n".$notes : '');
    }

    protected function duration(int $seconds): string
    {
        return $seconds >= 60 ? intdiv($seconds, 60).' min '.($seconds % 60).' s' : $seconds.' s';
    }

    protected function fallbackUserId(): int
    {
        return (int) DB::table('users')->orderBy('id')->value('id');
    }

    protected function digits(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);

        // US numbers: compare without the leading country code.
        return strlen($digits) === 11 && $digits[0] === '1' ? substr($digits, 1) : $digits;
    }
}
