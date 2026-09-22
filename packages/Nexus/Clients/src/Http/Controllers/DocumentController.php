<?php

namespace Nexus\Clients\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Nexus\Clients\Models\Client;
use Nexus\Clients\Models\ClientDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Client documents, the 12 Nexus contract among them. Kept on the private disk
 * and only ever served through the authenticated download route.
 */
class DocumentController extends Controller
{
    public function store(int $id): RedirectResponse
    {
        $client = Client::findOrFail($id);

        $data = request()->validate([
            'category' => ['required', Rule::in(array_keys(config('clients.document_categories')))],
            'title'    => ['nullable', 'string', 'max:255'],
            'file'     => [
                'required',
                'file',
                'max:'.config('clients.max_upload_kb'),
                'mimes:'.implode(',', config('clients.allowed_extensions')),
            ],
        ]);

        $file = request()->file('file');

        $path = $file->store('nexus-clients/'.$client->id, config('clients.disk'));

        $client->documents()->create([
            'category'      => $data['category'],
            'title'         => $data['title'] ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'uploaded_by'   => auth()->guard('user')->id(),
        ]);

        return redirect()
            ->route('admin.clients.view', $client->id)
            ->with('success', trans('clients::app.flash.document-uploaded'));
    }

    public function download(int $id, int $documentId): StreamedResponse
    {
        $document = $this->document($id, $documentId);

        abort_unless(Storage::disk(config('clients.disk'))->exists($document->path), 404);

        return Storage::disk(config('clients.disk'))->download($document->path, $document->original_name);
    }

    public function destroy(int $id, int $documentId): RedirectResponse
    {
        $document = $this->document($id, $documentId);

        Storage::disk(config('clients.disk'))->delete($document->path);

        $document->delete();

        return redirect()
            ->route('admin.clients.view', $id)
            ->with('success', trans('clients::app.flash.document-deleted'));
    }

    protected function document(int $clientId, int $documentId): ClientDocument
    {
        return ClientDocument::where('client_id', $clientId)->findOrFail($documentId);
    }
}
