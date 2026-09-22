@php
    $editing = $client->exists;
    $input = 'w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300';
    $label = 'mb-1 block text-xs font-medium text-gray-800 dark:text-white';
    $required = "after:ml-0.5 after:text-red-500 after:content-['*']";
@endphp

<x-admin::layouts>
    <x-slot:title>
        {{ $editing ? trans('clients::app.form.edit-title', ['name' => $client->name]) : trans('clients::app.form.create-title') }}
    </x-slot>

    <form
        method="POST"
        action="{{ $editing ? route('admin.clients.update', $client->id) : route('admin.clients.store') }}"
    >
        @csrf

        @if ($editing)
            @method('PUT')
        @endif

        <input type="hidden" name="lead_id" value="{{ old('lead_id', $client->lead_id) }}">
        <input type="hidden" name="person_id" value="{{ old('person_id', $client->person_id) }}">

        <div class="flex flex-col gap-4">
            <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between gap-4 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-1">
                    <a href="{{ route('admin.clients.index') }}" class="text-xs text-brandColor">
                        ← @lang('clients::app.index.title')
                    </a>

                    <div class="text-xl font-bold dark:text-white">
                        {{ $editing ? trans('clients::app.form.edit-title', ['name' => $client->name]) : trans('clients::app.form.create-title') }}
                    </div>

                    @if (! $editing && $client->lead_id)
                        <p class="text-gray-500 dark:text-gray-400">
                            @lang('clients::app.form.from-lead')
                        </p>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <a
                        href="{{ $editing ? route('admin.clients.view', $client->id) : route('admin.clients.index') }}"
                        class="transparent-button"
                    >
                        @lang('clients::app.form.cancel')
                    </a>

                    <button type="submit" class="primary-button">
                        @lang('clients::app.form.save')
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

            <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('clients::app.form.client-info')
                </p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="{{ $label }} {{ $required }}">@lang('clients::app.fields.name')</label>
                        <input type="text" name="name" value="{{ old('name', $client->name) }}" required class="{{ $input }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">@lang('clients::app.fields.company')</label>
                        <input type="text" name="company" value="{{ old('company', $client->company) }}" class="{{ $input }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">@lang('clients::app.fields.email')</label>
                        <input type="email" name="email" value="{{ old('email', $client->email) }}" class="{{ $input }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">@lang('clients::app.fields.phone')</label>
                        <input type="text" name="phone" value="{{ old('phone', $client->phone) }}" class="{{ $input }}">
                    </div>

                    <div class="md:col-span-2">
                        <label class="{{ $label }}">@lang('clients::app.fields.address')</label>
                        <textarea name="address" rows="2" class="{{ $input }}">{{ old('address', $client->address) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('clients::app.form.engagement')
                </p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="{{ $label }}">@lang('clients::app.fields.engagement')</label>
                        <select name="engagement_type" class="{{ $input }}">
                            <option value="">—</option>
                            @foreach (config('clients.engagement_types') as $type)
                                <option value="{{ $type }}" @selected(old('engagement_type', $client->engagement_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="{{ $label }}">@lang('clients::app.fields.retainer')</label>
                        <input type="number" step="0.01" min="0" name="monthly_retainer" value="{{ old('monthly_retainer', $client->monthly_retainer) }}" class="{{ $input }}">
                    </div>

                    <div>
                        <label class="{{ $label }}">@lang('clients::app.fields.onboarded-on')</label>
                        <input type="date" name="onboarded_on" value="{{ old('onboarded_on', optional($client->onboarded_on)->format('Y-m-d') ?? $client->onboarded_on) }}" class="{{ $input }}">
                    </div>

                    <div>
                        <label class="{{ $label }} {{ $required }}">@lang('clients::app.fields.status')</label>
                        <select name="status" required class="{{ $input }}">
                            @foreach (config('clients.statuses') as $value => $statusLabel)
                                <option value="{{ $value }}" @selected(old('status', $client->status) === $value)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="{{ $label }}">@lang('clients::app.fields.manager')</label>
                        <select name="user_id" class="{{ $input }}">
                            <option value="">—</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((int) old('user_id', $client->user_id) === $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="{{ $label }}">@lang('clients::app.fields.notes')</label>
                    <textarea name="notes" rows="4" class="{{ $input }}">{{ old('notes', $client->notes) }}</textarea>
                </div>
            </div>
        </div>
    </form>
</x-admin::layouts>
