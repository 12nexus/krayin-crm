{{--
    Engagement type and the estimated monthly value. Picking a type fills in its
    price (sales_form.lead_values), which the rep can change; nothing below the
    minimum is accepted.

    Rendered inside a Vue template whose component has `form.engagement_type` and
    `form.lead_value`. Needs $engagementTypes, $leadValues and $minimumValue.
--}}
<div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <p class="mb-2 text-xs font-medium text-gray-800 dark:text-white">
            @lang('sales_form::app.index.lead.engagement')
        </p>

        <div class="flex flex-wrap gap-4">
            @foreach ($engagementTypes as $engagementType)
                <label class="flex cursor-pointer items-center gap-2 text-sm dark:text-gray-300">
                    <input type="radio" name="engagement_type" value="{{ $engagementType }}" v-model="form.engagement_type" @change="form.lead_value = {{ (float) ($leadValues[$engagementType] ?? $defaultValue) }}" class="accent-[#004EF0]">
                    <span>{{ $engagementType }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div>
        <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
            @lang('sales_form::app.index.lead.lead-value')
        </label>

        <div class="flex items-center rounded border border-gray-300 dark:border-gray-800">
            <span class="px-2.5 text-sm text-gray-500">$</span>

            <input
                type="number"
                name="lead_value"
                v-model="form.lead_value"
                min="{{ $minimumValue }}"
                step="1"
                class="w-full rounded-r border-0 border-l border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
            >
        </div>

        <p class="mt-1 text-xs text-gray-400">
            @lang('sales_form::app.index.lead.lead-value-help', ['prices' => collect($leadValues)->map(fn ($price, $type) => $type.' $'.number_format($price))->implode(', '), 'min' => number_format($minimumValue)])
        </p>

        @error('lead_value')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
