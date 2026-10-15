{{--
    "Play recording" under a web phone call in the lead's activity list
    (admin.components.activities.content.activity.item.description.after).
    Opens the phone's recording page; it plays for the person on the call and admins.
--}}
<a
    v-if="activity.type === 'call' && activity.additional && activity.additional.recording_url"
    :href="activity.additional.recording_url"
    target="_blank"
    rel="noopener"
    class="mt-1 inline-flex w-fit items-center gap-1 rounded-md nexus-phone-outline border px-2 py-0.5 text-xs font-semibold"
>
    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
    @lang('phone::app.activity.play-recording')
</a>

{{-- Pictures and files sent with a text (MMS) on its note. --}}
<div
    v-if="activity.type === 'note' && activity.additional && activity.additional.media && activity.additional.media.length"
    class="mt-1 flex flex-wrap gap-1.5"
>
    <a
        v-for="(url, index) in activity.additional.media"
        :key="index"
        :href="url"
        target="_blank"
        rel="noopener"
        class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-0.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-900"
    >
        📎 @lang('phone::app.activity.attachment') @{{ index + 1 }}
    </a>
</div>

{{-- Gemini's call notes (Services/CallNotes): the recording and the transcript, folded. --}}
<div
    v-if="activity.type === 'note' && activity.additional && activity.additional.call_notes"
    class="mt-1 flex flex-col gap-1"
>
    <a
        v-if="activity.additional.call_notes.recording_url"
        :href="activity.additional.call_notes.recording_url"
        target="_blank"
        rel="noopener"
        class="inline-flex w-fit items-center gap-1 rounded-md nexus-phone-outline border px-2 py-0.5 text-xs font-semibold"
    >
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
        @lang('phone::app.activity.play-recording')
    </a>

    <details
        v-if="activity.additional.call_notes.transcript && activity.additional.call_notes.transcript.length"
        class="text-sm"
    >
        <summary class="cursor-pointer text-xs font-semibold text-brandColor">
            @lang('phone::app.activity.transcript') (@{{ activity.additional.call_notes.transcript.length }})
        </summary>

        <div class="mt-1 flex flex-col gap-1 rounded-md border border-gray-200 p-2 dark:border-gray-800 dark:text-gray-300" style="max-height: 24rem; overflow-y: auto">
            <p v-for="(turn, index) in activity.additional.call_notes.transcript" :key="index">
                <span class="font-semibold">@{{ turn.speaker }}:</span> @{{ turn.text }}
            </p>
        </div>
    </details>
</div>
