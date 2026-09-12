<?php

namespace Nexus\SalesForm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\User\Models\User;

/**
 * Turns one Sales Meeting Scheduling Form submission into a Krayin lead, with the
 * person, organisation, custom field values and the activity trail that makes it
 * clear who did what.
 */
class LeadBuilder
{
    public function __construct(
        protected LeadRepository $leadRepository,
        protected PersonRepository $personRepository,
        protected OrganizationRepository $organizationRepository,
        protected PipelineRepository $pipelineRepository,
        protected ActivityRepository $activityRepository,
        protected AttributeRepository $attributeRepository,
        protected AgentLookupService $lookup,
    ) {}

    public function create(array $input, User $submittedBy): \Webkul\Lead\Contracts\Lead
    {
        $agent = $this->lookup->lookup($input['phone'] ?? '');
        $matched = $agent['found'];
        $verified = $agent['agent'];

        $owner = $this->resolveOwner($input, $submittedBy);

        // Verified ViciDial values win over what was typed, but only where the rep
        // left the field blank — a deliberate correction must never be overwritten.
        $name = $this->pick($input['lead_name'] ?? null, $verified['full_name'] ?? null);
        $city = $this->pick($input['city'] ?? null, $verified['city'] ?? null);
        $stateInput = trim((string) ($input['state'] ?? ''));
        $state = $stateInput !== '' ? $stateInput : ($verified['state'] ?? '');
        $stateCode = $verified['state_code'] ?? $this->abbreviate($state);
        $brokerage = $this->pick($input['brokerage'] ?? null, config('sales_form.default_brokerage'));
        $country = $verified['country'] ?? '';

        [$pipeline, $stage] = $this->resolvePipelineAndStage();

        $meetingLocal = $this->meetingLocal($input);
        $meetingUtc = $this->toUtc($meetingLocal, $input['timezone']);

        $organization = $this->resolveOrganization($brokerage, $owner->id);

        $person = $this->resolvePerson($input, $verified, $name, $organization->id, $owner->id);

        $sourceId = $this->sourceId($matched);
        $typeId = $this->leadTypeId();

        $title = sprintf(
            'VA Opportunity — %s (%s, %s)',
            $name,
            $city !== '' ? $city : 'Unknown',
            $stateCode !== '' ? $stateCode : '—'
        );

        $willingness = config('sales_form.willingness')[(int) ($input['willingness'] ?? 0)] ?? null;
        $assistantType = $input['assistant_type'] ?? 'None';
        $additional = trim((string) ($input['additional_information'] ?? ''));

        $description = implode("\n", array_filter([
            sprintf('Real estate agent at %s — %s, %s%s.', $brokerage, $city !== '' ? $city : 'city unknown', $state, $country !== '' ? ', '.$country : ''),
            ! empty($input['experience_years']) ? 'Experience in real estate: '.$input['experience_years'] : null,
            'Currently using an assistant: '.($input['using_assistant'] ?? 'Not captured')
                .($assistantType !== 'None' ? ' ('.$assistantType.')' : ''),
            $willingness ? 'Willingness to hire a VA: '.$willingness : null,
            sprintf(
                'Discovery meeting: %s at %s %s (set by %s).',
                $meetingLocal->format('Y-m-d'),
                $meetingLocal->format('g:i A'),
                $input['timezone'],
                $owner->name
            ),
            $additional !== '' ? "\nDiscovery notes: ".$additional : null,
        ]));

        $attributes = $this->attributeRepository->findWhere(['entity_type' => 'leads']);

        $lead = $this->leadRepository->create([
            'title'                  => $title,
            'description'            => $description,
            'lead_value'             => config('sales_form.placeholder_lead_value'),
            'status'                 => 1,
            'user_id'                => $owner->id,
            'person_id'              => $person->id,
            'lead_source_id'         => $sourceId,
            'lead_type_id'           => $typeId,
            'lead_pipeline_id'       => $pipeline->id,
            'lead_pipeline_stage_id' => $stage->id,
            'expected_close_date'    => $meetingLocal->copy()
                ->addDays((int) config('sales_form.close_date_offset_days'))
                ->format('Y-m-d'),
            'entity_type'            => 'leads',

            'brokerage'          => $brokerage,
            'agent_city'         => $city,
            'agent_state'        => $state,
            'agent_country'      => $country,
            'experience_years'   => $input['experience_years'] ?? 'Not captured',
            'license_details'    => $verified['license_details'] ?? '',
            'appointment_setter' => $owner->name,
            'form_submitted_at'  => now()->format('Y-m-d H:i:s'),
            'meeting_at'         => $meetingLocal->format('Y-m-d H:i:s'),
            'vicidial_lead_code' => $verified['vendor_lead_code'] ?? '',
            'source_notes'       => $additional !== '' ? $additional : 'No additional information captured on the form.',
        ], $attributes);

        /**
         * Select-type attributes store the `attribute_options.id`, not the label,
         * so they have to be written after the repository call.
         */
        $this->setSelectValues($lead->id, [
            'using_assistant'     => $input['using_assistant'] ?? null,
            'assistant_type'      => $assistantType,
            'willingness_to_hire' => $willingness,
            'meeting_timezone'    => $input['timezone'],
            'meeting_appeared'    => 'Pending',
        ]);

        $this->recordActivities($lead->id, $person->id, $input, $verified, $matched, $owner, $meetingLocal, $meetingUtc);

        return $lead;
    }

    /**
     * Only an admin may log a lead on someone else's behalf; a sales executive is
     * always the owner of what they submit.
     */
    protected function resolveOwner(array $input, User $submittedBy): User
    {
        if (empty($input['user_id'])) {
            return $submittedBy;
        }

        if (! bouncer()->hasPermission('settings.user.users')) {
            return $submittedBy;
        }

        return User::find($input['user_id']) ?? $submittedBy;
    }

    protected function resolvePipelineAndStage(): array
    {
        $pipeline = $this->pipelineRepository->findOneWhere(['name' => config('sales_form.pipeline')])
            ?? $this->pipelineRepository->getDefaultPipeline();

        $stage = $pipeline->stages()->where('code', config('sales_form.stage'))->first()
            ?? $pipeline->stages()->orderBy('sort_order')->first();

        return [$pipeline, $stage];
    }

    protected function resolveOrganization(string $brokerage, int $userId)
    {
        $organization = $this->organizationRepository->findOneWhere(['name' => $brokerage]);

        if ($organization) {
            return $organization;
        }

        return $this->organizationRepository->create([
            'entity_type' => 'organizations',
            'name'        => $brokerage,
            'address'     => ['address' => '', 'country' => '', 'state' => '', 'city' => '', 'postcode' => ''],
            'user_id'     => $userId,
        ]);
    }

    protected function resolvePerson(array $input, ?array $verified, string $name, int $organizationId, int $userId)
    {
        $emails = array_values(array_unique(array_filter([
            strtolower(trim((string) ($input['email'] ?? ''))),
            strtolower(trim((string) ($verified['email'] ?? ''))),
        ])));

        $existing = $this->personRepository->findOneWhere([
            'name'            => $name,
            'organization_id' => $organizationId,
        ]);

        if ($existing) {
            return $existing;
        }

        return $this->personRepository->create([
            'entity_type'     => 'persons',
            'name'            => $name,
            'job_title'       => 'Real Estate Agent',
            'organization_id' => $organizationId,
            'user_id'         => $userId,
            'emails'          => array_map(
                fn ($email, $index) => ['value' => $email, 'label' => $index === 0 ? 'work' : 'personal'],
                $emails,
                array_keys($emails)
            ),
            'contact_numbers' => [[
                'value' => '+1'.\Nexus\SalesForm\Models\ViciDialAgent::normalizePhone($input['phone']),
                'label' => 'work',
            ]],
        ]);
    }

    /**
     * Write select-type custom field values by resolving each label to its option id.
     */
    protected function setSelectValues(int $leadId, array $values): void
    {
        foreach ($values as $code => $label) {
            if ($label === null || $label === '') {
                continue;
            }

            $attribute = DB::table('attributes')
                ->where('entity_type', 'leads')
                ->where('code', $code)
                ->first();

            if (! $attribute) {
                continue;
            }

            $optionId = DB::table('attribute_options')
                ->where('attribute_id', $attribute->id)
                ->where('name', $label)
                ->value('id');

            if (! $optionId) {
                continue;
            }

            $existing = DB::table('attribute_values')
                ->where('entity_type', 'leads')
                ->where('entity_id', $leadId)
                ->where('attribute_id', $attribute->id)
                ->first();

            if ($existing) {
                DB::table('attribute_values')
                    ->where('id', $existing->id)
                    ->update(['integer_value' => $optionId, 'text_value' => null]);
            } else {
                DB::table('attribute_values')->insert([
                    'entity_type'   => 'leads',
                    'entity_id'     => $leadId,
                    'attribute_id'  => $attribute->id,
                    'integer_value' => $optionId,
                    'unique_id'     => $leadId.'|'.$attribute->id,
                ]);
            }
        }
    }

    /**
     * The intake note plus the scheduled meeting. Krayin's own audit listener also
     * fires on create; these two are the ones a human reads.
     */
    protected function recordActivities(
        int $leadId,
        int $personId,
        array $input,
        ?array $verified,
        bool $matched,
        User $owner,
        Carbon $meetingLocal,
        Carbon $meetingUtc
    ): void {
        $blank = fn ($value) => ($value === null || $value === '') ? '(blank)' : $value;

        $intake = "SOURCE OF RECORD — Sales Meeting Scheduling Form (submitted in-CRM)\n"
            ."Submitted: ".now()->format('Y-m-d H:i:s')." UTC\n"
            ."Submitted by / appointment set by: {$owner->name}\n"
            ."Lead owner: {$owner->name} ({$owner->email})\n\n"
            ."--- AS ENTERED ON THE FORM ---\n"
            ."Sales Executive: {$owner->name}\n"
            ."Lead Name: ".$blank($input['lead_name'] ?? null)."\n"
            ."Experience in Real Estate (in years): ".$blank($input['experience_years'] ?? null)."\n"
            ."Currently using an Assistant?: ".$blank($input['using_assistant'] ?? null)."\n"
            ."If using assistant: ".$blank(($input['assistant_type'] ?? 'None') !== 'None' ? $input['assistant_type'] : null)."\n"
            ."Willingness to Hire a VA: ".$blank($input['willingness'] ?? null)
                ." (".($input['willingness'] ?? '?')." on a 1-3 scale)\n"
            ."Brokerage: ".$blank($input['brokerage'] ?? null)."\n"
            ."City: ".$blank($input['city'] ?? null)."\n"
            ."State: ".$blank($input['state'] ?? null)."\n"
            ."Phone Number: ".$this->lookup->formatPhone($input['phone'])."\n"
            ."Email Address: ".$blank($input['email'] ?? null)."\n"
            ."Meeting Date: ".$meetingLocal->format('Y-m-d')."\n"
            ."Time: ".$meetingLocal->format('g:i A')."\n"
            ."Timezone: {$input['timezone']}\n"
            ."Additional Information: ".$blank($input['additional_information'] ?? null)."\n"
            ."Meeting Appeared?: (not yet known — set to 'Pending')\n\n";

        if ($matched && $verified) {
            $intake .= "--- VERIFIED AGAINST THE ViciDial eXp AGENT LIST (matched on phone) ---\n"
                ."Verified name: {$verified['full_name']}\n"
                ."Verified email: ".($verified['email'] ?: '(none on file)')."\n"
                ."Location on file: ".trim(($verified['city'] ?? '').', '.($verified['state'] ?? ''), ', ')
                    ." (".($verified['state_code'] ?? '')."), ".($verified['country'] ?? '')."\n"
                ."License / specialties: ".($verified['license_details'] ?: '(none on file)')."\n"
                ."ViciDial vendor_lead_code: {$verified['vendor_lead_code']}\n";
        } else {
            $intake .= "--- ViciDial LOOKUP ---\n"
                ."No matching agent for this phone number in the ViciDial eXp list.\n"
                ."Name, brokerage, city, state and email above were entered manually by the rep.\n";
        }

        $note = $this->activityRepository->create([
            'type'    => 'note',
            'comment' => $intake,
            'user_id' => $owner->id,
            'is_done' => 1,
        ]);

        DB::table('lead_activities')->insert(['lead_id' => $leadId, 'activity_id' => $note->id]);
        DB::table('person_activities')->insert(['person_id' => $personId, 'activity_id' => $note->id]);

        $meeting = $this->activityRepository->create([
            'title'         => sprintf('Discovery meeting — %s', $input['lead_name'] ?? 'Lead'),
            'type'          => 'meeting',
            'comment'       => sprintf(
                "Discovery call booked from the dialer.\nAgent local time: %s %s (%s).\nStored in UTC: %s.\nAppointment set by: %s.%s",
                $meetingLocal->format('Y-m-d'),
                $meetingLocal->format('g:i A'),
                $input['timezone'],
                $meetingUtc->format('Y-m-d H:i:s'),
                $owner->name,
                ! empty($input['additional_information']) ? "\n\nPre-call context: ".$input['additional_information'] : ''
            ),
            'schedule_from' => $meetingUtc->format('Y-m-d H:i:s'),
            'schedule_to'   => $meetingUtc->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'user_id'       => $owner->id,
            'is_done'       => 0,
            'location'      => 'Online / phone',
        ]);

        DB::table('lead_activities')->insert(['lead_id' => $leadId, 'activity_id' => $meeting->id]);
        DB::table('person_activities')->insert(['person_id' => $personId, 'activity_id' => $meeting->id]);
    }

    protected function meetingLocal(array $input): Carbon
    {
        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $input['meeting_date'].' '.$input['meeting_time']
        );
    }

    protected function toUtc(Carbon $local, string $timezoneLabel): Carbon
    {
        $zone = config('sales_form.timezones')[$timezoneLabel] ?? 'UTC';

        return Carbon::createFromFormat('Y-m-d H:i:s', $local->format('Y-m-d H:i:s'), $zone)
            ->setTimezone('UTC');
    }

    protected function sourceId(bool $matched): ?int
    {
        $name = $matched
            ? config('sales_form.source_matched')
            : config('sales_form.source_unmatched');

        return DB::table('lead_sources')->where('name', $name)->value('id')
            ?? DB::table('lead_sources')->orderBy('id')->value('id');
    }

    protected function leadTypeId(): ?int
    {
        return DB::table('lead_types')->where('name', config('sales_form.lead_type'))->value('id')
            ?? DB::table('lead_types')->orderBy('id')->value('id');
    }

    protected function pick(?string $primary, ?string $fallback): string
    {
        $primary = trim((string) $primary);

        return $primary !== '' ? $primary : trim((string) $fallback);
    }

    protected function abbreviate(string $state): string
    {
        $state = trim($state);

        return strlen($state) <= 3 ? strtoupper($state) : strtoupper(substr($state, 0, 2));
    }
}
