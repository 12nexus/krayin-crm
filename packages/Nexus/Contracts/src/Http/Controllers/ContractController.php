<?php

namespace Nexus\Contracts\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Nexus\Contracts\Models\LeadContract;
use Nexus\Contracts\Services\ContractFields;
use Nexus\SalesForm\Support\LeadAccess;

/**
 * A lead's contract fields: edited and saved on one form, then printed into
 * the NDA and the Agreement to Hire Virtual Assistant on download.
 */
class ContractController extends Controller
{
    public function __construct(protected ContractFields $contractFields) {}

    public function edit(int $id): View
    {
        $lead = LeadAccess::findOrFail($id);

        $contract = LeadContract::with('editor')->where('lead_id', $lead->id)->first();

        // "Refill from lead" shows the lead's current details without saving them.
        $fromLead = request()->boolean('from_lead');

        $fields = $fromLead || ! $contract
            ? $this->contractFields->defaults($lead)
            : $this->contractFields->current($lead, $contract);

        return view('contracts::edit', [
            'lead'        => $lead,
            'contract'    => $contract,
            'fields'      => $fields,
            'fromLead'    => $fromLead,
            'definitions' => $this->contractFields->definitions(),
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $lead = LeadAccess::findOrFail($id);

        $data = request()->validate($this->contractFields->rules());

        $userId = auth()->guard('user')->id();

        $contract = LeadContract::firstOrNew(['lead_id' => $lead->id]);

        if (! $contract->exists) {
            $contract->created_by = $userId;
        }

        $contract->fields = $this->contractFields->normalise($data);
        $contract->updated_by = $userId;
        $contract->save();

        return redirect()
            ->route('admin.leads.contracts.edit', $lead->id)
            ->with('success', trans('contracts::app.flash.saved'));
    }

    /**
     * The NDA or the agreement as a PDF, from the saved fields.
     */
    public function download(int $id, string $document): Response|RedirectResponse
    {
        $lead = LeadAccess::findOrFail($id);

        $contract = LeadContract::where('lead_id', $lead->id)->first();

        if (! $contract) {
            return redirect()
                ->route('admin.leads.contracts.edit', $lead->id)
                ->with('warning', trans('contracts::app.flash.save-first'));
        }

        $fields = $this->contractFields->current($lead, $contract);

        $logo = base_path('packages/Nexus/SalesForm/src/Resources/assets/logo.png');

        return Pdf::loadView('contracts::pdf.'.$document, [
            'v'    => $this->contractFields->printable($fields),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null,
        ])
            ->setPaper('letter')
            ->setOption('defaultFont', 'Helvetica')
            ->download($this->contractFields->filename($document, $fields));
    }
}
