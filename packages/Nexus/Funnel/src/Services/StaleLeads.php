<?php

namespace Nexus\Funnel\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The open leads that need chasing, for the daily digest:
 *
 *  - overdue: a call, meeting or lunch on the lead that is not marked done and
 *    whose due time has passed;
 *  - rotten: the lead has sat in its pipeline longer than the pipeline's
 *    rotten days, by Krayin's own rule (days since created >= rotten_days).
 *
 * Open means in any pipeline but the archive, in a stage other than Won or
 * Lost.
 */
class StaleLeads
{
    /**
     * Activity types that carry a due time and a done flag.
     */
    protected array $followUpTypes = ['call', 'meeting', 'lunch'];

    /**
     * @return Collection<int, object> one row per overdue activity, most overdue first
     */
    public function overdueFollowUps(Carbon $now): Collection
    {
        $due = DB::raw('COALESCE(activities.schedule_to, activities.schedule_from)');

        return $this->openLeads()
            ->join('lead_activities', 'lead_activities.lead_id', '=', 'leads.id')
            ->join('activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->whereIn('activities.type', $this->followUpTypes)
            ->where('activities.is_done', 0)
            ->whereNotNull('activities.schedule_from')
            ->where($due, '<', $now->copy()->utc())
            ->orderBy($due)
            ->get([
                ...$this->leadColumns(),
                'activities.id as activity_id',
                'activities.type as activity_type',
                'activities.title as activity_title',
                'activities.schedule_from',
                'activities.schedule_to',
            ])
            ->each(function ($row) use ($now) {
                $row->due = Carbon::parse($row->schedule_to ?? $row->schedule_from, 'UTC');
                $row->days_overdue = (int) $row->due->diffInDays($now);
            });
    }

    /**
     * @return Collection<int, object> one row per rotten lead, longest rotten first
     */
    public function rottenLeads(Carbon $now): Collection
    {
        return $this->openLeads()
            ->whereRaw('DATEDIFF(?, leads.created_at) >= lead_pipelines.rotten_days', [$now->copy()->utc()])
            ->orderBy('leads.created_at')
            ->get([
                ...$this->leadColumns(),
                'lead_pipelines.rotten_days as pipeline_rotten_days',
            ])
            ->each(function ($row) use ($now) {
                $row->created = Carbon::parse($row->created_at, 'UTC');
                $row->days_rotten = (int) $row->created->copy()->addDays($row->pipeline_rotten_days)->diffInDays($now);
                $row->last_activity = $this->lastActivity($row->lead_id);
            });
    }

    protected function openLeads()
    {
        return DB::table('leads')
            ->join('lead_pipelines', 'lead_pipelines.id', '=', 'leads.lead_pipeline_id')
            ->join('lead_pipeline_stages', 'lead_pipeline_stages.id', '=', 'leads.lead_pipeline_stage_id')
            ->leftJoin('users', 'users.id', '=', 'leads.user_id')
            ->leftJoin('persons', 'persons.id', '=', 'leads.person_id')
            ->where('lead_pipelines.name', '!=', config('funnel.archive_pipeline'))
            ->whereNotIn('lead_pipeline_stages.code', ['won', 'lost']);
    }

    protected function leadColumns(): array
    {
        return [
            'leads.id as lead_id',
            'leads.title as lead_title',
            'leads.created_at',
            'persons.name as client_name',
            'users.name as owner_name',
            'lead_pipelines.name as pipeline_name',
            'lead_pipeline_stages.name as stage_name',
        ];
    }

    /**
     * When anything was last logged on the lead (note, call, meeting, email...).
     */
    protected function lastActivity(int $leadId): ?Carbon
    {
        $at = DB::table('activities')
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('lead_activities.lead_id', $leadId)
            ->where('activities.type', '!=', 'system')
            ->max('activities.created_at');

        return $at ? Carbon::parse($at, 'UTC') : null;
    }
}
