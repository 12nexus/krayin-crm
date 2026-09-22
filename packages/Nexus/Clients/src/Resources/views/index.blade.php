<x-admin::layouts>
    <x-slot:title>
        @lang('clients::app.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-1">
                <div class="text-xl font-bold dark:text-white">
                    @lang('clients::app.index.title')
                </div>

                <p class="text-gray-500 dark:text-gray-400">
                    @lang('clients::app.index.description')
                </p>
            </div>

            @if (bouncer()->hasPermission('clients.create'))
                <a href="{{ route('admin.clients.create') }}" class="primary-button">
                    @lang('clients::app.index.create')
                </a>
            @endif
        </div>

        <x-admin::datagrid :src="route('admin.clients.index')">
            <x-admin::shimmer.datagrid />
        </x-admin::datagrid>
    </div>
</x-admin::layouts>
