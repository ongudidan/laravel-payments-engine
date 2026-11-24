<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends BaseModel
{
    protected $baseTable = 'payment_transactions';

    protected $fillable = [
        'payment_method_id',
        'payment_method_mapping_id',
        'payment_account_id',
        'amount',
        'reference',
        'provider_reference',
        'notes',
        'cheque_details',
        'status', // pending, completed, confirmed, reversed, cancelled
        'customer_id',
        'supplier_id',
        'initiated_at',
        'confirmed_at',
        'posted_at',
        'reversed_at',
        'cancelled_at',
        'cancel_reason',
        'cancelled_by',
        'amount_due',
        'amount_given',
        'change_given',
        'change_status',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'amount_given' => 'decimal:2',
        'change_given' => 'decimal:2',
        'cheque_details' => 'array',
        'initiated_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function flows(): HasMany
    {
        return $this->hasMany(PaymentTransactionFlow::class, 'payment_transaction_id');
    }

    public function denominations(): HasMany
    {
        return $this->hasMany(PaymentTransactionDenomination::class, 'payment_transaction_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('customer'), 'customer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('supplier'), 'supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'created_by');
    }
}
