{{--
    On the lead view (admin.leads.view.title.after): prepare the lead's contract
    fields and download the NDA and the VA agreement once they are saved.
--}}
@if (bouncer()->hasPermission('leads.contracts'))
    @php($contract = \Nexus\Contracts\Models\LeadContract::with('editor')->where('lead_id', $lead->id)->first())

    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-800 dark:bg-gray-950">
        <div class="flex flex-col">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                @lang('contracts::app.panel.title')
            </span>

            <span class="text-xs text-gray-600 dark:text-gray-300">
                @if ($contract)
                    @lang('contracts::app.panel.saved', [
                        'when' => $contract->updated_at->diffForHumans(),
                        'name' => $contract->editor?->name ?? '—',
                    ])
                @else
                    @lang('contracts::app.panel.not-saved')
                @endif
            </span>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.leads.contracts.edit', $lead->id) }}" class="{{ $contract ? 'secondary-button' : 'primary-button' }}">
                {{ $contract ? trans('contracts::app.panel.edit') : trans('contracts::app.panel.prepare') }}
            </a>

            @if ($contract)
                <a href="{{ route('admin.leads.contracts.download', [$lead->id, 'nda']) }}" class="secondary-button">
                    @lang('contracts::app.panel.nda')
                </a>

                <a href="{{ route('admin.leads.contracts.download', [$lead->id, 'agreement']) }}" class="secondary-button">
                    @lang('contracts::app.panel.agreement')
                </a>
            @endif
        </div>
    </div>
@endif
