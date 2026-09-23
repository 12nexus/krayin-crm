{{--
    "Export CSV" on the board toolbar (admin.leads.index.kanban.toolbar.card_settings.after).

    Exports the pipeline the board is showing, every stage, with each lead's
    current stage. A sales executive gets their own leads, as on the board.
--}}
@if (bouncer()->hasPermission('leads'))
    <a
        href="{{ route('admin.leads.export', request()->only('pipeline_id')) }}"
        class="flex items-center gap-1 rounded border border-gray-300 px-2.5 py-1.5 text-sm font-medium text-gray-700 transition-all hover:bg-gray-100 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800"
        title="@lang('funnel::app.export.hint')"
    >
        <span class="icon-download text-xl"></span>

        <span class="max-md:hidden">@lang('funnel::app.export.button')</span>
    </a>
@endif
