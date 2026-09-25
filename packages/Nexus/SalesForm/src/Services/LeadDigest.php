<?php

namespace Nexus\SalesForm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Flattens a lead, its person, owner and custom field values into a plain array
 * suitable for an email and for queue serialisation.
 */
class LeadDigest
{
    /**
     * Custom attribute type => the attribute_values column holding it.
     */
    const TYPE_COLUMN = [
        'text' => 'text_value',
        'textarea' => 'text_value',
        'price' => 'float_value',
        'boolean' => 'boolean_value',
        'select' => 'integer_value',
        'multiselect' => 'text_value',
        'checkbox' => 'text_value',
        'email' => 'json_value',
        'address' => 'json_value',
        'phone' => 'json_value',
        'lookup' => 'integer_value',
        'datetime' => 'datetime_value',
        'date' => 'date_value',
        'file' => 'text_value',
        'image' => 'text_value',
    ];

    public function build($lead): array
    {
        $lead->loadMissing(['user', 'person', 'stage', 'source', 'type']);

        $fields = $this->customFields($lead->id);

        $person = $lead->person;
        $clientEmail = '';
        $clientPhone = '';

        if ($person) {
            $emails = is_string($person->emails) ? json_decode($person->emails, true) : $person->emails;
            $clientEmail = $emails[0]['value'] ?? '';

            $numbers = is_string($person->contact_numbers) ? json_decode($person->contact_numbers, true) : $person->contact_numbers;
            $clientPhone = $numbers[0]['value'] ?? '';
        }

        $meetingLocal = null;

        if (! empty($fields['meeting_at'])) {
            try {
                $meetingLocal = Carbon::parse($fields['meeting_at']);
            } catch (\Throwable) {
                $meetingLocal = null;
            }
        }

        $timezoneLabel = $fields['meeting_timezone'] ?? '';
        $timezone = config('sales_form.timezones')[$timezoneLabel] ?? null;

        return [
            'id'             => $lead->id,
            'title'          => $lead->title,
            'url'            => rtrim(config('app.url'), '/').'/'.config('app.admin_path').'/leads/view/'.$lead->id,
            'value'          => $lead->lead_value,
            'currency'       => config('app.currency', 'USD'),
            'stage'          => $lead->stage->name ?? '',
            'source'         => $lead->source->name ?? '',
            'type'           => $lead->type->name ?? '',
            'created_at'     => optional($lead->created_at)->format('Y-m-d H:i'),
            'expected_close' => $lead->expected_close_date,

            'owner_name'  => $lead->user->name ?? 'Unassigned',
            'owner_email' => $lead->user->email ?? '',

            'client_name'  => $person->name ?? '',
            'client_email' => $clientEmail,
            'client_phone' => $clientPhone,

            'brokerage'   => $fields['brokerage'] ?? '',
            'city'        => $fields['agent_city'] ?? '',
            'state'       => $fields['agent_state'] ?? '',
            'country'     => $fields['agent_country'] ?? '',
            'experience'  => $fields['experience_years'] ?? '',
            'license'     => $fields['license_details'] ?? '',
            'assistant'   => $fields['using_assistant'] ?? '',
            'assistant_type' => $fields['assistant_type'] ?? '',
            'willingness' => $fields['willingness_to_hire'] ?? '',
            'notes'       => $fields['source_notes'] ?? '',
            'vicidial'    => $fields['vicidial_lead_code'] ?? '',

            'meeting_local'    => $meetingLocal?->format('Y-m-d H:i:s'),
            'meeting_display'  => $meetingLocal?->format('l, j F Y \a\t g:i A'),
            'timezone_label'   => $timezoneLabel,
            'timezone'         => $timezone,
            'has_meeting'      => $meetingLocal !== null && $timezone !== null,
            'calendar_event_url' => app(MeetingCalendarSync::class)->currentLink($lead->id),
            'calendar_sync'      => app(MeetingCalendarSync::class)->state($lead->id),
        ];
    }

    /**
     * code => display value for every user-defined attribute on the lead, with
     * select options resolved to their label.
     */
    protected function customFields(int $leadId): array
    {
        $rows = DB::table('attribute_values as av')
            ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
            ->leftJoin('attribute_options as o', 'o.id', '=', 'av.integer_value')
            ->where('av.entity_type', 'leads')
            ->where('av.entity_id', $leadId)
            ->where('a.is_user_defined', 1)
            ->get([
                'a.code', 'a.type', 'o.name as option_name',
                'av.text_value', 'av.integer_value', 'av.float_value',
                'av.boolean_value', 'av.datetime_value', 'av.date_value',
            ]);

        $fields = [];

        foreach ($rows as $row) {
            if ($row->type === 'select') {
                $fields[$row->code] = $row->option_name;

                continue;
            }

            $column = self::TYPE_COLUMN[$row->type] ?? 'text_value';
            $fields[$row->code] = $row->{$column} ?? null;
        }

        return $fields;
    }
}
