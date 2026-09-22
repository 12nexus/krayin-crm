@php
    $canEdit = bouncer()->hasPermission('clients.edit');
    $input = 'w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300';
    $label = 'mb-1 block text-xs font-medium text-gray-800 dark:text-white';
    $card = 'box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900';

    $statusStyles = [
        'paid'    => 'bg-green-100 text-green-700',
        'unpaid'  => 'bg-amber-100 text-amber-800',
        'overdue' => 'bg-red-100 text-red-700',
        'void'    => 'bg-gray-200 text-gray-600',
    ];

    $clientStatusStyles = [
        'active' => 'bg-green-100 text-green-700',
        'paused' => 'bg-amber-100 text-amber-800',
        'ended'  => 'bg-gray-200 text-gray-600',
    ];

    $copyIcon = '<svg class="nexus-copy-icon h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
@endphp

<x-admin::layouts>
    <x-slot:title>
        {{ $client->name }}
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1">
                <a href="{{ route('admin.clients.index') }}" class="text-xs text-brandColor">
                    ← @lang('clients::app.index.title')
                </a>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xl font-bold dark:text-white">{{ $client->name }}</span>

                    <span class="rounded-xl px-2 py-0.5 text-xs font-medium {{ $clientStatusStyles[$client->status] ?? '' }}">
                        {{ config('clients.statuses')[$client->status] ?? $client->status }}
                    </span>

                    @if ($client->engagement_type)
                        <span class="rounded-xl bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">{{ $client->engagement_type }}</span>
                    @endif
                </div>

                @if ($client->company)
                    <p class="text-gray-500 dark:text-gray-400">{{ $client->company }}</p>
                @endif
            </div>

            @if ($canEdit)
                <a href="{{ route('admin.clients.edit', $client->id) }}" class="secondary-button">
                    @lang('clients::app.view.edit')
                </a>
            @endif
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ([
                ['clients::app.view.retainer', $client->monthly_retainer !== null ? core()->formatBasePrice($client->monthly_retainer) : '—', ''],
                ['clients::app.view.outstanding', core()->formatBasePrice($outstanding), $outstanding > 0 ? 'text-amber-700' : ''],
                ['clients::app.view.paid', core()->formatBasePrice($paid), 'text-green-700'],
                ['clients::app.view.overdue', $overdue, $overdue > 0 ? 'text-red-600' : ''],
            ] as [$key, $value, $tone])
                <div class="{{ $card }}">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang($key)</p>
                    <p class="mt-1 text-xl font-bold dark:text-white {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="flex gap-4 max-lg:flex-wrap">
            <!-- Left: client info -->
            <div class="{{ $card }} flex min-w-[340px] max-w-[340px] flex-col gap-3 self-start max-lg:min-w-full max-lg:max-w-full">
                <p class="text-base font-semibold text-gray-800 dark:text-white">@lang('clients::app.form.client-info')</p>

                <dl class="flex flex-col gap-2 text-sm dark:text-gray-300">
                    @if ($client->email)
                        <div>
                            <dt class="text-xs text-gray-500">@lang('clients::app.fields.email')</dt>
                            <dd>
                                <span class="inline-flex items-center gap-1 text-brandColor" role="button" tabindex="0" title="@lang('funnel::app.copy.hint')" data-nexus-copy="{{ $client->email }}">
                                    {{ $client->email }} {!! $copyIcon !!}
                                </span>
                            </dd>
                        </div>
                    @endif

                    @if ($client->phone)
                        <div>
                            <dt class="text-xs text-gray-500">@lang('clients::app.fields.phone')</dt>
                            <dd>
                                <span class="inline-flex items-center gap-1 text-brandColor" role="button" tabindex="0" title="@lang('funnel::app.copy.hint')" data-nexus-copy="{{ $client->phone }}">
                                    {{ $client->phone }} {!! $copyIcon !!}
                                </span>
                            </dd>
                        </div>
                    @endif

                    @if ($client->address)
                        <div>
                            <dt class="text-xs text-gray-500">@lang('clients::app.fields.address')</dt>
                            <dd class="whitespace-pre-line">{{ $client->address }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs text-gray-500">@lang('clients::app.fields.onboarded-on')</dt>
                        <dd>{{ $client->onboarded_on ? $client->onboarded_on->format('j M Y') : '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500">@lang('clients::app.fields.manager')</dt>
                        <dd>{{ $client->user?->name ?? '—' }}</dd>
                    </div>

                    @if ($client->lead)
                        <div>
                            <dt class="text-xs text-gray-500">@lang('clients::app.view.lead')</dt>
                            <dd>
                                <a href="{{ route('admin.leads.view', $client->lead->id) }}" class="text-brandColor">{{ $client->lead->title }}</a>
                            </dd>
                        </div>
                    @endif

                    @if ($client->notes)
                        <div>
                            <dt class="text-xs text-gray-500">@lang('clients::app.fields.notes')</dt>
                            <dd class="whitespace-pre-line">{{ $client->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Right -->
            <div class="flex w-full flex-col gap-4">
                <!-- Contract -->
                <div class="{{ $card }}">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-base font-semibold text-gray-800 dark:text-white">@lang('clients::app.view.contract')</p>
                    </div>

                    @forelse ($contracts as $document)
                        @include('clients::partials.document-row', ['document' => $document])
                    @empty
                        <p class="text-sm text-gray-500">@lang('clients::app.view.no-contract')</p>
                    @endforelse

                    @if ($canEdit)
                        @include('clients::partials.upload-form', ['category' => 'contract'])
                    @endif
                </div>

                <!-- Invoices -->
                <div class="{{ $card }}">
                    <p class="mb-3 text-base font-semibold text-gray-800 dark:text-white">@lang('clients::app.view.invoices')</p>

                    @if ($canEdit)
                        <form
                            method="POST"
                            action="{{ route('admin.clients.invoices.store', $client->id) }}"
                            class="mb-4 grid grid-cols-1 items-end gap-3 rounded-md border border-dashed border-gray-300 p-3 md:grid-cols-5 dark:border-gray-700"
                        >
                            @csrf

                            <div>
                                <label class="{{ $label }}">@lang('clients::app.invoice.month')</label>
                                <input type="month" name="billing_month" required value="{{ old('billing_month', $nextMonth) }}" class="{{ $input }}">
                            </div>

                            <div>
                                <label class="{{ $label }}">@lang('clients::app.invoice.amount')</label>
                                <input type="number" name="amount" required min="0.01" step="0.01" value="{{ old('amount', $client->monthly_retainer) }}" class="{{ $input }}">
                            </div>

                            <div>
                                <label class="{{ $label }}">@lang('clients::app.invoice.due-date')</label>
                                <input type="date" name="due_date" required value="{{ old('due_date', now()->addDays(config('clients.default_due_days'))->format('Y-m-d')) }}" class="{{ $input }}">
                            </div>

                            <div>
                                <label class="{{ $label }}">@lang('clients::app.invoice.description')</label>
                                <input type="text" name="description" value="{{ old('description') }}" placeholder="@lang('clients::app.invoice.description-placeholder')" class="{{ $input }}">
                            </div>

                            <button type="submit" class="primary-button justify-center">
                                @lang('clients::app.invoice.create')
                            </button>

                            @if ($errors->hasAny(['billing_month', 'amount', 'due_date', 'description']))
                                <p class="text-sm text-red-600 md:col-span-5">{{ $errors->first() }}</p>
                            @endif
                        </form>
                    @endif

                    @if ($invoices->isEmpty())
                        <p class="text-sm text-gray-500">@lang('clients::app.view.no-invoices')</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm dark:text-gray-300">
                                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                                    <tr>
                                        <th class="py-2 pr-3">@lang('clients::app.invoice.number')</th>
                                        <th class="py-2 pr-3">@lang('clients::app.invoice.month')</th>
                                        <th class="py-2 pr-3 text-right">@lang('clients::app.invoice.amount')</th>
                                        <th class="py-2 pr-3">@lang('clients::app.invoice.due-date')</th>
                                        <th class="py-2 pr-3">@lang('clients::app.invoice.status')</th>
                                        <th class="py-2 text-right">@lang('clients::app.invoice.actions')</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($invoices as $invoice)
                                        @php($status = $invoice->displayStatus())

                                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                            <td class="py-2 pr-3 font-medium">{{ $invoice->number }}</td>
                                            <td class="py-2 pr-3">{{ $invoice->billing_month->format('F Y') }}</td>
                                            <td class="py-2 pr-3 text-right">{{ core()->formatBasePrice($invoice->amount) }}</td>
                                            <td class="py-2 pr-3">{{ $invoice->due_date->format('j M Y') }}</td>
                                            <td class="py-2 pr-3">
                                                <span class="rounded-xl px-2 py-0.5 text-xs font-medium {{ $statusStyles[$status] ?? '' }}">
                                                    @lang('clients::app.invoice.statuses.'.$status)
                                                </span>

                                                @if ($invoice->paid_on)
                                                    <span class="ml-1 text-xs text-gray-500">{{ $invoice->paid_on->format('j M Y') }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2">
                                                <div class="flex items-center justify-end gap-1">
                                                    <a
                                                        href="{{ route('admin.clients.invoices.pdf', [$client->id, $invoice->id]) }}"
                                                        class="rounded px-2 py-1 text-xs font-medium text-brandColor hover:bg-gray-100 dark:hover:bg-gray-800"
                                                    >
                                                        PDF
                                                    </a>

                                                    @if ($canEdit)
                                                        @foreach ($invoice->status === 'unpaid' ? ['paid', 'void'] : ['unpaid'] as $next)
                                                            <form method="POST" action="{{ route('admin.clients.invoices.update', [$client->id, $invoice->id]) }}">
                                                                @csrf
                                                                @method('PUT')
                                                                <input type="hidden" name="status" value="{{ $next }}">
                                                                <button type="submit" class="rounded px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800">
                                                                    @lang('clients::app.invoice.mark-'.$next)
                                                                </button>
                                                            </form>
                                                        @endforeach

                                                        <form
                                                            method="POST"
                                                            action="{{ route('admin.clients.invoices.delete', [$client->id, $invoice->id]) }}"
                                                            onsubmit="return confirm(@js(trans('clients::app.invoice.confirm-delete', ['number' => $invoice->number])))"
                                                        >
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="icon-delete rounded p-1 text-lg text-gray-500 hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-800" title="@lang('clients::app.invoice.delete')"></button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- Documents -->
                <div class="{{ $card }}">
                    <p class="mb-3 text-base font-semibold text-gray-800 dark:text-white">@lang('clients::app.view.documents')</p>

                    @forelse ($documents as $document)
                        @include('clients::partials.document-row', ['document' => $document])
                    @empty
                        <p class="text-sm text-gray-500">@lang('clients::app.view.no-documents')</p>
                    @endforelse

                    @if ($canEdit)
                        @include('clients::partials.upload-form', ['category' => 'other'])
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin::layouts>
