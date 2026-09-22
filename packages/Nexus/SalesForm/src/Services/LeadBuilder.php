<?php

namespace Nexus\SalesForm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Nexus\SalesForm\Models\ViciDialAgent;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Contracts\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\User\Models\User;

/**
 * Turns the 12Nexus forms into Krayin leads, with the person, organisation,
 * custom field values and the activity trail that makes it clear who did what.
 *
 *  - create()          Sales Meeting Scheduling Form: a new lead with a meeting.
 *  - createNewLead()   Quick "Create Lead" form: an interested client, no meeting yet.
 *  - scheduleForLead() The sales form filled in against an existing lead.
 *  - scheduleMeeting() A meeting on its own: reschedules and follow-ups.
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
        protected LeadFields $fields,
        protected MeetingSlots $slots,
    ) {}

    /**
     * Sales Meeting Scheduling Form: a brand new lead with its discovery meeting.
     */
    public function create(array $input, User $submittedBy): Lead
    {
        // High-water mark so the audit rows this request generates can be told
        // apart from anything already on the record.
        $activityHighWaterMark = (int) DB::table('activities')->max('id');

        $who = $this->identify($input);
        $owner = $this->resolveOwner($input, $submittedBy);

        [$pipeline, $stage] = $this->resolvePipelineAndStage(config('sales_form.stage'));

        $meetingLocal = $this->meetingLocal($input);

        $organization = $this->resolveOrganization($who['brokerage'], $owner->id);

        $person = $this->resolvePerson($input, $who['verified'], $who['name'], $organization->id, $owner->id);

        $willingness = config('sales_form.willingness')[(int) ($input['willingness'] ?? 0)] ?? null;
        $assistantType = $input['assistant_type'] ?? 'None';
        $additional = trim((string) ($input['additional_information'] ?? ''));

        $lead = $this->leadRepository->create([
            'title'                  => $this->title($who),
            'description'            => $this->describe($who, $input, $owner, $meetingLocal),
            'lead_value'             => $this->leadValue($input),
            'status'                 => 1,
            'user_id'                => $owner->id,
            'person_id'              => $person->id,
            'lead_source_id'         => $this->sourceId($who['matched']),
            'lead_type_id'           => $this->leadTypeId(),
            'lead_pipeline_id'       => $pipeline->id,
            'lead_pipeline_stage_id' => $stage->id,
            'expected_close_date'    => $meetingLocal->copy()
                ->addDays((int) config('sales_form.close_date_offset_days'))
                ->format('Y-m-d'),
            'entity_type'            => 'leads',
        ], $this->attributeRepository->findWhere(['entity_type' => 'leads']));

        $this->fields->set($lead->id, [
            'brokerage'           => $who['brokerage'],
            'agent_city'          => $who['city'],
            'agent_state'         => $who['state'],
            'agent_country'       => $who['country'],
            'experience_years'    => $input['experience_years'] ?? 'Not captured',
            'license_details'     => $who['verified']['license_details'] ?? '',
            'appointment_setter'  => $owner->name,
            'form_submitted_at'   => now()->format('Y-m-d H:i:s'),
            'vicidial_lead_code'  => $who['verified']['vendor_lead_code'] ?? '',
            'source_notes'        => $additional !== '' ? $additional : 'No additional information captured on the form.',
            'using_assistant'     => $input['using_assistant'] ?? null,
            'assistant_type'      => $assistantType,
            'willingness_to_hire' => $willingness,
            'engagement_type'     => $input['engagement_type'] ?? null,
        ]);

        $this->recordIntake(
            $lead->id,
            $person->id,
            $this->salesFormIntake($input, $owner, $meetingLocal, 'Sales Meeting Scheduling Form (submitted in-CRM)').$this->verifiedBlock($who),
            $owner
        );

        $this->addMeeting($lead, $person->id, $input, $owner, sprintf('Discovery meeting — %s', $who['name']), 'Discovery call booked from the dialer.');

        $this->pruneCreationAuditNoise($lead->id, $person->id, $activityHighWaterMark);

        return $lead;
    }

    /**
     * Quick "Create Lead" form: the client sounded interested but no meeting was
     * booked on the call, so the lead waits in New Lead until one is.
     */
    public function createNewLead(array $input, User $submittedBy): Lead
    {
        $activityHighWaterMark = (int) DB::table('activities')->max('id');

        $who = $this->identify($input);
        $owner = $this->resolveOwner($input, $submittedBy);

        [$pipeline, $stage] = $this->resolvePipelineAndStage(config('sales_form.new_lead_stage'));

        $organization = $this->resolveOrganization($who['brokerage'], $owner->id);

        $person = $this->resolvePerson($input, $who['verified'], $who['name'], $organization->id, $owner->id);

        $note = trim((string) ($input['note'] ?? ''));

        $lead = $this->leadRepository->create([
            'title'                  => $this->title($who),
            'description'            => implode("\n", array_filter([
                $this->whereabouts($who),
                'Interested on the call; no meeting booked yet (logged by '.$owner->name.').',
                $note !== '' ? "\nNote: ".$note : null,
            ])),
            'lead_value'             => $this->leadValue($input),
            'status'                 => 1,
            'user_id'                => $owner->id,
            'person_id'              => $person->id,
            'lead_source_id'         => $this->sourceId($who['matched']),
            'lead_type_id'           => $this->leadTypeId(),
            'lead_pipeline_id'       => $pipeline->id,
            'lead_pipeline_stage_id' => $stage->id,
            'entity_type'            => 'leads',
        ], $this->attributeRepository->findWhere(['entity_type' => 'leads']));

        $this->fields->set($lead->id, [
            'brokerage'          => $who['brokerage'],
            'agent_city'         => $who['city'],
            'agent_state'        => $who['state'],
            'agent_country'      => $who['country'],
            'license_details'    => $who['verified']['license_details'] ?? '',
            'vicidial_lead_code' => $who['verified']['vendor_lead_code'] ?? '',
            'form_submitted_at'  => now()->format('Y-m-d H:i:s'),
            'source_notes'       => $note,
            'engagement_type'    => $input['engagement_type'] ?? null,
        ]);

        $blank = fn ($value) => ($value === null || $value === '') ? '(blank)' : $value;

        $intake = "SOURCE OF RECORD — Create Lead form (in-CRM)\n"
            .'Submitted: '.now()->format('Y-m-d H:i:s')." UTC\n"
            ."Submitted by: {$submittedBy->name}\n"
            ."Lead owner: {$owner->name} ({$owner->email})\n\n"
            ."--- AS ENTERED ON THE FORM ---\n"
            .'Phone Number: '.$this->lookup->formatPhone($input['phone'])."\n"
            .'Name: '.$blank($input['lead_name'] ?? null)."\n"
            .'Email: '.$blank($input['email'] ?? null)."\n"
            .'Brokerage: '.$blank($input['brokerage'] ?? null)."\n"
            .'City: '.$blank($input['city'] ?? null)."\n"
            .'State: '.$blank($input['state'] ?? null)."\n"
            .'Part-time / Full-time: '.$blank($input['engagement_type'] ?? null)."\n"
            .'Estimated monthly value: '.$this->leadValue($input)."\n"
            .'Additional note: '.$blank($note)."\n\n"
            .$this->verifiedBlock($who);

        $this->recordIntake($lead->id, $person->id, $intake, $owner);

        $this->pruneCreationAuditNoise($lead->id, $person->id, $activityHighWaterMark);

        return $lead;
    }

    /**
     * The full sales form filled in against a lead that already exists, typically
     * one waiting in New Lead that has now agreed to a meeting. Updates the lead
     * in place rather than opening a second one.
     */
    public function scheduleForLead(Lead $lead, array $input, User $submittedBy): Lead
    {
        $who = $this->identify($input);

        $owner = $this->canReassign() && ! empty($input['user_id'])
            ? (User::find($input['user_id']) ?? $lead->user ?? $submittedBy)
            : ($lead->user ?? $submittedBy);

        $meetingLocal = $this->meetingLocal($input);

        $this->updatePerson($lead, $input, $who);

        $willingness = config('sales_form.willingness')[(int) ($input['willingness'] ?? 0)] ?? null;
        $additional = trim((string) ($input['additional_information'] ?? ''));

        $lead->fill([
            'title'               => $this->title($who),
            'lead_value'          => $this->leadValue($input),
            'user_id'             => $owner->id,
            'expected_close_date' => $meetingLocal->copy()
                ->addDays((int) config('sales_form.close_date_offset_days'))
                ->format('Y-m-d'),
            'description'         => trim($lead->description."\n\n".$this->describe($who, $input, $owner, $meetingLocal)),
        ])->save();

        $this->fields->set($lead->id, [
            'brokerage'           => $who['brokerage'],
            'agent_city'          => $who['city'],
            'agent_state'         => $who['state'],
            'agent_country'       => $who['country'],
            'experience_years'    => $input['experience_years'] ?? null,
            'license_details'     => $who['verified']['license_details'] ?? null,
            'appointment_setter'  => $submittedBy->name,
            'form_submitted_at'   => now()->format('Y-m-d H:i:s'),
            'vicidial_lead_code'  => $who['verified']['vendor_lead_code'] ?? null,
            'source_notes'        => $additional,
            'using_assistant'     => $input['using_assistant'] ?? null,
            'assistant_type'      => $input['assistant_type'] ?? null,
            'willingness_to_hire' => $willingness,
            'engagement_type'     => $input['engagement_type'] ?? null,
        ]);

        $this->recordIntake(
            $lead->id,
            $lead->person_id,
            $this->salesFormIntake($input, $owner, $meetingLocal, 'Sales Meeting Scheduling Form (completed on an existing lead)').$this->verifiedBlock($who),
            $submittedBy
        );

        return $this->scheduleMeeting($lead, $input, $submittedBy, 'discovery');
    }

    /**
     * A meeting on its own: the reschedule after a no-show, a moved time, or a
     * follow-up call. Earlier open meetings on the lead are closed off so the
     * card and the Planned tab only show the one that is actually happening.
     *
     * A lead waiting in New Lead or No Show moves (back) to Meeting Scheduled;
     * one further along keeps its stage.
     *
     * @param  string  $kind  discovery | rescheduled | follow-up
     */
    public function scheduleMeeting(Lead $lead, array $input, User $by, string $kind = 'rescheduled'): Lead
    {
        $lead->loadMissing(['person', 'stage', 'pipeline']);

        $previousStage = $lead->stage?->code;

        $superseded = $previousStage === 'no-show' ? 'no-show' : 'rescheduled';

        $open = DB::table('activities')
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('lead_activities.lead_id', $lead->id)
            ->where('activities.type', 'meeting')
            ->where('activities.is_done', 0)
            ->get(['activities.id', 'activities.title']);

        foreach ($open as $meeting) {
            DB::table('activities')->where('id', $meeting->id)->update([
                'is_done'    => 1,
                'title'      => $meeting->title.' ('.$superseded.')',
                'updated_at' => now(),
            ]);
        }

        $name = $lead->person?->name ?: 'Lead';

        $title = match ($kind) {
            'discovery' => sprintf('Discovery meeting — %s', $name),
            'follow-up' => sprintf('Follow-up meeting — %s', $name),
            default     => sprintf('Rescheduled meeting — %s', $name),
        };

        $context = match ($kind) {
            'discovery' => 'Discovery call booked on an existing lead.',
            'follow-up' => 'Follow-up meeting booked.',
            default     => $previousStage === 'no-show'
                ? 'Rescheduled after the client did not show up.'
                : 'Meeting moved to a new time.',
        };

        $this->addMeeting($lead, $lead->person_id, $input, $by, $title, $context);

        $this->fields->set($lead->id, [
            'meeting_appeared' => 'Pending',
        ]);

        if (in_array($previousStage, ['new', 'no-show'], true)) {
            $this->moveToStage($lead, config('sales_form.stage'));
        }

        return $lead->refresh();
    }

    /**
     * Move a lead to another stage of its own pipeline by code, through the
     * model so Krayin logs the stage change in the lead's history.
     */
    public function moveToStage(Lead $lead, string $code): void
    {
        $stage = $lead->pipeline->stages()->where('code', $code)->first();

        if (! $stage || $stage->id === $lead->lead_pipeline_stage_id) {
            return;
        }

        $lead->fill([
            'lead_pipeline_stage_id' => $stage->id,
            'closed_at'              => in_array($code, ['won', 'lost'], true) ? now() : null,
        ])->save();
    }

    public function canReassign(): bool
    {
        return bouncer()->hasPermission('settings.user.users');
    }

    /**
     * Who the phone number belongs to, merging what the rep typed with the
     * verified ViciDial record. Verified values fill only what was left blank,
     * so a deliberate correction is never overwritten.
     */
    protected function identify(array $input): array
    {
        $agent = $this->lookup->lookup($input['phone'] ?? '');
        $verified = $agent['agent'];

        $state = $this->pick($input['state'] ?? null, $verified['state'] ?? null);

        return [
            'matched'    => $agent['found'],
            'verified'   => $verified,
            'name'       => $this->pick($input['lead_name'] ?? null, $verified['full_name'] ?? null),
            'city'       => $this->pick($input['city'] ?? null, $verified['city'] ?? null),
            'state'      => $state,
            'state_code' => $verified['state_code'] ?? $this->lookup->stateCode($state),
            'brokerage'  => $this->pick($input['brokerage'] ?? null, config('sales_form.default_brokerage')),
            'country'    => $verified['country'] ?? '',
        ];
    }

    protected function title(array $who): string
    {
        return sprintf(
            'VA Opportunity — %s (%s, %s)',
            $who['name'],
            $who['city'] !== '' ? $who['city'] : 'Unknown',
            $who['state_code'] !== '' ? $who['state_code'] : '—'
        );
    }

    protected function whereabouts(array $who): string
    {
        return sprintf(
            'Real estate agent at %s — %s%s%s.',
            $who['brokerage'],
            $who['city'] !== '' ? $who['city'] : 'city unknown',
            $who['state'] !== '' ? ', '.$who['state'] : '',
            $who['country'] !== '' ? ', '.$who['country'] : ''
        );
    }

    protected function describe(array $who, array $input, User $owner, Carbon $meetingLocal): string
    {
        $willingness = config('sales_form.willingness')[(int) ($input['willingness'] ?? 0)] ?? null;
        $assistantType = $input['assistant_type'] ?? 'None';
        $additional = trim((string) ($input['additional_information'] ?? ''));

        return implode("\n", array_filter([
            $this->whereabouts($who),
            ! empty($input['experience_years']) ? 'Experience in real estate: '.$input['experience_years'] : null,
            'Currently using an assistant: '.($input['using_assistant'] ?? 'Not captured')
                .($assistantType && $assistantType !== 'None' ? ' ('.$assistantType.')' : ''),
            $willingness ? 'Willingness to hire a VA: '.$willingness : null,
            ! empty($input['engagement_type']) ? 'Looking for: '.$input['engagement_type'].' VA' : null,
            sprintf(
                'Discovery meeting: %s at %s %s (set by %s).',
                $meetingLocal->format('Y-m-d'),
                $meetingLocal->format('g:i A'),
                $input['timezone'],
                $owner->name
            ),
            $additional !== '' ? "\nDiscovery notes: ".$additional : null,
        ]));
    }

    /**
     * The retainer floor applies whatever was typed, and is the default.
     */
    protected function leadValue(array $input): float
    {
        $minimum = (float) config('sales_form.minimum_lead_value');

        $value = (float) ($input['lead_value'] ?? 0);

        return max($minimum, $value);
    }

    /**
     * Only an admin may log a lead on someone else's behalf; a sales executive is
     * always the owner of what they submit.
     */
    protected function resolveOwner(array $input, User $submittedBy): User
    {
        if (empty($input['user_id']) || ! $this->canReassign()) {
            return $submittedBy;
        }

        return User::find($input['user_id']) ?? $submittedBy;
    }

    protected function resolvePipelineAndStage(string $stageCode): array
    {
        $pipeline = $this->pipelineRepository->findOneWhere(['name' => config('sales_form.pipeline')])
            ?? $this->pipelineRepository->getDefaultPipeline();

        $stage = $pipeline->stages()->where('code', $stageCode)->first()
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
                'value' => '+1'.ViciDialAgent::normalizePhone($input['phone']),
                'label' => 'work',
            ]],
        ]);
    }

    /**
     * Bring the lead's contact up to date with what the rep confirmed on the
     * call: the name as corrected, and any email or number not already on file.
     */
    protected function updatePerson(Lead $lead, array $input, array $who): void
    {
        $person = $lead->person;

        if (! $person) {
            return;
        }

        $emails = collect($person->emails ?? [])->filter(fn ($row) => ! empty($row['value']));
        $known = $emails->pluck('value')->map(fn ($value) => strtolower($value))->all();

        foreach ([$input['email'] ?? null, $who['verified']['email'] ?? null] as $email) {
            $email = strtolower(trim((string) $email));

            if ($email !== '' && ! in_array($email, $known, true)) {
                $emails->push(['value' => $email, 'label' => $emails->isEmpty() ? 'work' : 'personal']);
                $known[] = $email;
            }
        }

        $numbers = collect($person->contact_numbers ?? [])->filter(fn ($row) => ! empty($row['value']));
        $phone = '+1'.ViciDialAgent::normalizePhone($input['phone'] ?? '');

        $hasPhone = $numbers->contains(
            fn ($row) => ViciDialAgent::normalizePhone($row['value']) === ViciDialAgent::normalizePhone($phone)
        );

        if (strlen($phone) === 12 && ! $hasPhone) {
            $numbers->push(['value' => $phone, 'label' => $numbers->isEmpty() ? 'work' : 'mobile']);
        }

        $person->fill([
            'name'            => $who['name'] !== '' ? $who['name'] : $person->name,
            'emails'          => $emails->values()->all(),
            'contact_numbers' => $numbers->values()->all(),
        ])->save();
    }

    protected function salesFormIntake(array $input, User $owner, Carbon $meetingLocal, string $source): string
    {
        $blank = fn ($value) => ($value === null || $value === '') ? '(blank)' : $value;

        return "SOURCE OF RECORD — {$source}\n"
            .'Submitted: '.now()->format('Y-m-d H:i:s')." UTC\n"
            ."Submitted by / appointment set by: {$owner->name}\n"
            ."Lead owner: {$owner->name} ({$owner->email})\n\n"
            ."--- AS ENTERED ON THE FORM ---\n"
            ."Sales Executive: {$owner->name}\n"
            .'Lead Name: '.$blank($input['lead_name'] ?? null)."\n"
            .'Experience in Real Estate (in years): '.$blank($input['experience_years'] ?? null)."\n"
            .'Currently using an Assistant?: '.$blank($input['using_assistant'] ?? null)."\n"
            .'If using assistant: '.$blank(($input['assistant_type'] ?? 'None') !== 'None' ? ($input['assistant_type'] ?? null) : null)."\n"
            .'Willingness to Hire a VA: '.$blank($input['willingness'] ?? null)
                .' ('.($input['willingness'] ?? '?')." on a 1-3 scale)\n"
            .'Part-time / Full-time: '.$blank($input['engagement_type'] ?? null)."\n"
            .'Estimated monthly value: '.$this->leadValue($input)."\n"
            .'Brokerage: '.$blank($input['brokerage'] ?? null)."\n"
            .'City: '.$blank($input['city'] ?? null)."\n"
            .'State: '.$blank($input['state'] ?? null)."\n"
            .'Phone Number: '.$this->lookup->formatPhone($input['phone'])."\n"
            .'Email Address: '.$blank($input['email'] ?? null)."\n"
            .'Meeting Date: '.$meetingLocal->format('Y-m-d')."\n"
            .'Time: '.$meetingLocal->format('g:i A')."\n"
            ."Timezone: {$input['timezone']}\n"
            .'Additional Information: '.$blank($input['additional_information'] ?? null)."\n"
            ."Meeting Appeared?: (not yet known — set to 'Pending')\n\n";
    }

    protected function verifiedBlock(array $who): string
    {
        $verified = $who['verified'];

        if ($who['matched'] && $verified) {
            return "--- VERIFIED AGAINST THE ViciDial eXp AGENT LIST (matched on phone) ---\n"
                ."Verified name: {$verified['full_name']}\n"
                .'Verified email: '.($verified['email'] ?: '(none on file)')."\n"
                .'Location on file: '.trim(($verified['city'] ?? '').', '.($verified['state'] ?? ''), ', ')
                    .' ('.($verified['state_code'] ?? '').'), '.($verified['country'] ?? '')."\n"
                .'License / specialties: '.($verified['license_details'] ?: '(none on file)')."\n"
                ."ViciDial vendor_lead_code: {$verified['vendor_lead_code']}\n";
        }

        return "--- ViciDial LOOKUP ---\n"
            ."No matching agent for this phone number in the ViciDial eXp list.\n"
            ."Contact details above were entered manually by the rep.\n";
    }

    protected function recordIntake(int $leadId, ?int $personId, string $comment, User $by): void
    {
        $note = $this->activityRepository->create([
            'type'    => 'note',
            'comment' => $comment,
            'user_id' => $by->id,
            'is_done' => 1,
        ]);

        $this->attach($note->id, $leadId, $personId);
    }

    /**
     * The meeting activity plus the lead fields that describe it. Stored in UTC,
     * with the agent's own wall-clock time and zone kept alongside.
     */
    protected function addMeeting(Lead $lead, ?int $personId, array $input, User $by, string $title, string $context): void
    {
        $meetingLocal = $this->meetingLocal($input);
        $meetingUtc = $this->toUtc($meetingLocal, $input['timezone']);
        $meetingEnd = $meetingUtc->copy()->addMinutes((int) config('sales_form.notify.meeting_minutes'));
        $additional = trim((string) ($input['additional_information'] ?? ''));

        // Re-checked here, inside the caller's lock and transaction, because the
        // form's own check cannot see a booking made a moment after it ran.
        $this->slots->assertFree($meetingUtc, $meetingEnd, config('sales_form.timezones')[$input['timezone']] ?? null);

        $meeting = $this->activityRepository->create([
            'title'         => $title,
            'type'          => 'meeting',
            'comment'       => sprintf(
                "%s\nAgent local time: %s %s (%s).\nStored in UTC: %s.\nBooked by: %s.%s",
                $context,
                $meetingLocal->format('Y-m-d'),
                $meetingLocal->format('g:i A'),
                $input['timezone'],
                $meetingUtc->format('Y-m-d H:i:s'),
                $by->name,
                $additional !== '' ? "\n\nPre-call context: ".$additional : ''
            ),
            'schedule_from' => $meetingUtc->format('Y-m-d H:i:s'),
            'schedule_to'   => $meetingEnd->format('Y-m-d H:i:s'),
            'user_id'       => $lead->user_id ?? $by->id,
            'is_done'       => 0,
            'location'      => config('sales_form.notify.meeting_location'),
        ]);

        $this->attach($meeting->id, $lead->id, $personId);

        $this->fields->set($lead->id, [
            'meeting_at'       => $meetingLocal->format('Y-m-d H:i:s'),
            'meeting_timezone' => $input['timezone'],
        ]);
    }

    protected function attach(int $activityId, int $leadId, ?int $personId): void
    {
        DB::table('lead_activities')->insert(['lead_id' => $leadId, 'activity_id' => $activityId]);

        if ($personId) {
            DB::table('person_activities')->insert(['person_id' => $personId, 'activity_id' => $activityId]);
        }
    }

    /**
     * Krayin's attribute listener logs an "Updated <field>" system activity for
     * every field written, so creating one lead buries the intake note under ~20
     * meaningless rows. Drop the ones this request just produced; "Created" stays.
     *
     * Scoped by both the id high-water mark and this lead's own pivot rows, so a
     * concurrent request's history and any genuine earlier history on a repeat
     * person are never touched.
     */
    protected function pruneCreationAuditNoise(int $leadId, int $personId, int $highWaterMark): void
    {
        $ids = DB::table('activities')
            ->where('id', '>', $highWaterMark)
            ->where('type', 'system')
            ->where('title', 'like', 'Updated %')
            ->where(function ($query) use ($leadId, $personId) {
                $query->whereIn('id', function ($sub) use ($leadId) {
                    $sub->select('activity_id')->from('lead_activities')->where('lead_id', $leadId);
                })->orWhereIn('id', function ($sub) use ($personId) {
                    $sub->select('activity_id')->from('person_activities')->where('person_id', $personId);
                });
            })
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('lead_activities')->whereIn('activity_id', $ids)->delete();
        DB::table('person_activities')->whereIn('activity_id', $ids)->delete();
        DB::table('activity_participants')->whereIn('activity_id', $ids)->delete();
        DB::table('activities')->whereIn('id', $ids)->delete();
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
}
