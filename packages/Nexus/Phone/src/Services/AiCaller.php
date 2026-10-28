<?php

namespace Nexus\Phone\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Nexus\Funnel\Services\Funnel;
use Nexus\SalesForm\Listeners\LeadCreated;
use Nexus\SalesForm\Services\CalendarFeed;
use Nexus\SalesForm\Services\LeadBuilder;
use Nexus\SalesForm\Services\LeadFields;
use Nexus\SalesForm\Services\MeetingSlots;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;

/**
 * The AI caller's side of the CRM. It phones open New Leads that have gone
 * quiet (no meeting in the last stale_days days), tries to book a discovery
 * meeting, and reports how each call went. Every outcome lands on the lead the
 * same way a rep's would: a logged call, a booked meeting, a call back for the
 * owner, or the lead closed as Lost with the reason.
 */
class AiCaller
{
    /**
     * Outcomes the AI caller reports. contacted: a person took part in the call
     * and gave an answer; the others count towards max_attempts.
     */
    const DISPOSITIONS = [
        'meeting_booked' => ['label' => 'Meeting booked', 'contacted' => true],
        'callback'       => ['label' => 'Call back requested', 'contacted' => true],
        'follow_up'      => ['label' => 'Interested, no time yet', 'contacted' => true],
        'not_interested' => ['label' => 'Not interested', 'contacted' => true],
        'dnc'            => ['label' => 'Do not call', 'contacted' => true],
        'wrong_number'   => ['label' => 'Wrong number', 'contacted' => true],
        'hung_up'        => ['label' => 'Hung up', 'contacted' => false],
        'voicemail'      => ['label' => 'Voicemail', 'contacted' => false],
        'no_answer'      => ['label' => 'No answer', 'contacted' => false],
        'busy'           => ['label' => 'Busy', 'contacted' => false],
        'failed'         => ['label' => 'Call failed', 'contacted' => false],
    ];

    public function __construct(
        protected CallLog $callLog,
        protected LeadFields $fields,
        protected MeetingSlots $slots,
        protected LeadRepository $leads,
    ) {}

    /**
     * Leads to call now: open, in New Lead, created at least stale_days ago,
     * no meeting in that time (or booked ahead), no open call back for the
     * team, not called by the AI in the last retry_hours, fewer than
     * max_attempts unanswered AI calls, and a US/Canada number not on the
     * do-not-call list.
     */
    public function queue(int $limit = 20, ?int $leadId = null): array
    {
        $cfg = config('phone.ai');
        $since = now()->subDays($cfg['stale_days'])->format('Y-m-d H:i:s');

        $rows = DB::table('leads')
            ->join('lead_pipelines as p', 'p.id', '=', 'leads.lead_pipeline_id')
            ->join('lead_pipeline_stages as s', 's.id', '=', 'leads.lead_pipeline_stage_id')
            ->join('persons', 'persons.id', '=', 'leads.person_id')
            ->leftJoin('users as owner', 'owner.id', '=', 'leads.user_id')
            ->when($leadId, fn ($q) => $q->where('leads.id', $leadId), fn ($q) => $q
                ->where('p.name', config('funnel.pipeline'))
                ->where('s.code', 'new')
                ->whereNull('leads.closed_at')
                ->where('leads.created_at', '<=', $since)
                ->whereNotExists(fn ($e) => $e->from('activities as a')
                    ->join('lead_activities as la', 'la.activity_id', '=', 'a.id')
                    ->whereColumn('la.lead_id', 'leads.id')
                    ->where(fn ($w) => $w
                        ->where(fn ($m) => $m->where('a.type', 'meeting')->whereNotNull('a.schedule_from')->where('a.schedule_from', '>=', $since))
                        ->orWhere(fn ($c) => $c->where('a.type', 'call')->where('a.is_done', 0)->whereNotNull('a.schedule_from'))))
                ->whereNotExists(fn ($e) => $e->from('nexus_ai_calls as r')
                    ->whereColumn('r.lead_id', 'leads.id')
                    ->where(fn ($w) => $w
                        ->where('r.called_at', '>=', now()->subHours($cfg['retry_hours'])->format('Y-m-d H:i:s'))
                        ->orWhere('r.contacted', 1))))
            ->select([
                'leads.id', 'leads.title', 'leads.created_at', 'leads.description',
                'persons.name as person_name', 'persons.contact_numbers', 'persons.emails',
                'owner.name as owner_name', 's.name as stage',
                DB::raw('(SELECT COUNT(*) FROM nexus_ai_calls r WHERE r.lead_id = leads.id AND r.contacted = 0) as attempts'),
                DB::raw("(SELECT MAX(a.schedule_from) FROM activities a JOIN lead_activities la ON la.activity_id = a.id WHERE la.lead_id = leads.id AND a.type = 'meeting' AND a.schedule_from IS NOT NULL) as last_meeting_at"),
            ])
            ->orderBy('attempts')
            ->orderByDesc('leads.id')
            ->limit(500)
            ->get();

        $dnc = DB::table('nexus_ai_dnc')->pluck('digits')->flip();
        $out = [];

        foreach ($rows as $row) {
            if (! $leadId && $row->attempts >= $cfg['max_attempts']) {
                continue;
            }

            $number = $this->dialable($row->contact_numbers);

            if (! $number || ($dnc->has($number) && ! $leadId)) {
                continue;
            }

            $fields = $this->fields->get($row->id);
            $emails = collect(json_decode((string) $row->emails, true) ?: [])->pluck('value')->filter()->values();

            $out[] = [
                'id'              => (int) $row->id,
                'title'           => $row->title,
                'person_name'     => $row->person_name,
                'first_name'      => trim(explode(' ', trim((string) $row->person_name))[0] ?? ''),
                'number'          => '+1'.$number,
                'dnc'             => $dnc->has($number),
                'email'           => $emails->first(),
                'owner_name'      => $row->owner_name,
                'stage'           => $row->stage,
                'timezone'        => $fields['meeting_timezone'] ?? $this->zoneOfState($fields['agent_state'] ?? null),
                'city'            => $fields['agent_city'] ?? null,
                'state'           => $fields['agent_state'] ?? null,
                'brokerage'       => $fields['brokerage'] ?? null,
                'experience'      => $fields['experience_years'] ?? null,
                'using_assistant' => $fields['using_assistant'] ?? null,
                'notes'           => mb_substr(trim(strip_tags((string) ($row->description ?? ''))), 0, 600),
                'created_at'      => Carbon::parse($row->created_at)->toIso8601String(),
                'last_meeting_at' => $row->last_meeting_at ? Carbon::parse($row->last_meeting_at, 'UTC')->toIso8601String() : null,
                'attempts'        => (int) $row->attempts,
            ];

            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Free discovery-meeting times for the client, in their zone: weekdays
     * within meeting_hours, at least lead_hours from now, clear of other
     * meetings (with the buffer) and of busy events on the sales calendar.
     *
     * @return array<int, array{date: string, time: string, label: string}>
     */
    public function freeSlots(string $timezoneLabel, int $max = 12, ?string $onDate = null): array
    {
        $zone = config('sales_form.timezones')[$timezoneLabel] ?? null;

        if (! $zone) {
            throw ValidationException::withMessages(['timezone' => 'Unknown time zone.']);
        }

        $cfg = config('phone.ai');
        [$from, $to] = $cfg['meeting_hours'];
        $earliest = now()->addHours($cfg['lead_hours']);
        $minutes = (int) config('sales_form.notify.meeting_minutes', 30);
        $feed = app(CalendarFeed::class);
        $out = [];

        $days = $onDate ? [Carbon::parse($onDate, $zone)->startOfDay()] : collect(range(0, $cfg['slot_days']))
            ->map(fn ($d) => now($zone)->startOfDay()->addDays($d))->all();

        foreach ($days as $day) {
            if ($day->isWeekend()) {
                continue;
            }

            $busy = [];

            try {
                if ($feed->configured()) {
                    foreach ($feed->day($day->copy(), false)['events'] as $event) {
                        if ($event['busy']) {
                            $busy[] = [$event['start_min'], $event['end_min']];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('AI caller: calendar feed unavailable', ['message' => $e->getMessage()]);
            }

            $slot = Carbon::parse($day->format('Y-m-d').' '.$from, $zone);
            $end = Carbon::parse($day->format('Y-m-d').' '.$to, $zone);

            for (; $slot->copy()->addMinutes($minutes) <= $end; $slot->addMinutes(30)) {
                if ($slot < $earliest) {
                    continue;
                }

                $startMin = (int) $day->diffInMinutes($slot);

                foreach ($busy as [$b0, $b1]) {
                    if ($startMin < $b1 && $startMin + $minutes > $b0) {
                        continue 2;
                    }
                }

                $utc = $slot->copy()->utc();

                if ($this->slots->clash($utc, $utc->copy()->addMinutes($minutes))) {
                    continue;
                }

                $out[] = ['date' => $slot->format('Y-m-d'), 'time' => $slot->format('H:i'), 'label' => $slot->format('l, F j \a\t g:i A')];

                if (count($out) >= $max) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * Book the discovery meeting the client agreed to on the call, exactly as
     * a rep booking it from the Funnel panel would.
     *
     * @return array{ok: bool, error?: string, meeting?: string}
     */
    public function book(int $leadId, string $date, string $time, string $timezoneLabel, ?string $email, ?string $notes): array
    {
        $lead = $this->leads->find($leadId);

        if (! $lead) {
            return ['ok' => false, 'error' => 'Lead not found.'];
        }

        $user = $this->actAsAi();

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL) && $lead->person) {
            $emails = collect($lead->person->emails ?: []);

            if (! $emails->contains(fn ($e) => strcasecmp($e['value'] ?? '', $email) === 0)) {
                $lead->person->emails = $emails->push(['value' => $email, 'label' => 'work'])->values()->all();
                $lead->person->save();
            }
        }

        try {
            $lead = $this->slots->locked(fn () => DB::transaction(fn () => app(LeadBuilder::class)->scheduleMeeting($lead, [
                'meeting_date'           => $date,
                'meeting_time'           => $time,
                'timezone'               => $timezoneLabel,
                'additional_information' => trim('Booked by the AI caller on a follow-up call. '.($notes ?? '')),
            ], $user, 'discovery')));
        } catch (ValidationException $e) {
            return ['ok' => false, 'error' => collect($e->errors())->flatten()->first() ?: 'That time is not available.'];
        }

        app(LeadCreated::class)->handle($lead, 'meeting');

        $zone = config('sales_form.timezones')[$timezoneLabel];

        return ['ok' => true, 'meeting' => Carbon::parse($date.' '.$time, $zone)->format('l, F j \a\t g:i A').' '.$timezoneLabel];
    }

    /**
     * A finished AI call: log it on the lead, then act on the outcome.
     */
    public function result(array $call): array
    {
        $cfg = config('phone.ai');
        $disposition = $call['disposition'];
        $meta = self::DISPOSITIONS[$disposition];
        $lead = $this->leads->find($call['lead_id']);
        $user = $this->actAsAi();

        $summary = trim((string) ($call['summary'] ?? ''));
        $transcript = trim((string) ($call['transcript'] ?? ''));

        $attempt = DB::table('nexus_ai_calls')->where('lead_id', $call['lead_id'])->where('contacted', 0)->count()
            + ($meta['contacted'] ? 0 : 1);

        $result = $meta['label'].($meta['contacted'] ? '' : " (AI {$attempt}/{$cfg['max_attempts']})");

        $notes = trim("AI caller outcome: {$meta['label']}.\n".($summary !== '' ? "Summary: {$summary}\n" : '')
            .($transcript !== '' ? "\nTranscript:\n".$transcript : ''));

        $this->callLog->record([
            'call_id'        => $call['call_id'],
            'direction'      => 'out',
            'number'         => $call['number'],
            'result'         => mb_substr($result, 0, 40),
            'started_at'     => $call['started_at'],
            'duration'       => (int) $call['duration'],
            'krayin_user_id' => $user->id,
            'user_name'      => $user->name,
            'lead_id'        => $call['lead_id'],
            'notes'          => mb_substr($notes, 0, 5000),
            'recording_url'  => $call['recording_url'] ?? null,
            'source'         => 'AI caller',
        ]);

        $action = 'none';
        $open = $lead && ! $lead->closed_at;

        try {
            DB::transaction(function () use (&$action, $open, $lead, $user, $call, $disposition, $meta, $summary, $attempt, $cfg) {
                $why = $summary !== '' ? ' '.$summary : '';

                switch ($disposition) {
                    case 'dnc':
                    case 'wrong_number':
                        DB::table('nexus_ai_dnc')->updateOrInsert(
                            ['digits' => $this->digits($call['number'])],
                            ['lead_id' => $call['lead_id'], 'reason' => $meta['label'], 'updated_at' => now(), 'created_at' => now()]
                        );

                        if ($open) {
                            app(Funnel::class)->markLost($lead, $user, ($disposition === 'dnc'
                                ? 'Asked not to be called again (AI follow-up call).'
                                : 'Wrong number (AI follow-up call).').$why);
                            $action = 'closed';
                        }
                        break;

                    case 'not_interested':
                        if ($open) {
                            app(Funnel::class)->markLost($lead, $user, 'Not interested (AI follow-up call).'.$why);
                            $action = 'closed';
                        }
                        break;

                    case 'callback':
                    case 'follow_up':
                        if ($open) {
                            [$at, $label] = $this->callBackTime($call, $lead->id);
                            app(LeadBuilder::class)->scheduleCall($lead, [
                                'call_at'       => $at,
                                'call_timezone' => $label,
                                'call_note'     => $summary,
                            ], $user, $disposition === 'callback'
                                ? 'The client asked the AI caller to call back at this time.'
                                : 'Interested on the AI follow-up call, but no meeting time was agreed. Follow up.');
                            $action = 'call_back';
                        }
                        break;

                    case 'meeting_booked':
                        $action = 'meeting';
                        break;

                    default:
                        if ($open && $attempt >= $cfg['max_attempts']) {
                            app(Funnel::class)->markLost($lead, $user, "Unreachable: {$attempt} AI follow-up calls with no answer.");
                            $action = 'closed';
                        }
                }

                DB::table('nexus_ai_calls')->updateOrInsert(['call_id' => $call['call_id']], [
                    'lead_id'     => $call['lead_id'],
                    'number'      => $call['number'],
                    'disposition' => $disposition,
                    'contacted'   => $meta['contacted'],
                    'duration'    => (int) $call['duration'],
                    'summary'     => $summary,
                    'action'      => $action,
                    'called_at'   => Carbon::parse($call['started_at'])->utc()->format('Y-m-d H:i:s'),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('AI caller: outcome not applied', ['lead_id' => $call['lead_id'], 'message' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage(), 'attempt' => $attempt];
        }

        return ['ok' => true, 'action' => $action, 'attempt' => $attempt];
    }

    /**
     * The agreed call-back time, or 11:00 on the next weekday in the client's
     * zone (Eastern when unknown).
     *
     * @return array{0: string, 1: string}
     */
    protected function callBackTime(array $call, int $leadId): array
    {
        $zones = config('sales_form.timezones');
        $label = $call['timezone'] ?? null;
        $label = isset($zones[$label]) ? $label : ($this->fields->get($leadId)['meeting_timezone'] ?? null);
        $label = isset($zones[$label]) ? $label : array_key_first($zones);

        if (! empty($call['callback_at'])) {
            try {
                return [Carbon::parse($call['callback_at'], $zones[$label])->format('Y-m-d H:i'), $label];
            } catch (\Throwable) {
            }
        }

        $next = now($zones[$label])->addWeekday()->setTime(11, 0);

        return [$next->format('Y-m-d H:i'), $label];
    }

    /**
     * The sales form's zone label for a US state (name or abbreviation), going by
     * where most of the state is. Alaska and Hawaii fall back to Pacific.
     */
    protected function zoneOfState(?string $state): ?string
    {
        $state = strtoupper(trim((string) $state));

        if ($state === '') {
            return null;
        }

        $zones = [
            'EST (Eastern Standard Time)' => ['CT', 'DE', 'DC', 'FL', 'GA', 'IN', 'KY', 'ME', 'MD', 'MA', 'MI', 'NH', 'NJ', 'NY', 'NC', 'OH', 'PA', 'RI', 'SC', 'VT', 'VA', 'WV',
                'CONNECTICUT', 'DELAWARE', 'DISTRICT OF COLUMBIA', 'FLORIDA', 'GEORGIA', 'INDIANA', 'KENTUCKY', 'MAINE', 'MARYLAND', 'MASSACHUSETTS', 'MICHIGAN', 'NEW HAMPSHIRE', 'NEW JERSEY', 'NEW YORK', 'NORTH CAROLINA', 'OHIO', 'PENNSYLVANIA', 'RHODE ISLAND', 'SOUTH CAROLINA', 'VERMONT', 'VIRGINIA', 'WEST VIRGINIA', 'ONTARIO', 'QUEBEC', 'ON', 'QC'],
            'CST (Central Standard Time)' => ['AL', 'AR', 'IL', 'IA', 'KS', 'LA', 'MN', 'MS', 'MO', 'NE', 'ND', 'OK', 'SD', 'TN', 'TX', 'WI',
                'ALABAMA', 'ARKANSAS', 'ILLINOIS', 'IOWA', 'KANSAS', 'LOUISIANA', 'MINNESOTA', 'MISSISSIPPI', 'MISSOURI', 'NEBRASKA', 'NORTH DAKOTA', 'OKLAHOMA', 'SOUTH DAKOTA', 'TENNESSEE', 'TEXAS', 'WISCONSIN', 'MANITOBA', 'MB'],
            'MT (Mountain Time)' => ['AZ', 'CO', 'ID', 'MT', 'NM', 'UT', 'WY', 'ARIZONA', 'COLORADO', 'IDAHO', 'MONTANA', 'NEW MEXICO', 'UTAH', 'WYOMING', 'ALBERTA', 'AB'],
            'PST (Pacific Standard Time)' => ['CA', 'NV', 'OR', 'WA', 'AK', 'HI', 'CALIFORNIA', 'NEVADA', 'OREGON', 'WASHINGTON', 'ALASKA', 'HAWAII', 'BRITISH COLUMBIA', 'BC'],
        ];

        foreach ($zones as $label => $states) {
            if (in_array($state, $states, true)) {
                return $label;
            }
        }

        return null;
    }

    protected function actAsAi(): User
    {
        $user = User::where('email', config('phone.ai.user_email'))->firstOrFail();

        auth()->guard('user')->setUser($user);

        return $user;
    }

    /**
     * The first US/Canada number on the person, as 10 digits.
     */
    protected function dialable(?string $json): ?string
    {
        foreach (json_decode((string) $json, true) ?: [] as $entry) {
            $digits = $this->digits((string) ($entry['value'] ?? ''));

            if (preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $digits)) {
                return $digits;
            }
        }

        return null;
    }

    protected function digits(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);

        return strlen($digits) === 11 && $digits[0] === '1' ? substr($digits, 1) : $digits;
    }
}
