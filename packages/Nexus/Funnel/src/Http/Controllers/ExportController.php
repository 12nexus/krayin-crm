<?php

namespace Nexus\Funnel\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV of every lead on the board, with the stage each one currently sits in.
 *
 * Exports the pipeline the board is showing, all stages at once, and obeys the
 * same visibility rule as the board: a sales executive gets their own leads.
 */
class ExportController extends Controller
{
    /**
     * Column heading => how to read it off a row.
     */
    protected function columns(): array
    {
        return [
            'Lead ID'              => fn ($lead) => $lead->id,
            'Title'                => fn ($lead) => $lead->title,
            'Stage'                => fn ($lead) => $lead->stage_name,
            'Pipeline'             => fn ($lead) => $lead->pipeline_name,
            'Lead validity'        => fn ($lead) => $lead->fields['lead_validity'] ?? '',
            'Meeting appeared?'    => fn ($lead) => $lead->fields['meeting_appeared'] ?? '',
            'Most recent meeting'  => fn ($lead) => $lead->meeting_label,
            'Meeting timezone'     => fn ($lead) => $lead->fields['meeting_timezone'] ?? '',
            'Part-time / Full-time' => fn ($lead) => $lead->fields['engagement_type'] ?? '',
            'Estimated value'      => fn ($lead) => $lead->lead_value !== null ? number_format((float) $lead->lead_value, 2, '.', '') : '',
            'Sales owner'          => fn ($lead) => $lead->owner_name,
            'Client'               => fn ($lead) => $lead->person_name,
            'Email'                => fn ($lead) => $lead->emails,
            'Phone'                => fn ($lead) => $lead->phones,
            'Brokerage'            => fn ($lead) => $lead->fields['brokerage'] ?? '',
            'City'                 => fn ($lead) => $lead->fields['agent_city'] ?? '',
            'State / Province'     => fn ($lead) => $lead->fields['agent_state'] ?? '',
            'Country'              => fn ($lead) => $lead->fields['agent_country'] ?? '',
            'Source'               => fn ($lead) => $lead->source_name,
            'Type'                 => fn ($lead) => $lead->type_name,
            'Expected close date'  => fn ($lead) => $lead->expected_close_date,
            'Closed at'            => fn ($lead) => $lead->closed_at,
            'Created at'           => fn ($lead) => $lead->created_at,
            'Last updated'         => fn ($lead) => $lead->updated_at,
            'Discovery notes'      => fn ($lead) => $lead->fields['source_notes'] ?? '',
        ];
    }

    public function csv(): StreamedResponse
    {
        abort_unless(bouncer()->hasPermission('leads'), 401);

        $pipeline = $this->pipeline();

        $leads = $this->leads($pipeline->id);

        $columns = $this->columns();

        $filename = sprintf('leads-%s-%s.csv', Str::slug($pipeline->name), now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($leads, $columns) {
            $handle = fopen('php://output', 'w');

            // Excel reads a CSV as the local codepage unless the file says otherwise.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_keys($columns));

            foreach ($leads as $lead) {
                fputcsv($handle, array_map(fn ($read) => $read($lead), $columns));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The pipeline the board is showing: whichever the switcher selected, else
     * the default one.
     */
    protected function pipeline(): object
    {
        $id = (int) request('pipeline_id');

        $pipeline = $id
            ? DB::table('lead_pipelines')->where('id', $id)->first()
            : null;

        return $pipeline
            ?? DB::table('lead_pipelines')->where('is_default', 1)->first()
            ?? abort(404);
    }

    protected function leads(int $pipelineId): array
    {
        $query = DB::table('leads')
            ->leftJoin('lead_pipeline_stages as stages', 'stages.id', '=', 'leads.lead_pipeline_stage_id')
            ->leftJoin('lead_pipelines as pipelines', 'pipelines.id', '=', 'leads.lead_pipeline_id')
            ->leftJoin('users', 'users.id', '=', 'leads.user_id')
            ->leftJoin('persons', 'persons.id', '=', 'leads.person_id')
            ->leftJoin('lead_sources as sources', 'sources.id', '=', 'leads.lead_source_id')
            ->leftJoin('lead_types as types', 'types.id', '=', 'leads.lead_type_id')
            ->where('leads.lead_pipeline_id', $pipelineId)
            ->orderBy('stages.sort_order')
            ->orderByDesc('leads.updated_at');

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $query->whereIn('leads.user_id', $userIds);
        }

        $leads = $query->get([
            'leads.id',
            'leads.title',
            'leads.lead_value',
            'leads.expected_close_date',
            'leads.closed_at',
            'leads.created_at',
            'leads.updated_at',
            'stages.name as stage_name',
            'pipelines.name as pipeline_name',
            'users.name as owner_name',
            'persons.name as person_name',
            'persons.emails as person_emails',
            'persons.contact_numbers as person_numbers',
            'sources.name as source_name',
            'types.name as type_name',
        ]);

        if ($leads->isEmpty()) {
            return [];
        }

        $ids = $leads->pluck('id')->all();

        $fields = $this->fields($ids);
        $meetings = $this->meetings($ids);

        foreach ($leads as $lead) {
            $lead->fields = $fields[$lead->id] ?? [];
            $lead->emails = $this->flatten($lead->person_emails);
            $lead->phones = $this->flatten($lead->person_numbers);
            $lead->meeting_label = $this->meetingLabel(
                $meetings[$lead->id] ?? null,
                $lead->fields['meeting_timezone'] ?? null
            );
        }

        return $leads->all();
    }

    /**
     * lead id => [code => label], for the custom fields the export prints.
     */
    protected function fields(array $leadIds): array
    {
        $rows = DB::table('attribute_values as av')
            ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
            ->leftJoin('attribute_options as o', 'o.id', '=', 'av.integer_value')
            ->where('av.entity_type', 'leads')
            ->whereIn('av.entity_id', $leadIds)
            ->whereIn('a.code', [
                'lead_validity', 'meeting_appeared', 'meeting_timezone', 'engagement_type',
                'brokerage', 'agent_city', 'agent_state', 'agent_country', 'source_notes',
            ])
            ->get(['av.entity_id', 'a.code', 'a.type', 'o.name as option_name', 'av.text_value']);

        $fields = [];

        foreach ($rows as $row) {
            $fields[$row->entity_id][$row->code] = $row->type === 'select'
                ? $row->option_name
                : $row->text_value;
        }

        return $fields;
    }

    /**
     * lead id => the meeting booked last, which after any reschedule is the one
     * that counts. Mirrors what the card and the lead page show.
     */
    protected function meetings(array $leadIds): array
    {
        $latest = DB::table('lead_activities')
            ->join('activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->where('activities.type', 'meeting')
            ->whereNotNull('activities.schedule_from')
            ->whereIn('lead_activities.lead_id', $leadIds)
            ->groupBy('lead_activities.lead_id')
            ->selectRaw('lead_activities.lead_id as lead_id, MAX(activities.id) as activity_id')
            ->pluck('activity_id', 'lead_id');

        if ($latest->isEmpty()) {
            return [];
        }

        $schedules = DB::table('activities')
            ->whereIn('id', $latest->values()->all())
            ->pluck('schedule_from', 'id');

        return $latest
            ->map(fn ($activityId) => $schedules[$activityId] ?? null)
            ->filter()
            ->all();
    }

    /**
     * The meeting in the client's own timezone, as the board shows it.
     */
    protected function meetingLabel(?string $scheduleFrom, ?string $timezoneLabel): string
    {
        if (! $scheduleFrom) {
            return '';
        }

        $zone = config('sales_form.timezones')[$timezoneLabel] ?? config('app.timezone', 'UTC');

        return Carbon::parse($scheduleFrom, 'UTC')->setTimezone($zone)->format('Y-m-d H:i T');
    }

    /**
     * Person emails and numbers are stored as JSON lists of {value, label}.
     */
    protected function flatten($json): string
    {
        $rows = is_string($json) ? json_decode($json, true) : $json;

        if (! is_array($rows)) {
            return '';
        }

        return collect($rows)->pluck('value')->filter()->implode(', ');
    }
}
