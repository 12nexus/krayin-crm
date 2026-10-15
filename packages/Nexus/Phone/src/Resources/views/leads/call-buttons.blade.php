{{--
    Call buttons under the lead's phone numbers (admin.leads.view.person.contact_numbers.after).
    The call runs in the phone dock, tagged with this lead so it is logged here.
--}}
@if (config('phone.url') && $lead->person && count($lead->person->contact_numbers ?? []))
    <div class="flex flex-wrap gap-1.5 pt-1">
        @foreach ($lead->person->contact_numbers as $contactNumber)
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md bg-green-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-green-700"
                title="@lang('phone::app.lead.call-number', ['number' => $contactNumber['value'], 'from' => config('phone.number')])"
                onclick="window.NexusPhone && window.NexusPhone.dial(@js($contactNumber['value']), { id: {{ $lead->id }}, title: @js($lead->title) })"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg>
                @lang('phone::app.lead.call') {{ $contactNumber['value'] }}
            </button>
        @endforeach
    </div>
@endif
