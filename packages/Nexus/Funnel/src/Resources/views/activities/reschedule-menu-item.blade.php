{{--
    Injected into the activity "more actions" dropdown on the lead view. An open
    meeting is moved with the sales form's meeting fields (the funnel panel's
    meeting modal), which take the agent's timezone and check for clashes;
    Krayin's own edit form for meetings is hidden for the same reason.

    Rendered inside a Vue template, so `activity` is in scope at runtime.
--}}
@if (request()->routeIs('admin.leads.view') && bouncer()->hasPermission('leads.edit'))
    <x-admin::dropdown.menu.item
        v-if="activity.type === 'meeting' && ! activity.is_done"
        @click="$emitter.emit('nexus-open-meeting-modal')"
    >
        <div class="flex items-center gap-2">
            <span class="icon-calendar text-2xl"></span>

            @lang('funnel::app.panel.reschedule')
        </div>
    </x-admin::dropdown.menu.item>
@endif
