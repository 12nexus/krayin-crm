{{--
    Injected into the activity "more actions" dropdown on the lead view via the
    admin.components.activities.content.activity.item.more_actions.dropdown.menu_item.before
    render event, so Krayin's own template stays untouched.

    Rendered inside a Vue template, so `activity` is in scope at runtime.
--}}
<x-admin::dropdown.menu.item v-if="activity.schedule_from && ['call', 'meeting', 'lunch'].includes(activity.type)">
    <a
        class="flex items-center gap-2"
        :href="'{{ route('admin.sales_form.activity_calendar', 'replaceId') }}'.replace('replaceId', activity.id)"
        target="_blank"
        rel="noopener"
    >
        <span class="icon-calendar text-2xl"></span>

        @lang('sales_form::app.activity.add-to-calendar')
    </a>
</x-admin::dropdown.menu.item>
