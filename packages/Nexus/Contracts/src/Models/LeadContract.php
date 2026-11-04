<?php

namespace Nexus\Contracts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class LeadContract extends Model
{
    protected $table = 'nexus_lead_contracts';

    protected $fillable = [
        'lead_id',
        'fields',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fields' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
