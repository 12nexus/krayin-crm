{{--
    On a won lead's view (admin.leads.view.title.after): onboard it as a client,
    or open the client it already became. Onboarding is a deliberate step taken
    by hand; nothing happens automatically when a lead is won.
--}}
@if ($lead->stage?->code === 'won' && bouncer()->hasPermission('clients'))
    @php($onboarded = \Nexus\Clients\Models\Client::where('lead_id', $lead->id)->first())

    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm dark:border-green-900 dark:bg-green-950">
        @if ($onboarded)
            <span class="text-green-800 dark:text-green-300">@lang('clients::app.lead.onboarded')</span>

            <a href="{{ route('admin.clients.view', $onboarded->id) }}" class="secondary-button">
                @lang('clients::app.lead.view-client')
            </a>
        @elseif (bouncer()->hasPermission('clients.create'))
            <span class="text-green-800 dark:text-green-300">@lang('clients::app.lead.won')</span>

            <a href="{{ route('admin.clients.create', ['lead_id' => $lead->id]) }}" class="primary-button">
                @lang('clients::app.lead.onboard')
            </a>
        @endif
    </div>
@endif
