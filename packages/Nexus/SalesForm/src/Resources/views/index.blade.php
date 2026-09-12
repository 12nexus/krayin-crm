<x-admin::layouts>
    <x-slot:title>
        @lang('sales_form::app.index.title')
    </x-slot>

    <v-sales-form></v-sales-form>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-sales-form-template"
        >
            <form
                method="POST"
                action="{{ route('admin.sales_form.store') }}"
                @submit="submitting = true"
            >
                @csrf

                <input type="hidden" name="phone" :value="phone">
                <input type="hidden" name="user_id" :value="form.user_id">

                <div class="flex flex-col gap-4">
                    <!-- Header -->
                    <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between gap-4 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        <div class="flex flex-col gap-1">
                            <div class="text-xl font-bold dark:text-white">
                                @lang('sales_form::app.index.title')
                            </div>

                            <p class="text-gray-500 dark:text-gray-400">
                                @lang('sales_form::app.index.description')
                            </p>
                        </div>

                        <button
                            type="submit"
                            class="primary-button whitespace-nowrap"
                            :disabled="submitting"
                        >
                            <span v-if="! submitting">@lang('sales_form::app.index.submit')</span>
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

                    <!-- ============ Lead identification ============ -->
                    <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <p class="text-base font-semibold text-gray-800 dark:text-white">
                                @lang('sales_form::app.index.lookup.title')
                            </p>

                            <span class="text-xs text-gray-400">
                                @lang('sales_form::app.index.lookup.agents-on-file', ['count' => number_format($agentsOnFile)])
                            </span>
                        </div>

                        <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                            @lang('sales_form::app.index.lookup.hint')
                        </p>

                        <div class="flex flex-wrap items-end gap-3">
                            <div class="min-w-[260px] flex-1">
                                <label class="mb-1 block text-xs font-medium text-gray-800 after:ml-0.5 after:text-red-500 after:content-['*'] dark:text-white">
                                    @lang('sales_form::app.index.lookup.phone')
                                </label>

                                <input
                                    type="tel"
                                    v-model="phoneInput"
                                    placeholder="(555) 123-4567"
                                    autocomplete="off"
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                >
                            </div>

                            <button
                                v-if="showFetchButton"
                                type="button"
                                class="secondary-button whitespace-nowrap"
                                @click="fetchDetails"
                            >
                                <span v-if="lookedUpPhone">@lang('sales_form::app.index.lookup.refetch')</span>
                                <span v-else>@lang('sales_form::app.index.lookup.fetch')</span>
                            </button>
                        </div>

                        <!-- Lookup status -->
                        <div class="mt-3 text-sm">
                            <p
                                v-if="status === 'loading'"
                                class="text-gray-500"
                            >
                                @lang('sales_form::app.index.lookup.searching')
                            </p>

                            <p
                                v-else-if="status === 'incomplete'"
                                class="text-gray-500"
                            >
                                @lang('sales_form::app.index.lookup.incomplete')
                            </p>

                            <div
                                v-else-if="status === 'found'"
                                class="rounded-md border border-green-300 bg-green-50 p-3 dark:border-green-900 dark:bg-green-950"
                            >
                                <p class="mb-2 font-semibold text-green-800 dark:text-green-300">
                                    ✓ @lang('sales_form::app.index.lookup.matched')
                                </p>

                                <dl class="grid grid-cols-1 gap-x-6 gap-y-1 text-gray-700 sm:grid-cols-2 dark:text-gray-300">
                                    <div class="flex gap-2">
                                        <dt class="font-medium">@lang('sales_form::app.index.lookup.verified-name'):</dt>
                                        <dd v-text="agent.full_name"></dd>
                                    </div>

                                    <div class="flex gap-2">
                                        <dt class="font-medium">@lang('sales_form::app.index.lookup.verified-email'):</dt>
                                        <dd v-text="agent.email || 'Not on file'"></dd>
                                    </div>

                                    <div class="flex gap-2">
                                        <dt class="font-medium">@lang('sales_form::app.index.lookup.location'):</dt>
                                        <dd v-text="location"></dd>
                                    </div>

                                    <div class="flex gap-2">
                                        <dt class="font-medium">@lang('sales_form::app.index.lookup.code'):</dt>
                                        <dd class="truncate" v-text="agent.vendor_lead_code || 'Not on file'"></dd>
                                    </div>

                                    <div class="flex gap-2 sm:col-span-2">
                                        <dt class="shrink-0 font-medium">@lang('sales_form::app.index.lookup.license'):</dt>
                                        <dd v-text="agent.license_details || 'Not on file'"></dd>
                                    </div>
                                </dl>
                            </div>

                            <div
                                v-else-if="status === 'notfound'"
                                class="rounded-md border border-amber-300 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950"
                            >
                                <p class="font-semibold text-amber-800 dark:text-amber-300">
                                    @lang('sales_form::app.index.lookup.not-matched')
                                </p>

                                <p class="text-amber-700 dark:text-amber-400">
                                    @lang('sales_form::app.index.lookup.not-matched-help')
                                </p>
                            </div>
                        </div>
                    </div>

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
                                        value="{{ $currentUser->name }}"
                                        readonly
                                        class="w-full cursor-not-allowed rounded border border-gray-300 bg-gray-100 px-2.5 py-2 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-400"
                                    >

                                    <p class="mt-1 text-xs text-gray-400">
                                        @lang('sales_form::app.index.lead.sales-executive-help')
                                    </p>
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
                    </div>

                    <!-- ============ Meeting information ============ -->
                    <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                            @lang('sales_form::app.index.meeting.title')
                        </p>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 after:ml-0.5 after:text-red-500 after:content-['*'] dark:text-white">
                                    @lang('sales_form::app.index.meeting.date')
                                </label>

                                <input type="date" name="meeting_date" v-model="form.meeting_date" required
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 after:ml-0.5 after:text-red-500 after:content-['*'] dark:text-white">
                                    @lang('sales_form::app.index.meeting.time')
                                </label>

                                <input type="time" name="meeting_time" v-model="form.meeting_time" required
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="mb-2 text-xs font-medium text-gray-800 after:ml-0.5 after:text-red-500 after:content-['*'] dark:text-white">
                                @lang('sales_form::app.index.meeting.timezone')
                            </p>

                            <div class="flex flex-wrap gap-4">
                                @foreach ($timezones as $timezone)
                                    <label class="flex cursor-pointer items-center gap-2 text-sm dark:text-gray-300">
                                        <input type="radio" name="timezone" value="{{ $timezone }}" v-model="form.timezone" required class="accent-[#004EF0]">
                                        <span>{{ $timezone }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                @lang('sales_form::app.index.meeting.additional')
                            </label>

                            <textarea name="additional_information" v-model="form.additional_information" rows="3"
                                class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"></textarea>
                        </div>
                    </div>
                </div>
            </form>
        </script>

        <script type="module">
            app.component('v-sales-form', {
                template: '#v-sales-form-template',

                data() {
                    return {
                        phoneInput: @json(old('phone', '')),

                        // Normalized number of the last completed lookup, so we can
                        // tell when the rep has edited it since.
                        lookedUpPhone: null,

                        status: 'idle',

                        agent: null,

                        submitting: false,

                        assistantTypes: ['Remote VA', 'In-house Assistant', 'Other'],

                        // Fields the ViciDial match filled in, shown as a hint and
                        // safe to overwrite on a later lookup.
                        autofilled: {},

                        form: {
                            user_id: @json($canReassign ? old('user_id', $currentUser->id) : $currentUser->id),
                            lead_name: @json(old('lead_name', '')),
                            email: @json(old('email', '')),
                            brokerage: @json(old('brokerage', '')),
                            experience_years: @json(old('experience_years', '')),
                            city: @json(old('city', '')),
                            state: @json(old('state', '')),
                            using_assistant: @json(old('using_assistant', 'No')),
                            assistant_type: @json(old('assistant_type', '')),
                            willingness: @json((int) old('willingness', 2)),
                            meeting_date: @json(old('meeting_date', '')),
                            meeting_time: @json(old('meeting_time', '')),
                            timezone: @json(old('timezone', '')),
                            additional_information: @json(old('additional_information', '')),
                        },
                    };
                },

                computed: {
                    /**
                     * Digits only, country code dropped — mirrors the server's
                     * normalization so both sides agree on what "changed" means.
                     */
                    phone() {
                        let digits = (this.phoneInput || '').replace(/\D+/g, '');

                        if (digits.length === 11 && digits.startsWith('1')) {
                            digits = digits.substring(1);
                        }

                        return digits;
                    },

                    phoneComplete() {
                        return this.phone.length === 10;
                    },

                    /**
                     * Offer the button only once the number is complete and differs
                     * from whatever was last looked up.
                     */
                    showFetchButton() {
                        return this.phoneComplete
                            && this.phone !== this.lookedUpPhone
                            && this.status !== 'loading';
                    },

                    location() {
                        if (! this.agent) {
                            return '';
                        }

                        return [this.agent.city, this.agent.state, this.agent.country]
                            .filter(Boolean)
                            .join(', ');
                    },
                },

                watch: {
                    phoneInput() {
                        clearTimeout(this.debounce);

                        if (! this.phoneComplete) {
                            // Anything typed after a lookup invalidates the match.
                            if (this.status === 'found' || this.status === 'notfound') {
                                this.status = 'incomplete';
                                this.agent = null;
                            }

                            return;
                        }

                        // First complete number resolves on its own; after that the
                        // rep asks for it explicitly with the button.
                        if (this.lookedUpPhone === null) {
                            this.debounce = setTimeout(() => this.fetchDetails(), 400);
                        }
                    },
                },

                methods: {
                    fetchDetails() {
                        if (! this.phoneComplete) {
                            this.status = 'incomplete';

                            return;
                        }

                        this.status = 'loading';

                        this.$axios.get("{{ route('admin.sales_form.lookup') }}", {
                                params: { phone: this.phone },
                            })
                            .then(({ data }) => {
                                this.lookedUpPhone = this.phone;

                                if (data.found) {
                                    this.agent = data.agent;
                                    this.status = 'found';
                                    this.applyAgent(data.agent);
                                } else {
                                    this.agent = null;
                                    this.status = 'notfound';
                                    this.clearAutofilled();
                                }
                            })
                            .catch(() => {
                                this.status = 'notfound';
                                this.agent = null;
                                this.clearAutofilled();
                            });
                    },

                    /**
                     * Fill only what the rep has not typed themselves, or what a
                     * previous lookup filled. A manual correction is never clobbered.
                     */
                    applyAgent(agent) {
                        const mapping = {
                            lead_name: agent.full_name,
                            email: agent.email,
                            brokerage: agent.brokerage,
                            city: agent.city,
                            state: agent.state,
                        };

                        Object.entries(mapping).forEach(([field, value]) => {
                            if (! value) {
                                return;
                            }

                            const untouched = ! this.form[field] || this.autofilled[field];

                            if (untouched) {
                                this.form[field] = value;
                                this.autofilled[field] = true;
                            }
                        });
                    },

                    /**
                     * No match: drop values a previous match supplied so nothing
                     * stale is submitted, and let the rep type them in.
                     */
                    clearAutofilled() {
                        Object.keys(this.autofilled).forEach((field) => {
                            this.form[field] = '';
                        });

                        this.autofilled = {};
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
