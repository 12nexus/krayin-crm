<?php

namespace Nexus\SalesForm\Services;

use Illuminate\Support\Facades\DB;
use Nexus\SalesForm\Models\ViciDialAgent;

class AgentLookupService
{
    /**
     * US/Canada state and province codes to full names, so the form shows the rep
     * something readable rather than a two-letter code.
     */
    protected array $states = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
        'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
        'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
        'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
        'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
        'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
        'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
        'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba',
        'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia',
        'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario',
        'PE' => 'Prince Edward Island', 'QC' => 'Quebec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon',
    ];

    /**
     * Resolve a phone number to a verified agent record.
     *
     * Returns a `found => false` payload rather than null so the caller always has
     * the normalized number to echo back, and the form can fall back to manual entry.
     */
    public function lookup(?string $phone): array
    {
        $normalized = ViciDialAgent::normalizePhone($phone);

        if (strlen($normalized) < 10) {
            return [
                'found'   => false,
                'phone'   => $normalized,
                'reason'  => 'incomplete',
                'agent'   => null,
            ];
        }

        $agent = ViciDialAgent::query()
            ->where('phone', $normalized)
            ->orderBy('id')
            ->first();

        if (! $agent) {
            // Some agents list the number only as a secondary contact.
            $agent = ViciDialAgent::query()
                ->where('alt_phone', $normalized)
                ->orderBy('id')
                ->first();
        }

        $existing = $this->existingLeads($normalized);

        if (! $agent) {
            return [
                'found'          => false,
                'phone'          => $normalized,
                'reason'         => 'not_found',
                'agent'          => null,
                'existing_leads' => $existing,
            ];
        }

        return [
            'found'          => true,
            'phone'          => $normalized,
            'agent'          => $this->present($agent),
            'existing_leads' => $existing,
        ];
    }

    /**
     * Shape an agent row for the form: only what the rep needs to see or what
     * prefills a field.
     */
    public function present(ViciDialAgent $agent): array
    {
        $stateCode = trim((string) ($agent->state ?: $agent->province));

        return [
            'id'               => $agent->id,
            'full_name'        => $agent->full_name,
            'first_name'       => $agent->first_name,
            'last_name'        => $agent->last_name,
            'email'            => $agent->email,
            'phone'            => $agent->phone,
            'phone_display'    => $this->formatPhone($agent->phone),
            'alt_phone'        => $agent->alt_phone,
            'city'             => $agent->city,
            'state_code'       => $stateCode,
            'state'            => $this->states[strtoupper($stateCode)] ?? $stateCode,
            'country'          => $this->country($agent->country_code),
            'postal_code'      => $agent->postal_code,
            'license_details'  => $agent->comments,
            'vendor_lead_code' => $agent->vendor_lead_code,
            'brokerage'        => config('sales_form.default_brokerage'),
        ];
    }

    /**
     * Leads already on file for this number, so the rep is told before they open
     * a second one against someone who is already in the pipeline.
     *
     * Contact numbers are stored as JSON like [{"value":"+13058151136",...}], so
     * matching the normalised digits as a substring is enough to find the person.
     */
    public function existingLeads(?string $phone): array
    {
        $normalized = ViciDialAgent::normalizePhone($phone);

        if (strlen($normalized) < 10) {
            return [];
        }

        $personIds = DB::table('persons')
            ->where('contact_numbers', 'like', '%'.$normalized.'%')
            ->pluck('id');

        if ($personIds->isEmpty()) {
            return [];
        }

        $base = rtrim((string) config('app.url'), '/').'/'.config('app.admin_path').'/leads/view/';

        return DB::table('leads')
            ->leftJoin('users', 'users.id', '=', 'leads.user_id')
            ->leftJoin('lead_pipeline_stages as stages', 'stages.id', '=', 'leads.lead_pipeline_stage_id')
            ->whereIn('leads.person_id', $personIds)
            ->orderByDesc('leads.id')
            ->limit(5)
            ->get([
                'leads.id',
                'leads.title',
                'leads.created_at',
                'users.name as owner',
                'stages.name as stage',
                'stages.code as stage_code',
            ])
            ->map(fn ($lead) => [
                'id'         => $lead->id,
                'title'      => $lead->title,
                'owner'      => $lead->owner ?: 'Unassigned',
                'stage'      => $lead->stage ?: '',
                'is_closed'  => in_array($lead->stage_code, ['won', 'lost'], true),
                'created_at' => $lead->created_at ? substr((string) $lead->created_at, 0, 10) : '',
                'url'        => $base.$lead->id,
            ])
            ->all();
    }

    /**
     * Resolve whatever the rep typed in the State field to its two-letter code,
     * accepting either the code or the full name. Falls back to the input itself
     * so an unrecognised region still produces something readable.
     */
    public function stateCode(?string $state): string
    {
        $state = trim((string) $state);

        if ($state === '') {
            return '';
        }

        if (isset($this->states[strtoupper($state)])) {
            return strtoupper($state);
        }

        $byName = array_change_key_case(array_flip($this->states), CASE_LOWER);

        return $byName[strtolower($state)] ?? strtoupper($state);
    }

    public function formatPhone(?string $phone): string
    {
        $digits = ViciDialAgent::normalizePhone($phone);

        if (strlen($digits) !== 10) {
            return (string) $phone;
        }

        return sprintf('+1 (%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
    }

    protected function country(?string $code): string
    {
        return match (strtoupper((string) $code)) {
            'CA' => 'Canada',
            'US' => 'United States',
            ''   => '',
            default => (string) $code,
        };
    }
}
