<?php

namespace Nexus\Clients\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Nexus\Clients\DataGrids\ClientDataGrid;
use Nexus\Clients\Http\Requests\ClientRequest;
use Nexus\Clients\Models\Client;
use Nexus\SalesForm\Services\LeadFields;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class ClientController extends Controller
{
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(ClientDataGrid::class)->process();
        }

        return view('clients::index');
    }

    /**
     * New client form, prefilled from a won lead when one is passed.
     */
    public function create(): View|RedirectResponse
    {
        $client = new Client([
            'status'           => 'active',
            'onboarded_on'     => now()->format('Y-m-d'),
            'user_id'          => auth()->guard('user')->id(),
        ]);

        if ($leadId = (int) request('lead_id')) {
            $lead = Lead::with('person.organization')->find($leadId);

            if ($lead && ($existing = Client::where('lead_id', $lead->id)->first())) {
                return redirect()
                    ->route('admin.clients.view', $existing->id)
                    ->with('warning', trans('clients::app.form.already-onboarded'));
            }

            if ($lead) {
                $fields = app(LeadFields::class)->get($lead->id);
                $person = $lead->person;

                $client->fill([
                    'name'             => $person?->name ?? $lead->title,
                    'company'          => $fields['brokerage'] ?? $person?->organization?->name,
                    'email'            => collect($person?->emails ?? [])->pluck('value')->filter()->first(),
                    'phone'            => collect($person?->contact_numbers ?? [])->pluck('value')->filter()->first(),
                    'address'          => collect([
                        $fields['agent_city'] ?? null,
                        $fields['agent_state'] ?? null,
                        $fields['agent_country'] ?? null,
                    ])->filter()->implode(', '),
                    'engagement_type'  => $fields['engagement_type'] ?? null,
                    'monthly_retainer' => $lead->lead_value,
                    'lead_id'          => $lead->id,
                    'person_id'        => $person?->id,
                    'user_id'          => $lead->user_id,
                ]);
            }
        }

        return view('clients::form', $this->formData($client));
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());

        return redirect()
            ->route('admin.clients.view', $client->id)
            ->with('success', trans('clients::app.flash.created'));
    }

    public function view(int $id): View
    {
        $client = Client::with(['documents.uploader', 'invoices', 'lead', 'user'])->findOrFail($id);

        $invoices = $client->invoices;

        return view('clients::view', [
            'client'      => $client,
            'contracts'   => $client->documents->where('category', 'contract'),
            'documents'   => $client->documents->where('category', '!=', 'contract'),
            'invoices'    => $invoices,
            'outstanding' => $invoices->where('status', 'unpaid')->sum('amount'),
            'paid'        => $invoices->where('status', 'paid')->sum('amount'),
            'overdue'     => $invoices->filter(fn ($invoice) => $invoice->displayStatus() === 'overdue')->count(),
            'nextMonth'   => $this->nextBillingMonth($client),
        ]);
    }

    public function edit(int $id): View
    {
        return view('clients::form', $this->formData(Client::findOrFail($id)));
    }

    public function update(ClientRequest $request, int $id): RedirectResponse
    {
        Client::findOrFail($id)->update($request->validated());

        return redirect()
            ->route('admin.clients.view', $id)
            ->with('success', trans('clients::app.flash.updated'));
    }

    public function destroy(int $id): JsonResponse
    {
        $client = Client::with('documents')->findOrFail($id);

        foreach ($client->documents as $document) {
            Storage::disk(config('clients.disk'))->delete($document->path);
        }

        $client->delete();

        return new JsonResponse(['message' => trans('clients::app.flash.deleted')]);
    }

    protected function formData(Client $client): array
    {
        return [
            'client' => $client,
            'users'  => User::where('status', 1)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * The month after the latest one invoiced, or the current month.
     */
    protected function nextBillingMonth(Client $client): string
    {
        $latest = $client->invoices->max('billing_month');

        return $latest
            ? $latest->copy()->startOfMonth()->addMonth()->format('Y-m')
            : now()->format('Y-m');
    }
}
