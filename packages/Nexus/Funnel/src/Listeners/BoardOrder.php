<?php

namespace Nexus\Funnel\Listeners;

use Illuminate\Support\Facades\DB;

/**
 * Board order within each column: the soonest open appointment first, so the
 * next meeting (or, in New Lead, the next call back) is at the top. Overdue ones
 * come before upcoming ones, as they are the earliest and still need action.
 * Leads with nothing open follow, most recently updated first as before.
 *
 * The appointment is the lead's most recent meeting, or call in New Lead: the
 * same one its card shows.
 */
class BoardOrder
{
    /**
     * @return array<int, array{0: \Illuminate\Contracts\Database\Query\Expression, 1: string}>
     */
    public function handle($stage): array
    {
        $type = $stage->code === 'new' ? 'call' : 'meeting';

        $next = "(SELECT IF(a.is_done = 0, a.schedule_from, NULL)
            FROM activities a
            JOIN lead_activities la ON la.activity_id = a.id
            WHERE la.lead_id = leads.id AND a.type = '{$type}' AND a.schedule_from IS NOT NULL
            ORDER BY a.id DESC
            LIMIT 1)";

        return [
            [DB::raw("{$next} IS NULL"), 'asc'],
            [DB::raw($next), 'asc'],
        ];
    }
}
