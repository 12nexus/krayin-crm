<?php

namespace Nexus\SalesForm\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Nexus\SalesForm\Http\Requests\NewLeadRequest;
use Nexus\SalesForm\Http\Requests\SalesFormRequest;
use Nexus\SalesForm\Listeners\LeadCreated;
use Nexus\SalesForm\Services\AgentLookupService;
use Nexus\SalesForm\Services\CalendarFeed;
use Nexus\SalesForm\Services\CalendarLink;
use Nexus\SalesForm\Services\LeadBuilder;
use Nexus\SalesForm\Services\LeadDigest;
use Nexus\SalesForm\Services\LeadFields;
use Nexus\SalesForm\Services\MeetingSlots;
use Nexus\SalesForm\Support\LeadAccess;
use Webkul\User\Repositories\UserRepository;

class SalesFormController extends Controller
{
    public function __construct(
        protected AgentLookupService $lookup,
        protected LeadBuilder $leadBuilder,
        protected UserRepository $userRepository,
        protected LeadDigest $leadDigest,
        protected CalendarLink $calendar,
        protected MeetingSlots $slots,
        protected CalendarFeed $calendarFeed,
    ) {}

    /**
     * The in-CRM replacement for the Google form.
     */
    public function index()
    {
        return view('sales_form::index', $this->formData());
    }

    /**
     * The sales form filled in against a lead that already exists, typically one
     * waiting in New Lead that has now agreed to a meeting.
     */
    public function schedule(int $id)
    {
        abort_unless(bouncer()->hasPermission('leads.edit'), 401);

        $lead = LeadAccess::findOrFail($id);

        $fields = app(LeadFields::class)->get($lead->id);
        $person = $lead->person;

        $willingness = array_search($fields['willingness_to_hire'] ?? null, config('sales_form.willingness'), true);

        return view('sales_form::index', array_merge($this->formData(), [
            'lead'    => $lead,
            'prefill' => [
                'phone'            => collect($person?->contact_numbers ?? [])->pluck('value')->filter()->first() ?? '',
                'user_id'          => $lead->user_id,
                'lead_name'        => $person?->name ?? '',
                'email'            => collect($person?->emails ?? [])->pluck('value')->filter()->first() ?? '',
                'brokerage'        => $fields['brokerage'] ?? '',
                'experience_years' => $fields['experience_years'] ?? '',
                'city'             => $fields['agent_city'] ?? '',
                'state'            => $fields['agent_state'] ?? '',
                'using_assistant'  => $fields['using_assistant'] ?? 'No',
                'assistant_type'   => ($fields['assistant_type'] ?? 'None') === 'None' ? '' : $fields['assistant_type'],
                'willingness'      => $willingness ?: 2,
                'engagement_type'  => $fields['engagement_type'] ?? '',
                'lead_value'       => (float) $lead->lead_value,
                'additional_information' => '',
            ],
        ]));
    }

    public function storeSchedule(SalesFormRequest $request, int $id): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('leads.edit'), 401);

        $lead = LeadAccess::findOrFail($id);

        $user = auth()->guard('user')->user();

        try {
            $lead = $this->slots->locked(fn () => DB::transaction(
                fn () => $this->leadBuilder->scheduleForLead($lead, $request->validated(), $user)
            ));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Scheduling a meeting on an existing lead failed', [
                'user_id' => $user->id,
                'lead_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', trans('sales_form::app.schedule.failed'));
        }

        app(LeadCreated::class)->handle($lead, 'meeting');

        session()->flash('success', trans('sales_form::app.schedule.success'));

        return redirect()->route('admin.leads.view', $lead->id);
    }

    /**
     * Quick "Create Lead" form: an interested client with no meeting booked yet.
     */
    public function createNewLead()
    {
        abort_unless(bouncer()->hasPermission('leads.create'), 401);

        return view('sales_form::new-lead', $this->formData());
    }

    public function storeNewLead(NewLeadRequest $request): RedirectResponse
    {
        $user = auth()->guard('user')->user();

        try {
            $lead = DB::transaction(
                fn () => $this->leadBuilder->createNewLead($request->validated(), $user)
            );
        } catch (\Throwable $e) {
            Log::error('Create Lead form failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', trans('sales_form::app.store.failed'));
        }

        Event::dispatch('lead.create.after', $lead);

        session()->flash('success', trans('sales_form::app.store.success', ['title' => $lead->title]));

        return redirect()->route('admin.leads.index');
    }

    /**
     * What both forms need: who is filling it in, who they may assign it to, and
     * the choices on offer.
     */
    protected function formData(): array
    {
        $user = auth()->guard('user')->user();

        return [
            'currentUser'     => $user,
            'canReassign'     => $this->leadBuilder->canReassign(),
            'salesUsers'      => $this->userRepository->findWhere(['status' => 1], ['id', 'name', 'email']),
            'timezones'       => array_keys(config('sales_form.timezones')),
            'engagementTypes' => config('sales_form.engagement_types'),
            'minimumValue'    => (float) config('sales_form.minimum_lead_value'),
            'agentsOnFile'    => DB::table('vicidial_agents')->count(),
            'lead'            => null,
            'prefill'         => [],
        ];
    }

    /**
     * Resolve a phone number against the ViciDial mirror so the rep sees who they
     * are booking before the lead is created.
     */
    public function lookup(): JsonResponse
    {
        return response()->json(
            $this->lookup->lookup(request('phone'))
        );
    }

    public function store(SalesFormRequest $request): RedirectResponse
    {
        $user = auth()->guard('user')->user();

        try {
            $lead = $this->slots->locked(fn () => DB::transaction(
                fn () => $this->leadBuilder->create($request->validated(), $user)
            ));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Sales form lead creation failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', trans('sales_form::app.store.failed'));
        }

        Event::dispatch('lead.create.after', $lead);

        session()->flash('success', trans('sales_form::app.store.success', ['title' => $lead->title]));

        return redirect()->route('admin.leads.view', $lead->id);
    }

    /**
     * What is already on the shared sales calendar on a day, shown next to the
     * meeting fields so a rep sees a clash before booking. Times come back in
     * the client's timezone once one is picked, else in the rep's own.
     *
     * Only administrators see event titles; the calendar holds every rep's
     * client names, and a sales executive only sees their own leads.
     */
    public function dayPlan(): JsonResponse
    {
        if (! $this->calendarFeed->configured()) {
            return response()->json(['configured' => false]);
        }

        $zone = CalendarFeed::zone(request('timezone'), request('zone'));

        $date = request('date');

        try {
            $dayStart = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                ? Carbon::createFromFormat('Y-m-d H:i:s', $date.' 00:00:00', $zone)
                : Carbon::now($zone)->startOfDay();
        } catch (\Throwable) {
            $dayStart = Carbon::now($zone)->startOfDay();
        }

        try {
            $plan = $this->calendarFeed->day($dayStart, $this->leadBuilder->canReassign());
        } catch (\Throwable $e) {
            return response()->json([
                'configured' => true,
                'error'      => trans('sales_form::app.day-plan.unavailable'),
            ]);
        }

        return response()->json([
            'configured'      => true,
            'date'            => $dayStart->format('Y-m-d'),
            'date_label'      => $dayStart->format('l, j F Y'),
            'zone_label'      => $dayStart->copy()->setTime(12, 0)->format('T'),
            'is_client_zone'  => (bool) (request('timezone') && isset(config('sales_form.timezones')[request('timezone')])),
            'meeting_minutes' => (int) config('sales_form.notify.meeting_minutes'),
            'stale'           => $plan['stale'],
            'events'          => $plan['events'],
        ]);
    }

    /**
     * Send the user to Google Calendar with this activity already composed, so a
     * meeting logged on a lead can be put in the shared calendar from the lead
     * screen exactly as it can from the notification email.
     */
    public function activityCalendar(int $id): RedirectResponse
    {
        $activity = DB::table('activities')->where('id', $id)->first();

        if (! $activity) {
            abort(404);
        }

        $leadId = DB::table('lead_activities')->where('activity_id', $id)->value('lead_id');

        /**
         * `back()` would loop onto this same URL when there is no referer, so send
         * the user somewhere real instead.
         */
        if (! $activity->schedule_from) {
            $return = $leadId
                ? redirect()->route('admin.leads.view', $leadId)
                : redirect()->route('admin.leads.index');

            return $return->with('error', trans('sales_form::app.activity.not-schedulable'));
        }

        $lead = $leadId ? app(\Webkul\Lead\Models\Lead::class)->find($leadId) : null;

        $digest = $lead ? $this->leadDigest->build($lead) : [];

        /**
         * Activities are stored in the app timezone (UTC); show the event in the
         * agent's own zone when the lead records one, so the calendar entry reads
         * the same as the meeting does everywhere else in the CRM.
         */
        $timezone = $digest['timezone'] ?? config('app.timezone', 'UTC');

        $start = Carbon::parse($activity->schedule_from, config('app.timezone', 'UTC'))
            ->setTimezone($timezone);

        $minutes = $activity->schedule_to
            ? max(5, $start->diffInMinutes(
                Carbon::parse($activity->schedule_to, config('app.timezone', 'UTC'))->setTimezone($timezone)
            ))
            : (int) config('sales_form.notify.meeting_minutes');

        $client = $digest['client_name'] ?? null;

        $title = $client
            ? $client.' | Virtual Assistant Discovery Call'
            : ($activity->title ?: 'Discovery Call');

        $guests = array_filter([
            $digest['client_email'] ?? null,
            $digest['owner_email'] ?? null,
            auth()->guard('user')->user()?->email,
        ]);

        $details = implode("\n", array_filter([
            $activity->comment ?: null,
            ! empty($digest['url']) ? "\nLead in the CRM: ".$digest['url'] : null,
        ]));

        return redirect()->away($this->calendar->build(
            $title,
            $start,
            $timezone,
            $minutes,
            $guests,
            $details,
            $activity->location ?: config('sales_form.notify.meeting_location'),
        ));
    }
}
