{{--
    Phone number lookup against the ViciDial mirror, shared by the sales form and
    the Create Lead form. Rendered inside a Vue template whose component uses the
    lookup mixin (partials/lookup-mixin), which supplies every name used here.
--}}
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

                            <div
                                v-if="existingLeads.length"
                                class="mb-3 rounded-md border border-amber-300 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950"
                            >
                                <p class="font-semibold text-amber-800 dark:text-amber-300">
                                    <span v-if="existingLeads.length === 1">@lang('sales_form::app.index.lookup.duplicate-one')</span>
                                    <span v-else v-text="duplicateHeading"></span>
                                </p>

                                <ul class="mt-2 grid gap-1">
                                    <li v-for="lead in existingLeads" :key="lead.id">
                                        <a
                                            :href="lead.url"
                                            target="_blank"
                                            class="text-sm font-medium text-amber-900 underline dark:text-amber-200"
                                            v-text="lead.title"
                                        ></a>
                                        <span class="text-xs text-amber-700 dark:text-amber-400">
                                            (<span v-text="lead.stage"></span>,
                                            <span v-text="lead.owner"></span>,
                                            <span v-text="lead.created_at"></span>)
                                        </span>
                                    </li>
                                </ul>

                                <p class="mt-2 text-amber-700 dark:text-amber-400">
                                    @lang('sales_form::app.index.lookup.duplicate-help')
                                </p>
                            </div>
                        </div>
                    </div>
