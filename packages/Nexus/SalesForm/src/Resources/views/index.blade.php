<x-admin::layouts>
    <x-slot:title>
        {{ $lead ? trans('sales_form::app.schedule.title', ['name' => $lead->person?->name ?? $lead->title]) : trans('sales_form::app.index.title') }}
    </x-slot>

    <v-sales-form></v-sales-form>

    @include('sales_form::partials.lookup-mixin')

    @include('sales_form::partials.day-plan')

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-sales-form-template"
        >
            <form
                method="POST"
                action="{{ $lead ? route('admin.sales_form.schedule.store', $lead->id) : route('admin.sales_form.store') }}"
                @submit="submitting = true"
            >
                @csrf

                <input type="hidden" name="phone" :value="phone">
                <input type="hidden" name="user_id" :value="form.user_id">

                <div class="flex flex-col gap-4">
                    <!-- Header -->
                    <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between gap-4 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        <div class="flex flex-col gap-1">
                            @if ($lead)
                                <x-admin::breadcrumbs name="leads.view" :entity="$lead" />

                                <div class="text-xl font-bold dark:text-white">
                                    @lang('sales_form::app.schedule.title', ['name' => $lead->person?->name ?? $lead->title])
                                </div>

                                <p class="text-gray-500 dark:text-gray-400">
                                    @lang('sales_form::app.schedule.description')
                                </p>
                            @else
                                <div class="text-xl font-bold dark:text-white">
                                    @lang('sales_form::app.index.title')
                                </div>

                                <p class="text-gray-500 dark:text-gray-400">
                                    @lang('sales_form::app.index.description')
                                </p>
                            @endif
                        </div>

                        <button
                            type="submit"
                            class="primary-button whitespace-nowrap"
                            :disabled="submitting"
                        >
                            <span v-if="! submitting">{{ $lead ? trans('sales_form::app.schedule.submit') : trans('sales_form::app.index.submit') }}</span>
                            <span v-else>…</span>
                        </button>
                    </div>

                    @if (session('error'))
                        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <p class="mb-1 font-semibold">Please correct the following:</p>

                            <ul class="list-inside list-disc">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @include('sales_form::partials.lookup-card')

                    <!-- ============ Lead details ============ -->
                    <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                            @lang('sales_form::app.index.lead.title')
                        </p>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- Sales executive -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.sales-executive')
                                </label>

                                @if ($canReassign)
                                    <select
                                        v-model="form.user_id"
                                        class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        @foreach ($salesUsers as $salesUser)
                                            <option value="{{ $salesUser->id }}">{{ $salesUser->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input
                                        type="text"
                                        value="{{ $lead?->user?->name ?? $currentUser->name }}"
                                        readonly
                                        class="w-full cursor-not-allowed rounded border border-gray-300 bg-gray-100 px-2.5 py-2 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-400"
                                    >

                                    @unless ($lead)
                                        <p class="mt-1 text-xs text-gray-400">
                                            @lang('sales_form::app.index.lead.sales-executive-help')
                                        </p>
                                    @endunless
                                @endif
                            </div>

                            <!-- Lead name -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 after:ml-0.5 after:text-red-500 after:content-['*'] dark:text-white">
                                    @lang('sales_form::app.index.lead.lead-name')
                                    <span v-if="autofilled.lead_name" class="ml-1 font-normal text-green-600">(@lang('sales_form::app.index.autofilled'))</span>
                                </label>

                                <input type="text" name="lead_name" v-model="form.lead_name" required
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 after:ml-0.5 after:text-red-500 after:content-['*'] dark:text-white">
                                    @lang('sales_form::app.index.lead.email')
                                    <span v-if="autofilled.email" class="ml-1 font-normal text-green-600">(@lang('sales_form::app.index.autofilled'))</span>
                                </label>

                                <input type="email" name="email" v-model="form.email" required
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>

                            <!-- Brokerage -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.brokerage')
                                    <span v-if="autofilled.brokerage" class="ml-1 font-normal text-green-600">(@lang('sales_form::app.index.autofilled'))</span>
                                </label>

                                <input type="text" name="brokerage" v-model="form.brokerage"
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>

                            <!-- Experience -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.experience')
                                </label>

                                <input type="text" name="experience_years" v-model="form.experience_years"
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>

                            <!-- City -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.city')
                                    <span v-if="autofilled.city" class="ml-1 font-normal text-green-600">(@lang('sales_form::app.index.autofilled'))</span>
                                </label>

                                <input type="text" name="city" v-model="form.city"
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>

                            <!-- State -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 after:ml-0.5 after:text-red-500 after:content-['*'] dark:text-white">
                                    @lang('sales_form::app.index.lead.state')
                                    <span v-if="autofilled.state" class="ml-1 font-normal text-green-600">(@lang('sales_form::app.index.autofilled'))</span>
                                </label>

                                <input type="text" name="state" v-model="form.state" required
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <!-- Using an assistant -->
                            <div>
                                <p class="mb-2 text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.using-assistant')
                                </p>

                                <div class="flex gap-4">
                                    <label v-for="option in ['Yes', 'No']" :key="option" class="flex cursor-pointer items-center gap-2 text-sm dark:text-gray-300">
                                        <input type="radio" name="using_assistant" :value="option" v-model="form.using_assistant" class="accent-[#004EF0]">
                                        <span v-text="option"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Assistant type -->
                            <div>
                                <p class="mb-2 text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.assistant-type')
                                </p>

                                <div class="flex flex-wrap gap-4" :class="form.using_assistant === 'Yes' ? '' : 'opacity-50'">
                                    <label v-for="option in assistantTypes" :key="option" class="flex cursor-pointer items-center gap-2 text-sm dark:text-gray-300">
                                        <input type="radio" name="assistant_type" :value="option" v-model="form.assistant_type" :disabled="form.using_assistant !== 'Yes'" class="accent-[#004EF0]">
                                        <span v-text="option"></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Willingness -->
                            <div>
                                <p class="mb-2 text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.willingness')
                                </p>

                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-500">@lang('sales_form::app.index.lead.willingness-low')</span>

                                    <label v-for="score in [1, 2, 3]" :key="score" class="flex cursor-pointer flex-col items-center gap-1 text-sm dark:text-gray-300">
                                        <span v-text="score"></span>
                                        <input type="radio" name="willingness" :value="score" v-model="form.willingness" class="accent-[#004EF0]">
                                    </label>

                                    <span class="text-xs text-gray-500">@lang('sales_form::app.index.lead.willingness-high')</span>
                                </div>
                            </div>
                        </div>

                        @include('sales_form::partials.engagement-fields')
                    </div>

                    @include('sales_form::partials.meeting-fields')
                </div>
            </form>
        </script>

        <script type="module">
            app.component('v-sales-form', {
                template: '#v-sales-form-template',

                mixins: [window.nexusLookupMixin],

                data() {
                    const prefill = @json($prefill);

                    const pick = (field, fallback) => prefill[field] ?? fallback;

                    return {
                        phoneInput: @json(old('phone')) ?? pick('phone', ''),

                        ignoreLeadId: @json($lead?->id),

                        submitting: false,

                        meetingError: null,

                        assistantTypes: ['Remote VA', 'In-house Assistant', 'Other'],

                        form: {
                            user_id: @json($canReassign ? old('user_id') : null) ?? pick('user_id', @json($currentUser->id)),
                            lead_name: @json(old('lead_name')) ?? pick('lead_name', ''),
                            email: @json(old('email')) ?? pick('email', ''),
                            brokerage: @json(old('brokerage')) ?? pick('brokerage', ''),
                            experience_years: @json(old('experience_years')) ?? pick('experience_years', ''),
                            city: @json(old('city')) ?? pick('city', ''),
                            state: @json(old('state')) ?? pick('state', ''),
                            using_assistant: @json(old('using_assistant')) ?? pick('using_assistant', 'No'),
                            assistant_type: @json(old('assistant_type')) ?? pick('assistant_type', ''),
                            willingness: parseInt(@json(old('willingness')) ?? pick('willingness', 2)),
                            engagement_type: @json(old('engagement_type')) ?? pick('engagement_type', ''),
                            lead_value: @json(old('lead_value')) ?? pick('lead_value', @json($minimumValue)),
                            meeting_date: @json(old('meeting_date', '')),
                            meeting_time: @json(old('meeting_time', '')),
                            timezone: @json(old('timezone', '')),
                            additional_information: @json(old('additional_information', '')),
                        },
                    };
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
