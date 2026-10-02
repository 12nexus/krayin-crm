<x-admin::layouts>
    <x-slot:title>
        @lang('sales_form::app.google-calendar.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1">
                <x-admin::breadcrumbs name="settings" />

                <div class="text-xl font-bold dark:text-white">
                    @lang('sales_form::app.google-calendar.title')
                </div>

                <p class="max-w-3xl text-gray-500 dark:text-gray-400">
                    @lang('sales_form::app.google-calendar.description')
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-4 rounded-lg border border-gray-300 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div>
                <p class="font-semibold text-gray-800 dark:text-white">@lang('sales_form::app.google-calendar.calendar')</p>
                <p class="text-gray-600 dark:text-gray-300">{{ $calendarId ?: '—' }}</p>
            </div>

            <div>
                <p class="font-semibold text-gray-800 dark:text-white">@lang('sales_form::app.google-calendar.account')</p>

                @if ($account)
                    <p class="text-gray-600 dark:text-gray-300">
                        {{ $account->email }}
                        <span class="text-gray-400">· @lang('sales_form::app.google-calendar.connected-since', ['date' => \Carbon\Carbon::parse($account->created_at)->format('j M Y')])</span>
                    </p>
                @else
                    <p class="text-gray-600 dark:text-gray-300">@lang('sales_form::app.google-calendar.not-connected')</p>
                @endif
            </div>

            @if ($configured)
                <p class="text-gray-500 dark:text-gray-400">@lang('sales_form::app.google-calendar.requirement')</p>

                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.settings.google_calendar.connect') }}">
                        @csrf

                        <button type="submit" class="primary-button">
                            {{ $account ? trans('sales_form::app.google-calendar.reconnect') : trans('sales_form::app.google-calendar.connect') }}
                        </button>
                    </form>

                    @if ($account)
                        <form method="POST" action="{{ route('admin.settings.google_calendar.disconnect') }}">
                            @csrf
                            @method('DELETE')

                            <button type="submit" class="secondary-button">@lang('sales_form::app.google-calendar.disconnect')</button>
                        </form>
                    @endif
                </div>
            @else
                <p class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-amber-800">
                    @lang('sales_form::app.google-calendar.not-configured')
                </p>
            @endif

            <div>
                <p class="font-semibold text-gray-800 dark:text-white">@lang('sales_form::app.google-calendar.redirect-uri')</p>
                <code class="select-all text-gray-600 dark:text-gray-300">{{ $redirectUri }}</code>
            </div>
        </div>
    </div>
</x-admin::layouts>
