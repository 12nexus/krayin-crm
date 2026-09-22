{{--
    The meeting section of the sales form: date, time, the agent's timezone and
    call notes. Every way of booking a meeting uses these same fields, so a
    meeting is always entered in the agent's own zone and checked for clashes.

    Rendered inside a Vue template whose component has `form.meeting_date`,
    `form.meeting_time`, `form.timezone`, `form.additional_information` and a
    `meetingError` (null unless an asynchronous submit reported a problem).
    Needs $timezones.
--}}
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

                        @error('meeting_time')
                            <div class="mt-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                                {{ $message }}
                            </div>
                        @enderror

                        <!-- Clash reported by an asynchronous submit (the reschedule modal) -->
                        <div
                            v-if="meetingError"
                            class="mt-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                            v-text="meetingError"
                        ></div>

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
