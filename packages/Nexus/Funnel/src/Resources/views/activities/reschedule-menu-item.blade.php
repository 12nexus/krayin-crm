{{--
    Injected into the activity "more actions" dropdown on the lead view. An open
    meeting is moved with the sales form's meeting fields (the funnel panel's
    meeting modal), which take the agent's timezone and check for clashes;
    Krayin's own edit form for meetings is hidden for the same reason.

    An open Call on a New Lead is moved the same way, with the funnel panel's
    call modal, so the new time is logged as a fresh Call entry.

    Rendered inside a Vue template, so `activity` is in scope at runtime.
--}}
@if (request()->routeIs('admin.leads.view') && bouncer()->hasPermission('activities.create'))
    <x-admin::dropdown.menu.item
        v-if="activity.type === 'meeting' && ! activity.is_done"
        @click="$emitter.emit('nexus-open-meeting-modal')"
    >
        <div class="flex items-center gap-2">
            <span class="icon-calendar text-2xl"></span>

            @lang('funnel::app.panel.reschedule')
        </div>
    </x-admin::dropdown.menu.item>

    @if (\Webkul\Lead\Models\Lead::with('stage')->find(request()->route('id'))?->stage?->code === 'new')
        <x-admin::dropdown.menu.item
            v-if="activity.type === 'call' && ! activity.is_done"
            @click="$emitter.emit('nexus-open-call-modal')"
        >
            <div class="flex items-center gap-2">
                <span class="icon-call text-2xl"></span>

                @lang('funnel::app.panel.reschedule-call')
            </div>
        </x-admin::dropdown.menu.item>
    @endif
@endif
