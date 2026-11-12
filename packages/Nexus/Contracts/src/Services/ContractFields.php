<?php

namespace Nexus\Contracts\Services;

use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Nexus\Contracts\Models\LeadContract;
use Nexus\SalesForm\Services\LeadFields;
use Webkul\Lead\Contracts\Lead;

/**
 * The fields both contracts are filled from: what they start out as for a
 * lead, how they validate, and the values the documents print, with derived
 * ones (hours a week, renewal date, the party lines) worked out from them so
 * a change to one field shows everywhere it is used.
 */
class ContractFields
{
    public function __construct(protected LeadFields $leadFields) {}

    /**
     * section => [field => [type, where it shows in the documents]]
     */
    public function definitions(): array
    {
        return [
            'client' => [
                'client_name'      => ['text', 'NDA: Disclosing Party, Printed Name · Agreement: The Client, Name'],
                'client_company'   => ['text', 'NDA: Disclosing Party, Title · Agreement: The Client, Company'],
                'client_brokerage' => ['text', 'NDA: Title, in brackets after the company'],
                'client_title'     => ['text', 'NDA: Title · Agreement: Designation'],
            ],
            'agreement' => [
                'agreement_date' => ['date', 'Agreement: "made as of"'],
                'start_date'     => ['date', 'Agreement: start date; the renewal date follows from it'],
                'duration'       => ['duration', 'Agreement: duration and renewal date'],
                'resources'      => ['number', 'Agreement: No of resources'],
                'hours_per_day'  => ['number', 'Agreement: working hours a day, hours a week'],
                'days_per_week'  => ['number', 'Agreement: days a week, hours a week'],
                'plan'           => ['plan', 'Agreement: Charges ("for Full time")'],
                'monthly_charge' => ['money', 'Agreement: Charges per month'],
                'setup_fee'      => ['money', 'Agreement: One-time setup'],
            ],
            'additions' => [
                'additional_responsibilities' => ['lines', 'Agreement: extra bullets under Responsibilities, one per line'],
                'additional_terms'            => ['textarea', 'Agreement: an "Additional Terms" section before the signatures'],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'client_name'      => ['required', 'string', 'max:120'],
            'client_company'   => ['nullable', 'string', 'max:160'],
            'client_brokerage' => ['nullable', 'string', 'max:160'],
            'client_title'     => ['nullable', 'string', 'max:120'],
            'agreement_date'   => ['required', 'date_format:Y-m-d'],
            'start_date'       => ['required', 'date_format:Y-m-d'],
            'duration'         => ['required', 'integer', Rule::in(config('contracts.durations'))],
            'resources'        => ['required', 'integer', 'min:1', 'max:50'],
            'hours_per_day'    => ['required', 'numeric', 'min:0.5', 'max:24', 'multiple_of:0.5'],
            'days_per_week'    => ['required', 'integer', 'min:1', 'max:7'],
            'plan'             => ['required', Rule::in(config('contracts.plans'))],
            'monthly_charge'   => ['required', 'numeric', 'min:0', 'max:1000000'],
            'setup_fee'        => ['required', 'numeric', 'min:0', 'max:1000000'],
            'additional_responsibilities' => ['nullable', 'string', 'max:5000'],
            'additional_terms'            => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * The saved fields, or the lead's defaults when nothing is saved yet. A
     * field added after a lead was saved falls back to its default.
     */
    public function current(Lead $lead, ?LeadContract $contract): array
    {
        return array_merge($this->defaults($lead), $contract?->fields ?? []);
    }

    /**
     * Starting values taken from the lead, its person and their organization.
     */
    public function defaults(Lead $lead): array
    {
        $fields = $this->leadFields->get($lead->id);
        $person = $lead->person;
        $engagement = $fields['engagement_type'] ?? null;
        $defaults = config('contracts.defaults');

        $brokerage = trim((string) ($fields['brokerage'] ?? $fields['firm'] ?? ''));
        $organization = trim((string) ($person?->organization?->name ?? ''));

        // Names typed all in lower case ("steven moning") read better capitalised.
        $name = trim((string) ($person?->name ?: $lead->title));

        if ($name === mb_strtolower($name)) {
            $name = mb_convert_case($name, MB_CASE_TITLE);
        }

        $today = now()->format('Y-m-d');

        return [
            'client_name'      => $name,
            'client_company'   => $organization !== '' ? $organization : $brokerage,
            'client_brokerage' => $brokerage,
            'client_title'     => $defaults['client_title'],
            'agreement_date'   => $today,
            'start_date'       => $today,
            'duration'         => $defaults['duration'],
            'resources'        => $defaults['resources'],
            'hours_per_day'    => config('contracts.hours_per_day')[$engagement] ?? 4,
            'days_per_week'    => $defaults['days_per_week'],
            'plan'             => $engagement === 'Part-time' ? 'Part time' : 'Full time',
            'monthly_charge'   => (float) $lead->lead_value > 0 ? (float) $lead->lead_value : $defaults['monthly_charge'],
            'setup_fee'        => $defaults['setup_fee'],
            'additional_responsibilities' => '',
            'additional_terms'            => '',
        ];
    }

    /**
     * Validated input as stored: numbers as numbers, text trimmed.
     */
    public function normalise(array $input): array
    {
        $fields = [];

        foreach ($this->rules() as $key => $rules) {
            $value = $input[$key] ?? null;

            $fields[$key] = match ($key) {
                'duration', 'resources', 'days_per_week' => (int) $value,
                'hours_per_day', 'monthly_charge', 'setup_fee' => (float) $value,
                'additional_responsibilities', 'additional_terms' => trim(str_replace("\r\n", "\n", (string) $value)),
                default => trim((string) $value),
            };
        }

        return $fields;
    }

    /**
     * Everything the documents print, formatted. Empty values stay empty so
     * the documents can show a blank line in their place.
     */
    public function printable(array $fields): array
    {
        $name = trim((string) $fields['client_name']);
        $company = trim((string) $fields['client_company']);
        $brokerage = trim((string) $fields['client_brokerage']);
        $title = trim((string) $fields['client_title']);

        $start = $this->date($fields['start_date']);
        $months = (int) $fields['duration'];

        // "Moning Associates / eXp Realty", without repeating a company that is the brokerage.
        $affiliation = implode(' / ', array_values(array_unique(array_filter([$company, $brokerage]))));

        return [
            'company'         => config('contracts.company'),
            'client_name'     => $name,
            'client_company'  => $company,
            'client_title'    => $title,
            'nda_party'       => implode(' / ', array_filter([$name, $company])),
            'nda_title'       => trim($title.($affiliation !== '' ? ' ('.$affiliation.')' : '')),
            'agreement_party' => implode(' - ', array_filter([$name, $company])),
            'agreement_date'  => $this->longDate($this->date($fields['agreement_date'])),
            'start_date'      => $this->longDate($start),
            'renewal_date'    => $this->longDate($start?->copy()->addMonthsNoOverflow($months)),
            'duration'        => $this->durationLabel($months),
            'resources'       => (string) (int) $fields['resources'],
            'hours_per_day'   => $this->number($fields['hours_per_day']),
            'days_per_week'   => (string) (int) $fields['days_per_week'],
            'hours_per_week'  => $this->number((float) $fields['hours_per_day'] * (int) $fields['days_per_week']),
            'plan'            => $fields['plan'],
            'monthly_charge'  => $this->money($fields['monthly_charge']),
            'setup_fee'       => $this->money($fields['setup_fee']),
            // One bullet per non-empty line, any leading "-", "*" or "•" dropped.
            'additional_responsibilities' => array_values(array_filter(array_map(
                fn ($line) => trim(preg_replace('/^\s*[-*•]\s*/u', '', $line)),
                explode("\n", (string) ($fields['additional_responsibilities'] ?? ''))
            ), 'strlen')),
            'additional_terms' => trim((string) ($fields['additional_terms'] ?? '')),
        ];
    }

    /**
     * Download name for a document, e.g. "Non-Disclosure Agreement (NDA) - Steven Moning.pdf".
     */
    public function filename(string $document, array $fields): string
    {
        $name = trim(preg_replace('/[^\pL\pN .,&\'()-]+/u', ' ', (string) $fields['client_name']));

        $base = str_replace(':name', $name !== '' ? $name : 'Client', config('contracts.documents.'.$document));

        return preg_replace('/\s+/', ' ', $base).'.pdf';
    }

    public function durationLabel(int $months): string
    {
        if ($months % 12 === 0) {
            $years = intdiv($months, 12);

            return $years.' '.($years === 1 ? 'year' : 'years');
        }

        return $months.' months';
    }

    protected function date(?string $value): ?Carbon
    {
        return $value ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : null;
    }

    /**
     * "7th November, 2026", as the signed contracts have it.
     */
    protected function longDate(?Carbon $date): string
    {
        return $date ? $date->format('jS F, Y') : '';
    }

    /**
     * $600, or $612.50 when there are cents.
     */
    protected function money(mixed $amount): string
    {
        $amount = (float) $amount;

        return '$'.number_format($amount, floor($amount) == $amount ? 0 : 2);
    }

    protected function number(mixed $value): string
    {
        $value = (float) $value;

        return floor($value) == $value ? (string) (int) $value : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
