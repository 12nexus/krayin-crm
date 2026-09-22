<form
    method="POST"
    action="{{ route('admin.clients.documents.store', $client->id) }}"
    enctype="multipart/form-data"
    class="mt-3 grid grid-cols-1 items-end gap-3 rounded-md border border-dashed border-gray-300 p-3 md:grid-cols-3 dark:border-gray-700"
>
    @csrf

    <input type="hidden" name="category" value="{{ $category }}">

    <div>
        <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">@lang('clients::app.document.title')</label>
        <input
            type="text"
            name="title"
            placeholder="{{ $category === 'contract' ? trans('clients::app.document.contract-placeholder') : trans('clients::app.document.title-placeholder') }}"
            class="w-full rounded border border-gray-300 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
        >
    </div>

    <div>
        <label class="mb-1 block text-xs font-medium text-gray-800 dark:text-white">@lang('clients::app.document.file')</label>
        <input
            type="file"
            name="file"
            required
            accept="{{ collect(config('clients.allowed_extensions'))->map(fn ($ext) => '.'.$ext)->implode(',') }}"
            class="w-full text-sm text-gray-700 dark:text-gray-300"
        >
    </div>

    <button type="submit" class="secondary-button justify-center">
        {{ $category === 'contract' ? trans('clients::app.document.upload-contract') : trans('clients::app.document.upload') }}
    </button>

    @if ($errors->has('file') && old('category') === $category)
        <p class="text-sm text-red-600 md:col-span-3">{{ $errors->first('file') }}</p>
    @endif
</form>
