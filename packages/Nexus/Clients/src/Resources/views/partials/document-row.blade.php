<div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0 dark:border-gray-800">
    <div class="flex min-w-0 items-center gap-2">
        <span class="icon-file text-2xl text-gray-500"></span>

        <div class="min-w-0">
            <a
                href="{{ route('admin.clients.documents.download', [$client->id, $document->id]) }}"
                class="block truncate text-sm font-medium text-brandColor"
            >
                {{ $document->title }}
            </a>

            <p class="truncate text-xs text-gray-500">
                {{ $document->original_name }} · {{ $document->humanSize() }} · {{ $document->created_at->format('j M Y') }}{{ $document->uploader ? ' · '.$document->uploader->name : '' }}
            </p>
        </div>
    </div>

    @if (bouncer()->hasPermission('clients.edit'))
        <form
            method="POST"
            action="{{ route('admin.clients.documents.delete', [$client->id, $document->id]) }}"
            onsubmit="return confirm(@js(trans('clients::app.document.confirm-delete', ['title' => $document->title])))"
        >
            @csrf
            @method('DELETE')

            <button type="submit" class="icon-delete rounded p-1 text-lg text-gray-500 hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-800" title="@lang('clients::app.document.delete')"></button>
        </form>
    @endif
</div>
