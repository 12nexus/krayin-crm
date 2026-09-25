<?php

namespace Nexus\Funnel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Nexus\Funnel\Services\Funnel;
use Nexus\SalesForm\Http\Requests\MeetingRequest;
use Nexus\SalesForm\Listeners\LeadCreated;
use Nexus\SalesForm\Services\LeadBuilder;
use Nexus\SalesForm\Services\MeetingSlots;
use Nexus\SalesForm\Support\LeadAccess;
use Webkul\Lead\Contracts\Lead;

/**
 * The funnel actions on the lead view: valid / invalid, meeting held, no-show,
 * (re)scheduling a meeting, and restoring an archived lead.
 */
class FunnelController extends Controller
{
    public function __construct(
        protected Funnel $funnel,
        protected LeadBuilder $leadBuilder,
        protected MeetingSlots $slots,
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
     * Book a meeting on the lead with the sales form's own meeting fields:
     * reschedules, moved times and follow-ups. The clash check runs in the
     * request, and again under the booking lock.
     */
    public function meeting(MeetingRequest $request, int $id): JsonResponse
    {
        $lead = $this->lead($id);

        if ($this->funnel->isArchived($lead)) {
            abort(422, trans('funnel::app.errors.archived'));
        }

        $kind = match ($this->funnel->stageCode($lead)) {
            'new'       => 'discovery',
            'follow-up' => 'follow-up',
            default     => 'rescheduled',
        };

        $lead = $this->slots->locked(fn () => DB::transaction(
            fn () => $this->leadBuilder->scheduleMeeting($lead, $request->validated(), $this->user(), $kind)
        ));

        app(LeadCreated::class)->handle($lead, match ($kind) {
            'discovery' => 'meeting',
            'follow-up' => 'follow-up',
            default     => 'rescheduled',
        });

        session()->flash('success', trans('funnel::app.flash.meeting'));

        return new JsonResponse([
            'message'  => trans('funnel::app.flash.meeting'),
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

    protected function lead(int $id): Lead
    {
        abort_unless(bouncer()->hasPermission('leads.edit'), 401);

        return LeadAccess::findOrFail($id);
    }

    protected function user()
    {
        return auth()->guard('user')->user();
    }
}
