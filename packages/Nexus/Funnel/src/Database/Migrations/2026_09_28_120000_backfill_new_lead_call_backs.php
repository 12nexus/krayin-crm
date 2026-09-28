<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Nexus\SalesForm\Services\LeadBuilder;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

/**
 * One-off: give the leads already waiting in New Lead the call back their
 * discovery notes ask for, now that a call back is a Call activity the board
 * shows and sorts by.
 *
 * Each time below was read from the lead's note by hand, relative to when the
 * note was taken (the lead's creation). The client's timezone comes from their
 * state or province, the nearest of the four the forms offer. Where the note
 * gives a day but no time, the call is at 11:00 AM client time, the same
 * default the calendar picker uses. Notes with no timing at all ("busy call
 * back", "Call Back") get no call; their card shows the note instead.
 *
 * A lead is skipped if it has left New Lead or already has a call, so running
 * this again, or on a copy of the data, is harmless.
 */
return new class extends Migration
{
    const EST = 'EST (Eastern Standard Time)';
    const CST = 'CST (Central Standard Time)';
    const MT = 'MT (Mountain Time)';
    const PST = 'PST (Pacific Standard Time)';

    /**
     * lead id => [client local time, timezone, how it was read from the note].
     */
    protected function calls(): array
    {
        return [
            124 => ['2026-11-09 11:00', self::EST, '"maybe in 2 3 months" from 9 Sep: two months on'],
            145 => ['2026-10-24 11:00', self::CST, '"call back at 24 oct"'],
            149 => ['2026-09-25 11:00', self::EST, '"Call Friday", noted Wed 23 Sep'],
            150 => ['2026-10-07 11:00', self::CST, '"Call Back After 2 Weeks" from 23 Sep'],
            151 => ['2026-10-15 11:00', self::EST, '"Call back at 15 oct"'],
            152 => ['2026-09-28 11:00', self::CST, '"Call Back on Monday", noted Wed 23 Sep'],
            153 => ['2026-10-15 11:00', self::EST, '"welcome to call at 15 oct"'],
            154 => ['2026-10-15 11:00', self::MT, '"call back at 15 oct"'],
            155 => ['2026-11-02 11:00', self::MT, '"Call back november 2026": first Monday of November'],
            156 => ['2026-10-16 11:00', self::EST, '"Call Back After 15 Oct": the day after'],
            158 => ['2026-09-28 11:00', self::EST, '"Call Back Monday", noted Wed 23 Sep'],
            159 => ['2026-10-01 11:00', self::MT, '"Call Back on 1 Oct"'],
            164 => ['2026-10-24 11:00', self::CST, '"call back on 24 oct 2026"'],
            165 => ['2027-01-04 11:00', self::MT, '"January Call Back": first Monday of January'],
            166 => ['2026-10-01 11:00', self::EST, '"Call Back Oct": 1 October'],
            167 => ['2026-09-30 11:00', self::EST, '"Every Week Call Back": a week after 23 Sep'],
            171 => ['2026-09-23 18:00', self::EST, '"Call Back Today", noted 23 Sep: later that day'],
            172 => ['2026-10-26 11:00', self::PST, '"call back ... in october last week": Monday of the last week'],
            173 => ['2026-09-24 11:00', self::CST, '"Call Back Tommorow", noted 23 Sep'],
            174 => ['2026-09-24 17:00', self::EST, '"Call Back Tomorrow 5pm EST", noted 23 Sep'],
            175 => ['2026-09-24 11:00', self::EST, '"Call Back tommorow", noted 23 Sep'],
            176 => ['2027-01-04 11:00', self::PST, '"call back january": first Monday of January'],
            179 => ['2026-10-20 11:00', self::MT, '"call back 20 oct"'],
            183 => ['2026-09-24 15:45', self::EST, '"Call Back After 2 hours", noted 24 Sep 1:45 PM EDT'],
            185 => ['2026-09-25 11:00', self::EST, '"Call Back Tomorrow", noted 24 Sep (New Brunswick, nearest zone offered)'],
            188 => ['2026-10-01 11:00', self::CST, '"Call Back October": 1 October'],
            189 => ['2026-09-25 11:00', self::CST, '"call back tomorrow", noted 24 Sep'],
            191 => ['2026-11-02 11:00', self::EST, '"Call Back November": first Monday of November'],
            198 => ['2026-10-20 11:00', self::EST, '"20 oct meeting date": call that day to book it'],
        ];
    }

    public function up(): void
    {
        $builder = app(LeadBuilder::class);

        foreach ($this->calls() as $leadId => [$at, $timezone, $reading]) {
            $lead = Lead::with(['stage', 'pipeline', 'person'])->find($leadId);

            if (! $lead
                || $lead->pipeline?->name !== config('funnel.pipeline')
                || $lead->stage?->code !== 'new') {
                continue;
            }

            $hasCall = DB::table('activities')
                ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
                ->where('lead_activities.lead_id', $leadId)
                ->where('activities.type', 'call')
                ->exists();

            $owner = $lead->user_id ? User::find($lead->user_id) : null;

            if ($hasCall || ! $owner) {
                continue;
            }

            $builder->scheduleCall($lead, [
                'call_at'       => $at,
                'call_timezone' => $timezone,
            ], $owner, 'Call back read from the discovery notes when call backs were added to the board: '.$reading.'.');
        }
    }

    public function down(): void
    {
        // Data only: the calls stay, and can be rescheduled or deleted from the lead.
    }
};
