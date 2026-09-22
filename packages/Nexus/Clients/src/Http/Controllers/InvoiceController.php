<?php

namespace Nexus\Clients\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Nexus\Clients\Models\Client;
use Nexus\Clients\Models\ClientInvoice;

/**
 * Monthly invoices on a client: raised for a month with an amount and a due
 * date, marked paid (or void) when settled, and downloadable as a PDF.
 */
class InvoiceController extends Controller
{
    public function store(int $id): RedirectResponse
    {
        $client = Client::findOrFail($id);

        $data = request()->validate([
            'billing_month' => ['required', 'date_format:Y-m'],
            'amount'        => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'due_date'      => ['required', 'date_format:Y-m-d'],
            'description'   => ['nullable', 'string', 'max:255'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['billing_month'])->startOfMonth();

        $invoice = DB::transaction(fn () => $client->invoices()->create([
            'number'        => ClientInvoice::nextNumber(),
            'billing_month' => $month->format('Y-m-d'),
            'amount'        => $data['amount'],
            'currency'      => config('clients.currency'),
            'issued_on'     => now()->format('Y-m-d'),
            'due_date'      => $data['due_date'],
            'status'        => 'unpaid',
            'description'   => $data['description'] ?: $this->defaultDescription($client, $month),
            'notes'         => $data['notes'] ?? null,
            'created_by'    => auth()->guard('user')->id(),
        ]));

        return redirect()
            ->route('admin.clients.view', $client->id)
            ->with('success', trans('clients::app.flash.invoice-created', ['number' => $invoice->number]));
    }

    /**
     * Settle, void or reopen an invoice.
     */
    public function update(int $id, int $invoiceId): RedirectResponse
    {
        $invoice = $this->invoice($id, $invoiceId);

        $data = request()->validate([
            'status'  => ['required', Rule::in(['unpaid', 'paid', 'void'])],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $invoice->update([
            'status'  => $data['status'],
            'paid_on' => $data['status'] === 'paid' ? ($data['paid_on'] ?? now()->format('Y-m-d')) : null,
        ]);

        return redirect()
            ->route('admin.clients.view', $id)
            ->with('success', trans('clients::app.flash.invoice-'.$data['status'], ['number' => $invoice->number]));
    }

    public function pdf(int $id, int $invoiceId): Response
    {
        $invoice = $this->invoice($id, $invoiceId)->load('client');

        $logo = base_path('packages/Nexus/SalesForm/src/Resources/assets/logo.png');

        return Pdf::loadView('clients::invoices.pdf', [
            'invoice' => $invoice,
            'client'  => $invoice->client,
            'issuer'  => config('clients.issuer'),
            'payment' => config('clients.payment_instructions'),
            'logo'    => is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null,
        ])
            ->setPaper('A4')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->download($invoice->number.'.pdf');
    }

    public function destroy(int $id, int $invoiceId): RedirectResponse
    {
        $invoice = $this->invoice($id, $invoiceId);

        $invoice->delete();

        return redirect()
            ->route('admin.clients.view', $id)
            ->with('success', trans('clients::app.flash.invoice-deleted', ['number' => $invoice->number]));
    }

    protected function invoice(int $clientId, int $invoiceId): ClientInvoice
    {
        return ClientInvoice::where('client_id', $clientId)->findOrFail($invoiceId);
    }

    protected function defaultDescription(Client $client, Carbon $month): string
    {
        return trim(sprintf(
            'Virtual assistant services%s, %s',
            $client->engagement_type ? ' ('.strtolower($client->engagement_type).')' : '',
            $month->format('F Y')
        ));
    }
}
