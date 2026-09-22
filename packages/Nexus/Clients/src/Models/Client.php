<?php

namespace Nexus\Clients\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class Client extends Model
{
    protected $table = 'nexus_clients';

    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'address',
        'engagement_type',
        'monthly_retainer',
        'onboarded_on',
        'status',
        'notes',
        'lead_id',
        'person_id',
        'user_id',
    ];

    protected $casts = [
        'onboarded_on'     => 'date',
        'monthly_retainer' => 'decimal:2',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The account manager.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class)->latest();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ClientInvoice::class)->orderByDesc('billing_month')->orderByDesc('id');
    }
}
