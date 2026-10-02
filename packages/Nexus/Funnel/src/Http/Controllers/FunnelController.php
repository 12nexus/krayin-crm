<?php

namespace Nexus\Funnel\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Nexus\Funnel\Services\Funnel;
use Nexus\SalesForm\Http\Requests\CallRequest;
use Nexus\SalesForm\Http\Requests\MeetingRequest;
use Nexus\SalesForm\Listeners\LeadCreated;
use Nexus\SalesForm\Services\GoogleCalendar;
use Nexus\SalesForm\Services\LeadBuilder;
use Nexus\SalesForm\Services\MeetingSlots;
use Nexus\SalesForm\Support\LeadAccess;
use Webkul\Lead\Contracts\Lead;

/**
 * The funnel actions on the lead view: valid / invalid, meeting held, no-show,
 * (re)scheduling a meeting or a call back, and restoring an archived lead.
 */
class FunnelController extends Controller
{
    public function __construct(
        protected Funnel $funnel,
        protected LeadBuilder $leadBuilder,
        protected MeetingSlots $slots,
        protected GoogleCalendar $calendar,
    ) {}

    public function valid(int $id): RedirectResponse
    {
        $lead = $this->lead($id);

        return $this->run($lead, fn () => $this->funnel->markValid($lead, $this->user()), 'valid');
    }

    public function invalid(int $id): RedirectResponse
    {
        $lead = $this->lead($id);

        if (! in_array($this->funnel->stageCode($lead), config('funnel.invalidatable'), true)) {
            return back()->with('error', trans('funnel::app.errors.not-invalidatable'));
        }

        $reason = request()->validate(['reason' => ['nullable', 'string', 'max:2000']])['reason'] ?? null;

        return $this->run($lead, fn () => $this->funnel->markInvalid($lead, $this->user(), $reason), 'invalid');
    }

    public function restore(int $id): RedirectResponse
    {
        $lead = $this->lead($id);

        if (! $this->funnel->isArchived($lead)) {
            return back();
        }

        return $this->run($lead, fn () => $this->funnel->restore($lead, $this->user()), 'restored');
    }

    public function held(int $id): RedirectResponse
    {
        $lead = $this->lead($id);

        return $this->run($lead, fn () => $this->funnel->meetingHeld($lead, $this->user()), 'held');
    }

    public function noShow(int $id): RedirectResponse
    {
        $lead = $this->lead($id);

        return $this->run($lead, fn () => $this->funnel->noShow($lead, $this->user()), 'no-show');
    }

    /**
     * Book a meeting on the lead with the sales form's own meeting fields. The
     * clash check runs in the request, and again under the booking lock.
     *
     *  - New Lead: the first (discovery) meeting.
     *  - mode=new: a follow-up meeting, the client having come to theirs. From
     *    Meeting Scheduled the current meeting is recorded as held first.
     *  - Otherwise a reschedule: the open meeting moves to the new time (after a
     *    no-show, a new one is booked), and so does its Google Calendar event.
     */
    public function meeting(MeetingRequest $request, int $id): JsonResponse
    {
        $lead = $this->lead($id, 'activities.create');

        // Refused as validation errors: Krayin shows any other 422 as a bare 500.
        if ($this->funnel->isArchived($lead)) {
            throw ValidationException::withMessages(['meeting_date' => trans('funnel::app.errors.archived')]);
        }

        $stage = $this->funnel->stageCode($lead);
        $newMeeting = $request->input('mode') === 'new';

        if ($newMeeting && ! in_array($stage, config('funnel.new_meeting_stages'), true)) {
            throw ValidationException::withMessages(['meeting_date' => trans('funnel::app.errors.new-meeting-stage')]);
        }

        $kind = match (true) {
            $newMeeting       => 'follow-up',
            $stage === 'new'  => 'discovery',
            default           => 'rescheduled',
        };

        // The meeting as it was, to find its calendar event.
        $previous = $kind === 'rescheduled' ? $this->latestMeeting($lead) : null;

        $lead = $this->slots->locked(fn () => DB::transaction(function () use ($lead, $request, $kind, $stage) {
            if ($kind === 'follow-up' && $stage === 'meeting-scheduled') {
                $this->funnel->meetingHeld($lead, $this->user());

                $lead->refresh()->load('stage');
            }

            return $this->leadBuilder->scheduleMeeting($lead, $request->validated(), $this->user(), $kind);
        }));

        match ($kind) {
            'discovery' => app(LeadCreated::class)->handle($lead, 'meeting'),
            'follow-up' => app(LeadCreated::class)->handle($lead, 'follow-up'),
            default     => app(LeadCreated::class)->rescheduled(
                $lead,
                $previous?->schedule_from,
                $previous ? $this->moveCalendarEvent($lead, $previous, $request->validated()) : null,
            ),
        };

        session()->flash('success', trans('funnel::app.flash.'.($kind === 'follow-up' ? 'new-meeting' : 'meeting')));

        return new JsonResponse([
            'message'  => trans('funnel::app.flash.meeting'),
            'redirect' => route('admin.leads.view', $lead->id),
        ]);
    }

    /**
     * Give the meeting's Google Calendar event its new time. Returns the event's
     * link, or null if it was not moved (not connected, not found, or Google
     * refused), in which case the email asks for it to be moved by hand.
     */
    protected function moveCalendarEvent(Lead $lead, object $previous, array $input): ?string
    {
        if (! $previous->schedule_from) {
            return null;
        }

        $current = $this->latestMeeting($lead);

        if (! $current || $current->is_done || ! $current->schedule_from) {
            return null;
        }

        try {
            return $this->calendar->moveMeeting(
                (int) $previous->id,
                (int) $current->id,
                Carbon::parse($previous->schedule_from, 'UTC'),
                Carbon::parse($current->schedule_from, 'UTC'),
                Carbon::parse($current->schedule_to ?? $current->schedule_from, 'UTC'),
                config('sales_form.timezones')[$input['timezone']] ?? config('app.timezone', 'UTC'),
                $lead->person?->name,
            );
        } catch (\Throwable $e) {
            Log::warning('Could not move the meeting in Google Calendar', [
                'lead_id' => $lead->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * A new time to call a New Lead back. Logged as a fresh Call entry; the
     * earlier one is closed as rescheduled.
     */
    public function call(CallRequest $request, int $id): JsonResponse
    {
        $lead = $this->lead($id, 'activities.create');

        if ($this->funnel->stageCode($lead) !== 'new' || $this->funnel->isArchived($lead)) {
            abort(422, trans('funnel::app.errors.call-new-only'));
        }

        $first = ! $this->funnel->currentCall($lead);

        DB::transaction(fn () => $this->leadBuilder->scheduleCall(
            $lead,
            $request->validated(),
            $this->user(),
            $first ? 'Call back time set.' : 'Call back rescheduled.'
        ));

        session()->flash('success', trans('funnel::app.flash.call'));

        return new JsonResponse([
            'message'  => trans('funnel::app.flash.call'),
            'redirect' => route('admin.leads.view', $lead->id),
        ]);
    }

    protected function run(Lead $lead, callable $action, string $flash): RedirectResponse
    {
        try {
            DB::transaction($action);
        } catch (\Throwable $e) {
            Log::error('Funnel action failed', [
                'lead_id' => $lead->id,
                'action'  => $flash,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', trans('funnel::app.errors.failed'));
        }

        return redirect()
            ->route('admin.leads.view', $lead->id)
            ->with('success', trans('funnel::app.flash.'.$flash));
    }

    /**
     * Booking a meeting or a call back is logging an activity, open to anyone who
     * may create activities; every other move changes the lead and needs leads.edit.
     */
    protected function lead(int $id, string $permission = 'leads.edit'): Lead
    {
        abort_unless(bouncer()->hasPermission($permission), 401);

        return LeadAccess::findOrFail($id);
    }

    /**
     * The lead's meeting that counts: an open one, else the last one booked.
     */
    protected function latestMeeting(Lead $lead): ?object
    {
        return DB::table('activities')
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('lead_activities.lead_id', $lead->id)
            ->where('activities.type', 'meeting')
            ->orderBy('activities.is_done')
            ->orderByDesc('activities.id')
            ->first(['activities.id', 'activities.schedule_from', 'activities.schedule_to', 'activities.is_done']);
    }

    protected function user()
    {
        return auth()->guard('user')->user();
    }
}
