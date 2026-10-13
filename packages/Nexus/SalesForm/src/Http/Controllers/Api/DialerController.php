<?php

namespace Nexus\SalesForm\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Nexus\SalesForm\Http\Controllers\SalesFormController;
use Nexus\SalesForm\Http\Middleware\DialerToken;
use Nexus\SalesForm\Http\Requests\NewLeadRequest;
use Nexus\SalesForm\Http\Requests\SalesFormRequest;
use Nexus\SalesForm\Listeners\LeadCreated;
use Nexus\SalesForm\Services\AgentLookupService;
use Nexus\SalesForm\Services\LeadBuilder;
use Nexus\SalesForm\Services\MeetingSlots;
use Nexus\SalesForm\Support\LeadAccess;
use Webkul\Lead\Contracts\Lead;
use Webkul\User\Models\User;
use Webkul\User\Repositories\UserRepository;

/**
 * The 12Nexus Dialer Chrome extension: the sales form and the Create Lead form,
 * filled in on the ViciDial agent screen at disposition time.
 *
 * Every lead goes through the same form requests and LeadBuilder as the web
 * forms, so it lands exactly as if the rep had typed it into the CRM.
 */
class DialerController extends Controller
{
    public function __construct(
        protected LeadBuilder $leadBuilder,
        protected MeetingSlots $slots,
        protected UserRepository $userRepository,
    ) {}

    /**
     * Swap a rep's CRM email and password for a dialer token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'device'   => ['nullable', 'string', 'max:100'],
        ]);

        $throttleKey = 'dialer-login|'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'message' => trans('sales_form::app.dialer.login.throttled', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ], 429);
        }

        $provider = auth()->guard('user')->getProvider();

        $user = $provider->retrieveByCredentials(['email' => $credentials['email']]);

        if (! $user || ! $provider->validateCredentials($user, ['password' => $credentials['password']])) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json(['message' => trans('sales_form::app.dialer.login.failed')], 422);
        }

        RateLimiter::clear($throttleKey);

        if (! $user->status || ! $user->role) {
            return response()->json(['message' => trans('sales_form::app.dialer.login.disabled')], 403);
        }

        auth()->guard('user')->setUser($user);

        $profile = $this->profile($user);

        if (! $profile['permissions']['meeting'] && ! $profile['permissions']['new_lead']) {
            return response()->json(['message' => trans('sales_form::app.dialer.login.no-permission')], 403);
        }

        $expiresAt = now()->addDays((int) config('sales_form.dialer.token_days'));

        $name = trim('ViciDial extension '.($credentials['device'] ?? ''));

        $token = $user->createToken($name, [DialerToken::ABILITY], $expiresAt);

        return response()->json([
            'token'      => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ] + $profile);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('dialer_token')?->delete();

        return response()->json(['message' => trans('sales_form::app.dialer.logged-out')]);
    }

    /**
     * Who is signed in, what they may do, and every choice the forms offer.
     */
    public function me(): JsonResponse
    {
        return response()->json($this->profile(auth()->guard('user')->user()));
    }

    /**
     * The ViciDial mirror's record for a number, and any leads already on file
     * for it: the same answer the web forms' lookup gives.
     */
    public function lookup(): JsonResponse
    {
        abort_unless($this->canLog(), 401);

        return app(SalesFormController::class)->lookup();
    }

    /**
     * The shared sales calendar on a day, as the web form's day plan shows it.
     */
    public function dayPlan(): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('sales_form'), 401);

        return app(SalesFormController::class)->dayPlan();
    }

    /**
     * A new lead with its discovery meeting (the sales form).
     */
    public function storeMeeting(SalesFormRequest $request): JsonResponse
    {
        $user = auth()->guard('user')->user();

        try {
            $lead = $this->slots->locked(fn () => DB::transaction(
                fn () => $this->leadBuilder->create($request->validated(), $user)
            ));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->failed('Dialer: lead with meeting failed', $user, $e);
        }

        Event::dispatch('lead.create.after', $lead);

        return $this->created($lead, 'meeting');
    }

    /**
     * A new lead with no meeting yet, optionally with a time to call back (the
     * Create Lead form).
     */
    public function storeNewLead(NewLeadRequest $request): JsonResponse
    {
        $user = auth()->guard('user')->user();

        try {
            $lead = DB::transaction(
                fn () => $this->leadBuilder->createNewLead($request->validated(), $user)
            );
        } catch (\Throwable $e) {
            return $this->failed('Dialer: new lead failed', $user, $e);
        }

        Event::dispatch('lead.create.after', $lead);

        return $this->created($lead, 'lead');
    }

    /**
     * The sales form against a lead already on file for the number, typically
     * one in New Lead that has now agreed to a meeting.
     */
    public function storeSchedule(SalesFormRequest $request, int $id): JsonResponse
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
            return $this->failed('Dialer: meeting on an existing lead failed', $user, $e, $id);
        }

        app(LeadCreated::class)->handle($lead, 'meeting');

        return $this->created($lead, 'existing');
    }

    protected function canLog(): bool
    {
        return bouncer()->hasPermission('sales_form') || bouncer()->hasPermission('leads.create');
    }

    protected function profile(User $user): array
    {
        $canReassign = $this->leadBuilder->canReassign();

        return [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],

            'permissions' => [
                'meeting'          => bouncer()->hasPermission('sales_form'),
                'new_lead'         => bouncer()->hasPermission('leads.create'),
                'existing_meeting' => bouncer()->hasPermission('sales_form') && bouncer()->hasPermission('leads.edit'),
                'reassign'         => $canReassign,
            ],

            'options' => [
                'firms'            => collect(AgentLookupService::firmOptions())
                    ->map(fn ($firm, $key) => ['key' => $key, 'name' => $firm['name']])
                    ->values(),
                'other_firm'       => config('sales_form.other_firm.key'),
                'timezones'        => config('sales_form.timezones'),
                'engagement_types' => config('sales_form.engagement_types'),
                'assistant_types'  => ['Remote VA', 'In-house Assistant', 'Other'],
                'willingness'      => config('sales_form.willingness'),
                'minimum_value'    => (float) config('sales_form.minimum_lead_value'),
                'meeting_minutes'  => (int) config('sales_form.notify.meeting_minutes'),
                'call_minutes'     => (int) config('sales_form.call_minutes'),
                'sales_users'      => $canReassign
                    ? $this->userRepository->findWhere(['status' => 1], ['id', 'name'])
                        ->map(fn ($salesUser) => ['id' => $salesUser->id, 'name' => $salesUser->name])
                        ->values()
                    : [],
                'dispositions'     => self::dispositions(),
            ],

            'crm_url' => route('admin.leads.index'),
        ];
    }

    /**
     * ViciDial status code => the form it opens (`meeting` or `lead`), from
     * DIALER_DISPOSITIONS ("SALE:meeting,CALLBK:lead") or the config default.
     */
    public static function dispositions(): array
    {
        $configured = config('sales_form.dialer.dispositions');

        if (is_string($configured)) {
            $configured = collect(explode(',', $configured))
                ->map(fn ($pair) => array_map('trim', explode(':', $pair, 2)))
                ->filter(fn ($pair) => $pair[0] !== '')
                ->mapWithKeys(fn ($pair) => [strtoupper($pair[0]) => ($pair[1] ?? '') === 'lead' ? 'lead' : 'meeting'])
                ->all();
        }

        return (array) $configured;
    }

    protected function created(Lead $lead, string $kind): JsonResponse
    {
        return response()->json([
            'message' => trans('sales_form::app.dialer.created.'.$kind, ['title' => $lead->title]),
            'lead'    => [
                'id'    => $lead->id,
                'title' => $lead->title,
                'url'   => route('admin.leads.view', $lead->id),
            ],
        ], 201);
    }

    protected function failed(string $what, User $user, \Throwable $e, ?int $leadId = null): JsonResponse
    {
        Log::error($what, array_filter([
            'user_id' => $user->id,
            'lead_id' => $leadId,
            'message' => $e->getMessage(),
        ]));

        return response()->json(['message' => trans('sales_form::app.store.failed')], 500);
    }
}
