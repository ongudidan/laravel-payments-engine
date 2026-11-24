<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierWalletTransaction extends BaseModel
{
    protected $baseTable = 'supplier_wallet_transactions';

    protected $fillable = [
        'supplier_id',
        'type', // credit, debit
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'reference_number',
        'notes',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('supplier'), 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'created_by');
    }
}
