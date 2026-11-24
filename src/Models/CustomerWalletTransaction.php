<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWalletTransaction extends BaseModel
{
    protected $baseTable = 'customer_wallet_transactions';

    protected $fillable = [
        'customer_id',
        'type', // credit, debit
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'reference_number',
        'notes',
        'description',
        'sale_id',
        'mpesa_transaction_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('customer'), 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'created_by');
    }

    public function mpesaTransaction(): BelongsTo
    {
        return $this->belongsTo(MpesaTransaction::class, 'mpesa_transaction_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('sale'), 'sale_id');
    }
}
