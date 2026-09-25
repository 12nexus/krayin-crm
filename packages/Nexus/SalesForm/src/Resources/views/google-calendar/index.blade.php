@php
    $card = 'box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900';
    $statusStyles = ['active' => 'bg-green-100 text-green-700', 'held' => 'bg-gray-200 text-gray-600', 'cancelled' => 'bg-amber-100 text-amber-800', 'failed' => 'bg-red-100 text-red-700'];
    $roleOk = in_array($accessRole, ['owner', 'writer'], true);
@endphp

<x-admin::layouts>
    <x-slot:title>
        @lang('sales_form::app.google.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1">
                <a href="{{ route('admin.settings.index') }}" class="text-xs text-brandColor">← @lang('admin::app.layouts.settings')</a>

                <div class="text-xl font-bold dark:text-white">@lang('sales_form::app.google.title')</div>

                <p class="max-w-3xl text-gray-500 dark:text-gray-400">@lang('sales_form::app.google.description')</p>
            </div>
        </div>

        <div class="{{ $card }} flex flex-col gap-3 text-sm dark:text-gray-300">
            @if (! $configured)
                <p class="text-amber-700">@lang('sales_form::app.google.not-configured')</p>
            @elseif (! $calendarId)
                <p class="text-amber-700">@lang('sales_form::app.google.no-calendar')</p>
            @elseif ($account && $account->status === 'reconnect')
                <p class="font-semibold text-red-700">@lang('sales_form::app.google.needs-reconnect')</p>
                <p class="text-gray-500">@lang('sales_form::app.google.connected-as', ['email' => $account->email])</p>
            @elseif ($connected)
                <p class="text-base font-semibold text-green-700">
                    ✓ @lang('sales_form::app.google.connected-as', ['email' => $account->email])
                    <span class="font-normal text-gray-500">@lang('sales_form::app.google.connected-since', ['date' => \Carbon\Carbon::parse($account->created_at)->format('j M Y')])</span>
                </p>

                @if ($accessError)
                    <p class="text-red-700">{{ $accessError }}</p>
                @elseif ($accessRole)
                    <p @class(['text-green-700' => $roleOk, 'text-red-700' => ! $roleOk])>
                        @lang('sales_form::app.google.role', ['role' => $roleOk ? trans('sales_form::app.google.role-ok') : $accessRole.', '.trans('sales_form::app.google.role-bad')])
                    </p>
                @endif
            @else
                <p>@lang('sales_form::app.google.not-connected')</p>
            @endif

            @if ($configured && $calendarId)
                <div class="flex flex-wrap items-center gap-2">
                    @if (! $connected)
                        <a href="{{ route('admin.settings.google_calendar.connect') }}" class="primary-button">
                            {{ $account ? trans('sales_form::app.google.reconnect') : trans('sales_form::app.google.connect') }}
                        </a>
                    @endif

                    @if ($account)
                        <form method="POST" action="{{ route('admin.settings.google_calendar.disconnect') }}">
                            @csrf
                            <button type="submit" class="secondary-button">@lang('sales_form::app.google.disconnect')</button>
                        </form>
                    @endif

                    @if ($connected)
                        <form method="POST" action="{{ route('admin.settings.google_calendar.link_existing') }}">
                            @csrf
                            <button type="submit" class="secondary-button" title="@lang('sales_form::app.google.link-help')">@lang('sales_form::app.google.link-existing')</button>
                        </form>
                    @endif
                </div>

                @if ($connected)
                    <p class="text-xs text-gray-500">@lang('sales_form::app.google.link-help')</p>
                @endif
            @endif

            <p class="text-xs text-gray-400">@lang('sales_form::app.google.redirect-uri'): <code>{{ $redirectUri }}</code></p>
        </div>

        <div class="{{ $card }}">
            <p class="mb-3 text-base font-semibold text-gray-800 dark:text-white">@lang('sales_form::app.google.recent')</p>

            @if ($recent->isEmpty())
                <p class="text-sm text-gray-500">@lang('sales_form::app.google.none-yet')</p>
            @else
                <table class="w-full text-left text-sm dark:text-gray-300">
                    <tbody>
                        @foreach ($recent as $row)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="py-2 pr-3">
                                    <a href="{{ route('admin.leads.view', $row->lead_id) }}" class="text-brandColor">{{ $row->client ?: '#'.$row->lead_id }}</a>
                                </td>
                                <td class="py-2 pr-3">
                                    <span class="rounded-xl px-2 py-0.5 text-xs font-medium {{ $statusStyles[$row->status] ?? '' }}">
                                        {{ trans('sales_form::app.google.statuses')[$row->status] ?? $row->status }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3 text-xs text-gray-500">{{ \Carbon\Carbon::parse($row->updated_at)->format('j M, H:i') }} UTC</td>
                                <td class="py-2 pr-3 text-xs text-red-700">{{ $row->status === 'failed' ? \Illuminate\Support\Str::limit($row->last_error, 120) : '' }}</td>
                                <td class="py-2 text-right">
                                    @if ($row->html_link)
                                        <a href="{{ $row->html_link }}" target="_blank" rel="noopener" class="text-xs font-medium text-brandColor">@lang('sales_form::app.google.open')</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-admin::layouts>
