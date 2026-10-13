{{--
    admin.components.activities.content.activity.item.description.after

    The Google Meet files on a note written by nexus:meeting-notes, as links.
    Rendered inside a Vue template, so `activity` is in scope at runtime.
--}}
<div
    class="flex flex-wrap gap-2"
    v-if="activity.additional?.nexus_meet?.files?.length"
>
    <a
        v-for="file in activity.additional.nexus_meet.files"
        :href="file.url"
        :title="file.title"
        target="_blank"
        rel="noopener noreferrer"
        class="flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 hover:bg-gray-100 dark:border-gray-800 dark:hover:bg-gray-950"
    >
        <span :class="file.kind === 'Recording' ? 'icon-video' : 'icon-file'" class="text-lg"></span>

        <span class="font-medium text-brandColor">@{{ file.kind }}</span>
    </a>
</div>
