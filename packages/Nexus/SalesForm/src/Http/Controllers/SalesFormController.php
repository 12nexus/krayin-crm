<?php

namespace Nexus\SalesForm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Nexus\SalesForm\Http\Requests\SalesFormRequest;
use Nexus\SalesForm\Services\AgentLookupService;
use Nexus\SalesForm\Services\LeadBuilder;
use Webkul\User\Repositories\UserRepository;

class SalesFormController extends Controller
{
    public function __construct(
        protected AgentLookupService $lookup,
        protected LeadBuilder $leadBuilder,
        protected UserRepository $userRepository,
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
}
