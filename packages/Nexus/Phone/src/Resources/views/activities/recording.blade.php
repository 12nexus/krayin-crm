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
    class="mt-1 inline-flex w-fit items-center gap-1 rounded-md border border-green-600 px-2 py-0.5 text-xs font-semibold text-green-700 hover:bg-green-50 dark:text-green-400 dark:hover:bg-gray-900"
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
