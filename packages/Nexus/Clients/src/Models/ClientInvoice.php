<?php

namespace Nexus\Clients\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;

class ClientInvoice extends Model
{
    protected $table = 'nexus_client_invoices';

    protected $fillable = [
        'client_id',
        'number',
        'billing_month',
        'amount',
        'currency',
        'issued_on',
        'due_date',
        'status',
        'paid_on',
        'description',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'billing_month' => 'date',
        'issued_on'     => 'date',
        'due_date'      => 'date',
        'paid_on'       => 'date',
        'amount'        => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * paid | void | overdue | unpaid: overdue is an unpaid invoice past its due date.
     */
    public function displayStatus(): string
    {
        if ($this->status === 'unpaid' && $this->due_date && $this->due_date->endOfDay()->isPast()) {
            return 'overdue';
        }

        return $this->status;
    }

    /**
     * Next number in the year's sequence: INV-2026-0001, INV-2026-0002, …
     * Called inside the create transaction, with the rows locked, so two
     * invoices raised together never share a number.
     */
    public static function nextNumber(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        $prefix = config('clients.invoice_prefix', 'INV').'-'.$year.'-';

        $last = DB::table('nexus_client_invoices')
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
