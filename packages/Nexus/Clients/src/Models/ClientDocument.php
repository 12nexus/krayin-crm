<?php

namespace Nexus\Clients\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\User\Models\User;

class ClientDocument extends Model
{
    protected $table = 'nexus_client_documents';

    protected $fillable = [
        'client_id',
        'category',
        'title',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $size = (int) $this->size;

        return match (true) {
            $size >= 1048576 => round($size / 1048576, 1).' MB',
            $size >= 1024    => round($size / 1024).' KB',
            default          => $size.' B',
        };
    }
}
