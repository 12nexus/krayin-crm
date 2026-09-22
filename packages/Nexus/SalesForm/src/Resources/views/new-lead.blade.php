<x-admin::layouts>
    <x-slot:title>
        @lang('sales_form::app.new-lead.title')
    </x-slot>

    <v-new-lead-form></v-new-lead-form>

    @include('sales_form::partials.lookup-mixin')

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-new-lead-form-template"
        >
            <form
                method="POST"
                action="{{ route('admin.leads.new_lead.store') }}"
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
                                @lang('sales_form::app.new-lead.title')
                            </div>

                            <p class="text-gray-500 dark:text-gray-400">
                                @lang('sales_form::app.new-lead.description')
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a
                                href="{{ route('admin.leads.index') }}"
                                class="transparent-button whitespace-nowrap"
                            >
                                @lang('sales_form::app.new-lead.cancel')
                            </a>

                            <button
                                type="submit"
                                class="primary-button whitespace-nowrap"
                                :disabled="submitting"
                            >
                                <span v-if="! submitting">@lang('sales_form::app.new-lead.submit')</span>
                                <span v-else>…</span>
                            </button>
                        </div>
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

                    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                        @lang('sales_form::app.new-lead.meeting-hint', ['url' => route('admin.sales_form.index')])
                        <a href="{{ route('admin.sales_form.index') }}" class="font-semibold underline">@lang('sales_form::app.new-lead.meeting-link')</a>
                    </div>

                    @include('sales_form::partials.lookup-card')

                    <!-- ============ Lead details ============ -->
                    <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                            @lang('sales_form::app.index.lead.title')
                        </p>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- Sales owner -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 after:ml-0.5 after:text-red-500 after:content-['*'] dark:text-white">
                                    @lang('sales_form::app.new-lead.owner')
                                </label>

                                @if ($canReassign)
                                    <select
                                        v-model="form.user_id"
                                        required
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

                            <!-- Name -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 after:ml-0.5 after:text-red-500 after:content-['*'] dark:text-white">
                                    @lang('sales_form::app.new-lead.name')
                                    <span v-if="autofilled.lead_name" class="ml-1 font-normal text-green-600">(@lang('sales_form::app.index.autofilled'))</span>
                                </label>

                                <input type="text" name="lead_name" v-model="form.lead_name" required
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.email')
                                    <span v-if="autofilled.email" class="ml-1 font-normal text-green-600">(@lang('sales_form::app.index.autofilled'))</span>
                                </label>

                                <input type="email" name="email" v-model="form.email"
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>

                            <!-- Note -->
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.new-lead.note')
                                </label>

                                <textarea name="note" v-model="form.note" rows="3"
                                    placeholder="@lang('sales_form::app.new-lead.note-placeholder')"
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- ============ Optional details ============ -->
                    <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-base font-semibold text-gray-800 dark:text-white">
                            @lang('sales_form::app.new-lead.optional')
                        </p>

                        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                            @lang('sales_form::app.new-lead.optional-hint')
                        </p>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.brokerage')
                                    <span v-if="autofilled.brokerage" class="ml-1 font-normal text-green-600">(@lang('sales_form::app.index.autofilled'))</span>
                                </label>

                                <input type="text" name="brokerage" v-model="form.brokerage"
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.city')
                                    <span v-if="autofilled.city" class="ml-1 font-normal text-green-600">(@lang('sales_form::app.index.autofilled'))</span>
                                </label>

                                <input type="text" name="city" v-model="form.city"
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">
                                    @lang('sales_form::app.index.lead.state')
                                    <span v-if="autofilled.state" class="ml-1 font-normal text-green-600">(@lang('sales_form::app.index.autofilled'))</span>
                                </label>

                                <input type="text" name="state" v-model="form.state"
                                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            </div>
                        </div>

                        @include('sales_form::partials.engagement-fields')
                    </div>
                </div>
            </form>
        </script>

        <script type="module">
            app.component('v-new-lead-form', {
                template: '#v-new-lead-form-template',

                mixins: [window.nexusLookupMixin],

                data() {
                    return {
                        phoneInput: @json(old('phone', '')),

                        submitting: false,

                        form: {
                            user_id: @json($canReassign ? old('user_id', $currentUser->id) : $currentUser->id),
                            lead_name: @json(old('lead_name', '')),
                            email: @json(old('email', '')),
                            note: @json(old('note', '')),
                            brokerage: @json(old('brokerage', '')),
                            city: @json(old('city', '')),
                            state: @json(old('state', '')),
                            engagement_type: @json(old('engagement_type', '')),
                            lead_value: @json(old('lead_value', $minimumValue)),
                        },
                    };
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
