<?php

namespace Nexus\SalesForm\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Nexus\SalesForm\Http\Requests\SalesFormRequest;
use Nexus\SalesForm\Services\AgentLookupService;
use Nexus\SalesForm\Services\CalendarLink;
use Nexus\SalesForm\Services\LeadBuilder;
use Nexus\SalesForm\Services\LeadDigest;
use Webkul\User\Repositories\UserRepository;

class SalesFormController extends Controller
{
    public function __construct(
        protected AgentLookupService $lookup,
        protected LeadBuilder $leadBuilder,
        protected UserRepository $userRepository,
        protected LeadDigest $leadDigest,
        protected CalendarLink $calendar,
    ) {}

    /**
     * The in-CRM replacement for the Google form.
     */
    public function index()
    {
        $user = auth()->guard('user')->user();

        return view('sales_form::index', [
            'currentUser'  => $user,
            'canReassign'  => bouncer()->hasPermission('settings.user.users'),
            'salesUsers'   => $this->userRepository->all(['id', 'name', 'email']),
            'timezones'    => array_keys(config('sales_form.timezones')),
            'agentsOnFile' => DB::table('vicidial_agents')->count(),
        ]);
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
            $lead = DB::transaction(
                fn () => $this->leadBuilder->create($request->validated(), $user)
            );
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
