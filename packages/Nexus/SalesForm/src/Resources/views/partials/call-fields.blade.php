{{--
    When to call a New Lead back: a calendar to pick the date and time, and the
    client's timezone the time is in. Used by the Create Lead form and by the
    "Reschedule call" modal on the lead view.

    Rendered inside a Vue template whose component has `form.call_at`
    ("Y-m-d H:i", the client's local time) and `form.call_timezone`. Pass
    $callRequired = true where a time must be given. Needs $timezones.
--}}
@php($callRequired = $callRequired ?? false)

<input type="hidden" name="call_at" :value="form.call_at">

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label @class([
            'mb-1 block text-xs font-medium text-gray-800 dark:text-white',
            "after:ml-0.5 after:text-red-500 after:content-['*']" => $callRequired,
        ])>
            @lang('sales_form::app.call.at')
        </label>

        <v-call-picker v-model="form.call_at" :required="{{ $callRequired ? 'true' : 'false' }}"></v-call-picker>

        @error('call_at')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <p @class([
            'mb-2 text-xs font-medium text-gray-800 dark:text-white',
            "after:ml-0.5 after:text-red-500 after:content-['*']" => $callRequired,
        ])>
            @lang('sales_form::app.call.timezone')
        </p>

        <div class="flex flex-col gap-1.5">
            @foreach ($timezones as $timezone)
                <label class="flex cursor-pointer items-center gap-2 text-sm dark:text-gray-300">
                    <input
                        type="radio"
                        name="call_timezone"
                        value="{{ $timezone }}"
                        v-model="form.call_timezone"
                        :required="{{ $callRequired ? 'true' : 'false' }} || !! form.call_at"
                        class="accent-[#004EF0]"
                    >
                    <span>{{ $timezone }}</span>
                </label>
            @endforeach
        </div>

        @error('call_timezone')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-call-picker-template">
        <div class="flex items-center gap-2">
            <div class="relative w-full">
                <input
                    ref="input"
                    type="text"
                    :required="required"
                    placeholder="@lang('sales_form::app.call.placeholder')"
                    class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                >

                <span class="icon-calendar pointer-events-none absolute top-1/2 -translate-y-1/2 text-2xl text-gray-400 ltr:right-2 rtl:left-2"></span>
            </div>

            <button
                type="button"
                v-if="modelValue && ! required"
                class="icon-cross-large rounded p-1 text-xl text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                title="@lang('sales_form::app.call.clear')"
                @click="clear"
            ></button>
        </div>
    </script>

    <script type="module">
        /**
         * Flatpickr calendar with the time underneath. The value is the client's
         * wall-clock time, "Y-m-d H:i"; the zone is chosen beside it.
         */
        app.component('v-call-picker', {
            template: '#v-call-picker-template',

            props: {
                modelValue: String,

                required: Boolean,
            },

            emits: ['update:modelValue'],

            mounted() {
                this.picker = new Flatpickr(this.$refs.input, {
                    enableTime: true,
                    dateFormat: 'Y-m-d H:i',
                    altInput: true,
                    altFormat: 'D j M Y, h:i K',
                    altInputClass: this.$refs.input.className,
                    allowInput: false,
                    minuteIncrement: 15,
                    defaultHour: 11,
                    defaultMinute: 0,
                    minDate: 'today',
                    defaultDate: this.modelValue || null,
                    onChange: (dates, value) => this.$emit('update:modelValue', value),
                });
            },

            beforeUnmount() {
                this.picker?.destroy();
            },

            watch: {
                modelValue(value) {
                    if (value !== this.picker.input.value) {
                        this.picker.setDate(value || null, false);
                    }
                },
            },

            methods: {
                clear() {
                    this.picker.clear();

                    this.$emit('update:modelValue', '');
                },
            },
        });
    </script>
@endPushOnce
