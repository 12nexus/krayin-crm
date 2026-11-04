@php
    $input = 'w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300';
    $label = 'mb-1 block text-xs font-medium text-gray-800 dark:text-white';
    $hint = 'mt-1 block text-xs text-gray-500 dark:text-gray-400';
    $required = "after:ml-0.5 after:text-red-500 after:content-['*']";
    $requiredFields = ['client_name', 'agreement_date', 'start_date', 'duration', 'resources', 'hours_per_day', 'days_per_week', 'plan', 'monthly_charge', 'setup_fee'];

    $contractFields = app(\Nexus\Contracts\Services\ContractFields::class);
    $value = fn ($key) => old($key, $fields[$key] ?? '');

    // What the documents print from the saved fields (or the ones shown, before a first save).
    $printed = $contractFields->printable($contract && ! $fromLead ? $contractFields->current($lead, $contract) : $fields);
    $blank = '<span class="italic text-gray-400">'.e(trans('contracts::app.edit.blank')).'</span>';
    $show = fn ($text) => $text !== '' ? e($text) : $blank;

    $title = trans('contracts::app.edit.title', ['name' => $lead->title]);
@endphp

<x-admin::layouts>
    <x-slot:title>
        {{ $title }}
    </x-slot>

    <form method="POST" action="{{ route('admin.leads.contracts.update', $lead->id) }}">
        @csrf
        @method('PUT')

        <div class="flex flex-col gap-4">
            <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between gap-4 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-1">
                    <a href="{{ route('admin.leads.view', $lead->id) }}" class="text-xs text-brandColor">
                        ← @lang('contracts::app.edit.back')
                    </a>

                    <div class="text-xl font-bold dark:text-white">{{ $title }}</div>

                    <p class="max-w-3xl text-gray-500 dark:text-gray-400">@lang('contracts::app.edit.intro')</p>
                </div>

                <div class="flex items-center gap-2">
                    @if ($fromLead)
                        <a href="{{ route('admin.leads.contracts.edit', $lead->id) }}" class="transparent-button">
                            @lang('contracts::app.edit.discard')
                        </a>
                    @elseif ($contract)
                        <a href="{{ route('admin.leads.contracts.edit', [$lead->id, 'from_lead' => 1]) }}" class="transparent-button">
                            @lang('contracts::app.edit.refill')
                        </a>
                    @endif

                    <button type="submit" class="primary-button">
                        @lang('contracts::app.edit.save')
                    </button>
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($fromLead)
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    @lang('contracts::app.edit.from-lead')
                </div>
            @elseif (! $contract)
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    @lang('contracts::app.edit.unsaved')
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-base font-semibold text-gray-800 dark:text-white">@lang('contracts::app.edit.downloads')</p>

                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">@lang('contracts::app.edit.downloads-hint')</p>

                    @if ($contract)
                        <div class="flex flex-col gap-2">
                            <a href="{{ route('admin.leads.contracts.download', [$lead->id, 'nda']) }}" class="secondary-button justify-center">
                                @lang('contracts::app.panel.nda')
                            </a>

                            <a href="{{ route('admin.leads.contracts.download', [$lead->id, 'agreement']) }}" class="secondary-button justify-center">
                                @lang('contracts::app.panel.agreement')
                            </a>
                        </div>

                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            @lang('contracts::app.panel.saved', [
                                'when' => $contract->updated_at->diffForHumans(),
                                'name' => $contract->editor?->name ?? '—',
                            ])
                        </p>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-300">@lang('contracts::app.flash.save-first')</p>
                    @endif
                </div>

                <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <p class="mb-3 text-base font-semibold text-gray-800 dark:text-white">@lang('contracts::app.edit.printed')</p>

                    <dl class="flex flex-col gap-1">
                        <div><dt class="inline text-gray-500">@lang('contracts::app.printed.nda_party'):</dt> <dd class="inline">{!! $show($printed['nda_party']) !!}</dd></div>

                        <div><dt class="inline text-gray-500">@lang('contracts::app.printed.nda_title'):</dt> <dd class="inline">{!! $show($printed['nda_title']) !!}</dd></div>

                        <div><dt class="inline text-gray-500">@lang('contracts::app.printed.agreement_party'):</dt> <dd class="inline">{!! $show($printed['agreement_party']) !!}</dd></div>

                        <div><dt class="inline text-gray-500">@lang('contracts::app.printed.agreement_date'):</dt> <dd class="inline">{{ $printed['agreement_date'] }}</dd></div>

                        <div><dt class="inline text-gray-500">@lang('contracts::app.printed.term'):</dt> <dd class="inline">{{ $printed['duration'] }}: {{ $printed['start_date'] }} → {{ $printed['renewal_date'] }}</dd></div>

                        <div><dt class="inline text-gray-500">@lang('contracts::app.printed.hours'):</dt> <dd class="inline">{{ $printed['resources'] }} [{{ $printed['hours_per_day'] }} working hours a day – {{ $printed['days_per_week'] }} Days a week / {{ $printed['hours_per_week'] }} hours a week]</dd></div>

                        <div><dt class="inline text-gray-500">@lang('contracts::app.printed.charges'):</dt> <dd class="inline">{{ $printed['monthly_charge'] }} per month for {{ $printed['plan'] }} ({{ $printed['hours_per_week'] }} hours a week)</dd></div>

                        <div><dt class="inline text-gray-500">@lang('contracts::app.printed.setup_fee'):</dt> <dd class="inline">{{ $printed['setup_fee'] }}</dd></div>
                    </dl>
                </div>
            </div>

            @foreach ($definitions as $section => $sectionFields)
                <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                        @lang('contracts::app.edit.'.$section)
                    </p>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @foreach ($sectionFields as $key => [$type, $usedIn])
                            <div>
                                <label for="{{ $key }}" class="{{ $label }} {{ in_array($key, $requiredFields, true) ? $required : '' }}">
                                    @lang('contracts::app.fields.'.$key)
                                </label>

                                @switch($type)
                                    @case('date')
                                        <input type="date" id="{{ $key }}" name="{{ $key }}" value="{{ $value($key) }}" required class="{{ $input }}">
                                        @break

                                    @case('duration')
                                        <select id="{{ $key }}" name="{{ $key }}" required class="{{ $input }}">
                                            @foreach (config('contracts.durations') as $months)
                                                <option value="{{ $months }}" @selected((int) $value($key) === $months)>{{ $contractFields->durationLabel($months) }}</option>
                                            @endforeach
                                        </select>
                                        @break

                                    @case('plan')
                                        <select id="{{ $key }}" name="{{ $key }}" required class="{{ $input }}">
                                            @foreach (config('contracts.plans') as $plan)
                                                <option value="{{ $plan }}" @selected($value($key) === $plan)>{{ $plan }}</option>
                                            @endforeach
                                        </select>
                                        @break

                                    @case('number')
                                        <input
                                            type="number"
                                            id="{{ $key }}"
                                            name="{{ $key }}"
                                            value="{{ $value($key) }}"
                                            min="{{ $key === 'hours_per_day' ? '0.5' : '1' }}"
                                            step="{{ $key === 'hours_per_day' ? '0.5' : '1' }}"
                                            required
                                            class="{{ $input }}"
                                        >
                                        @break

                                    @case('money')
                                        <input type="number" id="{{ $key }}" name="{{ $key }}" value="{{ $value($key) }}" min="0" step="0.01" required class="{{ $input }}">
                                        @break

                                    @default
                                        <input
                                            type="text"
                                            id="{{ $key }}"
                                            name="{{ $key }}"
                                            value="{{ $value($key) }}"
                                            @if (in_array($key, $requiredFields, true)) required @endif
                                            class="{{ $input }}"
                                        >
                                @endswitch

                                <span class="{{ $hint }}">{{ $usedIn }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </form>
</x-admin::layouts>
