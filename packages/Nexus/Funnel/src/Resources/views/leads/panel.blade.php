{{--
    Funnel panel under the lead title (admin.leads.view.title.after): the lead's
    current meeting in the agent's own timezone, and the moves open to it at its
    stage. Meetings are booked with the sales form's own meeting fields.
--}}
@php
    $funnel = app(\Nexus\Funnel\Services\Funnel::class);
    $fields = app(\Nexus\SalesForm\Services\LeadFields::class)->get($lead->id);

    $archived = $funnel->isArchived($lead);
    $stage = $funnel->stageCode($lead);
    $meeting = $funnel->currentMeeting($lead, $fields['meeting_timezone'] ?? null);

    $validity = $fields['lead_validity'] ?? config('funnel.validity.pending');
    $isValid = $validity === config('funnel.validity.valid');
    $appeared = $fields['meeting_appeared'] ?? null;

    $canEdit = bouncer()->hasPermission('leads.edit');
    $canInvalidate = ! $archived && in_array($stage, config('funnel.invalidatable'), true);

    $meetingButton = match (true) {
        $archived || in_array($stage, ['won', 'lost', 'new'], true) => null,
        $stage === 'no-show'           => trans('funnel::app.panel.reschedule'),
        $stage === 'meeting-scheduled' => trans('funnel::app.panel.move-meeting'),
        default                        => trans('funnel::app.panel.follow-up-meeting'),
    };
@endphp

<div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            @lang('funnel::app.panel.meeting')
        </span>

        <div class="flex flex-wrap gap-1">
            @if ($archived)
                <span class="rounded-xl bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">@lang('funnel::app.panel.archived')</span>
            @elseif ($isValid)
                <span class="rounded-xl bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">✓ @lang('funnel::app.panel.valid')</span>
            @endif

            @if ($appeared && $appeared !== config('funnel.appeared.pending') && ! $archived)
                <span class="rounded-xl bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $appeared }}</span>
            @endif
        </div>
    </div>

    @if ($meeting)
        <div class="flex items-center gap-1.5 text-sm font-semibold text-gray-800 dark:text-white">
            <span class="icon-calendar text-lg"></span>

            <span>{{ $meeting['label'] }}</span>
        </div>

        @if ($meeting['done'])
            <p class="text-xs text-gray-500">@lang('funnel::app.panel.meeting-closed')</p>
        @elseif (! $meeting['upcoming'])
            <p class="text-xs text-amber-700">@lang('funnel::app.panel.meeting-passed')</p>
        @endif
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">@lang('funnel::app.panel.no-meeting')</p>
    @endif

    @if ($canEdit)
        <div class="flex flex-wrap gap-2 pt-1">
            @if ($archived)
                <form method="POST" action="{{ route('admin.leads.funnel.restore', $lead->id) }}">
                    @csrf
                    <button type="submit" class="secondary-button">@lang('funnel::app.panel.restore')</button>
                </form>
            @endif

            @if ($stage === 'new' && ! $archived && bouncer()->hasPermission('sales_form'))
                <a href="{{ route('admin.sales_form.schedule', $lead->id) }}" class="primary-button">
                    @lang('funnel::app.panel.schedule')
                </a>
            @endif

            @if ($stage === 'meeting-scheduled' && ! $archived)
                @unless ($isValid)
                    <form method="POST" action="{{ route('admin.leads.funnel.valid', $lead->id) }}">
                        @csrf
                        <button type="submit" class="secondary-button !border-green-600 !text-green-700">✓ @lang('funnel::app.panel.mark-valid')</button>
                    </form>
                @endunless

                <form method="POST" action="{{ route('admin.leads.funnel.held', $lead->id) }}">
                    @csrf
                    <button type="submit" class="secondary-button">@lang('funnel::app.panel.held')</button>
                </form>

                <form method="POST" action="{{ route('admin.leads.funnel.no_show', $lead->id) }}">
                    @csrf
                    <button type="submit" class="secondary-button">@lang('funnel::app.panel.no-show')</button>
                </form>
            @endif

            @if ($meetingButton)
                <button
                    type="button"
                    @class(['primary-button' => $stage === 'no-show', 'secondary-button' => $stage !== 'no-show'])
                    @click="$emitter.emit('nexus-open-meeting-modal')"
                >
                    {{ $meetingButton }}
                </button>
            @endif

            @if ($canInvalidate)
                <button
                    type="button"
                    class="secondary-button !border-red-500 !text-red-600"
                    @click="$emitter.emit('nexus-open-invalid-modal')"
                >
                    @lang('funnel::app.panel.mark-invalid')
                </button>
            @endif
        </div>
    @endif
</div>

@if ($canEdit && $meetingButton)
    <v-funnel-meeting></v-funnel-meeting>

    @include('sales_form::partials.day-plan')
@endif

@if ($canEdit && $canInvalidate)
    <v-funnel-invalid></v-funnel-invalid>
@endif

@pushOnce('scripts')
    <script type="text/x-template" id="v-funnel-meeting-template">
        <x-admin::modal ref="meetingModal">
            <x-slot:header>
                <h3 class="text-base font-semibold dark:text-white">
                    {{ $meetingButton }}
                </h3>
            </x-slot>

            <x-slot:content>
                <form ref="form" @submit.prevent="submit" class="flex flex-col gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        @lang('funnel::app.meeting-modal.hint')
                    </p>

                    @include('sales_form::partials.meeting-fields', ['timezones' => array_keys(config('sales_form.timezones'))])
                </form>
            </x-slot>

            <x-slot:footer>
                <button
                    type="button"
                    class="primary-button"
                    :disabled="submitting"
                    @click="submit"
                >
                    <span v-if="! submitting">@lang('funnel::app.meeting-modal.save')</span>
                    <span v-else>…</span>
                </button>
            </x-slot>
        </x-admin::modal>
    </script>

    <script type="text/x-template" id="v-funnel-invalid-template">
        <x-admin::modal ref="invalidModal">
            <x-slot:header>
                <h3 class="text-base font-semibold dark:text-white">
                    @lang('funnel::app.invalid-modal.title')
                </h3>
            </x-slot>

            <x-slot:content>
                <form
                    id="funnel-invalid-form"
                    method="POST"
                    action="{{ route('admin.leads.funnel.invalid', $lead->id) }}"
                    class="flex flex-col gap-3"
                >
                    @csrf

                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        @lang('funnel::app.invalid-modal.hint')
                    </p>

                    <label class="text-xs font-medium text-gray-800 dark:text-white">
                        @lang('funnel::app.invalid-modal.reason')
                    </label>

                    <textarea
                        name="reason"
                        rows="3"
                        class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    ></textarea>
                </form>
            </x-slot>

            <x-slot:footer>
                <button type="submit" form="funnel-invalid-form" class="primary-button !bg-red-600 !border-red-600">
                    @lang('funnel::app.invalid-modal.confirm')
                </button>
            </x-slot>
        </x-admin::modal>
    </script>

    <script type="module">
        app.component('v-funnel-meeting', {
            template: '#v-funnel-meeting-template',

            data() {
                return {
                    submitting: false,

                    meetingError: null,

                    form: {
                        meeting_date: '',
                        meeting_time: '',
                        timezone: @json($fields['meeting_timezone'] ?? ''),
                        additional_information: '',
                    },
                };
            },

            mounted() {
                this.$emitter.on('nexus-open-meeting-modal', () => {
                    this.meetingError = null;

                    this.$refs.meetingModal.open();
                });
            },

            methods: {
                submit() {
                    if (! this.$refs.form.reportValidity()) {
                        return;
                    }

                    this.submitting = true;
                    this.meetingError = null;

                    this.$axios.post("{{ route('admin.leads.funnel.meeting', $lead->id) }}", this.form)
                        .then(({ data }) => {
                            window.location.href = data.redirect;
                        })
                        .catch((error) => {
                            this.submitting = false;

                            const errors = error.response?.data?.errors;

                            this.meetingError = errors
                                ? Object.values(errors).flat().join(' ')
                                : (error.response?.data?.message || "@lang('funnel::app.errors.failed')");
                        });
                },
            },
        });

        app.component('v-funnel-invalid', {
            template: '#v-funnel-invalid-template',

            mounted() {
                this.$emitter.on('nexus-open-invalid-modal', () => this.$refs.invalidModal.open());
            },
        });
    </script>
@endPushOnce
