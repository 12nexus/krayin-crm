{{--
    Day plan from the shared sales calendar, shown beside the meeting fields.
    Registers <v-sales-day-plan :date :time :timezone>; include this partial
    once on any page that renders the meeting fields.

    The server returns each event's times already converted into the day's
    zone, as minutes from midnight, so the clash check here is plain arithmetic.
--}}
@pushOnce('scripts', 'nexus-day-plan')
    <script type="text/x-template" id="v-sales-day-plan-template">
        <div
            v-if="configured"
            class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm font-semibold text-gray-800 dark:text-white">
                    <span class="icon-calendar mr-1 align-middle text-lg"></span>
                    @lang('sales_form::app.day-plan.title')<span v-if="plan.date_label" class="font-normal text-gray-600 dark:text-gray-300"> · @{{ plan.date_label }}</span>
                </p>

                <button
                    type="button"
                    class="text-xs font-medium text-brandColor hover:underline"
                    @click="load(true)"
                >
                    @lang('sales_form::app.day-plan.refresh')
                </button>
            </div>

            <p v-if="plan.zone_label" class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                <span v-if="plan.is_client_zone" v-text="zoneText(strings.timesClient)"></span>
                <span v-else v-text="zoneText(strings.timesYours)"></span>
            </p>

            <p v-if="loading" class="text-sm text-gray-500">@lang('sales_form::app.day-plan.loading')</p>

            <p v-else-if="error" class="text-sm text-amber-700" v-text="error"></p>

            <template v-else>
                <p v-if="plan.stale" class="mb-2 text-xs text-amber-700">@lang('sales_form::app.day-plan.stale')</p>

                <ul class="flex flex-col gap-1">
                    <li
                        v-for="(item, index) in items"
                        :key="index"
                        class="flex items-center gap-3 rounded px-2 py-1.5 text-sm"
                        :class="item.proposed
                            ? (conflicts.length ? 'border border-red-400 bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'border border-blue-300 bg-blue-50 text-blue-900 dark:bg-blue-950 dark:text-blue-100')
                            : (item.clash ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300')"
                    >
                        <span class="w-36 shrink-0 whitespace-nowrap text-xs font-medium tabular-nums">
                            <template v-if="item.all_day">@lang('sales_form::app.day-plan.all-day')</template>
                            <template v-else>@{{ item.start }} – @{{ item.end }}</template>
                        </span>

                        <span class="min-w-0 flex-1 truncate">
                            <strong v-if="item.proposed">@lang('sales_form::app.day-plan.this-meeting')</strong>
                            <template v-else>@{{ item.title }}</template>
                            <span v-if="! item.proposed && ! item.busy" class="ml-1 text-xs text-gray-400">(@lang('sales_form::app.day-plan.free'))</span>
                        </span>
                    </li>

                    <li v-if="! items.length" class="px-2 py-1.5 text-sm text-gray-500">@lang('sales_form::app.day-plan.empty')</li>
                </ul>

                <p
                    v-if="proposed && conflicts.length"
                    class="mt-2 text-sm font-medium text-red-700 dark:text-red-300"
                    v-text="strings.clash.replace(':count', conflicts.length)"
                ></p>

                <p v-else-if="proposed" class="mt-2 text-sm text-green-700 dark:text-green-400">
                    ✓ @lang('sales_form::app.day-plan.clear')
                </p>
            </template>
        </div>
    </script>

    <script type="module">
        app.component('v-sales-day-plan', {
            template: '#v-sales-day-plan-template',

            props: {
                date: { type: String, default: '' },
                time: { type: String, default: '' },
                timezone: { type: String, default: '' },
            },

            data() {
                return {
                    configured: true,
                    loading: false,
                    error: null,
                    plan: { events: [] },
                    request: 0,

                    strings: @json([
                        'timesClient' => trans('sales_form::app.day-plan.times-client'),
                        'timesYours'  => trans('sales_form::app.day-plan.times-yours'),
                        'clash'       => trans('sales_form::app.day-plan.clash'),
                        'unavailable' => trans('sales_form::app.day-plan.unavailable'),
                    ]),
                };
            },

            computed: {
                /**
                 * The meeting being entered, in minutes from the day's midnight.
                 * Only meaningful once the plan is for that same date.
                 */
                proposed() {
                    if (! this.time || ! this.date || this.plan.date !== this.date) {
                        return null;
                    }

                    const [hours, minutes] = this.time.split(':').map(Number);

                    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
                        return null;
                    }

                    const start = hours * 60 + minutes;
                    const end = start + (this.plan.meeting_minutes || 30);

                    return { start, end, label: this.label(start) + ' – ' + this.label(end) };
                },

                conflicts() {
                    if (! this.proposed) {
                        return [];
                    }

                    return (this.plan.events || []).filter((event) => this.clashes(event));
                },

                items() {
                    const events = (this.plan.events || []).map((event) => ({ ...event, clash: this.clashes(event) }));

                    if (! this.proposed) {
                        return events;
                    }

                    const mine = {
                        proposed: true,
                        all_day: false,
                        start_min: this.proposed.start,
                        start: this.label(this.proposed.start),
                        end: this.label(this.proposed.end),
                    };

                    return [...events, mine].sort((a, b) =>
                        (b.all_day - a.all_day) || (a.start_min - b.start_min) || (a.proposed ? 1 : -1)
                    );
                },
            },

            watch: {
                date() { this.load(); },
                timezone() { this.load(); },
            },

            mounted() {
                this.load();
            },

            methods: {
                clashes(event) {
                    return !! this.proposed
                        && event.busy
                        && ! event.all_day
                        && event.start_min < this.proposed.end
                        && event.end_min > this.proposed.start;
                },

                label(totalMinutes) {
                    const minutes = ((totalMinutes % 1440) + 1440) % 1440;
                    const hours24 = Math.floor(minutes / 60);
                    const hours12 = hours24 % 12 || 12;

                    return hours12 + ':' + String(minutes % 60).padStart(2, '0') + ' ' + (hours24 < 12 ? 'AM' : 'PM');
                },

                zoneText(template) {
                    return template.replace(':zone', this.plan.zone_label || '');
                },

                load(force = false) {
                    const ticket = ++this.request;

                    this.loading = ! this.plan.events.length || force;
                    this.error = null;

                    let zone = '';

                    try {
                        zone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
                    } catch (e) {}

                    this.$axios.get("{{ route('admin.sales_form.day_plan') }}", {
                            params: { date: this.date || undefined, timezone: this.timezone || undefined, zone },
                        })
                        .then(({ data }) => {
                            // A slower, older request must not overwrite a newer one.
                            if (ticket !== this.request) {
                                return;
                            }

                            this.configured = data.configured !== false;
                            this.error = data.error || null;
                            this.plan = data.error ? { events: [] } : data;
                        })
                        .catch(() => {
                            if (ticket === this.request) {
                                this.error = this.strings.unavailable;
                            }
                        })
                        .finally(() => {
                            if (ticket === this.request) {
                                this.loading = false;
                            }
                        });
                },
            },
        });
    </script>
@endPushOnce
