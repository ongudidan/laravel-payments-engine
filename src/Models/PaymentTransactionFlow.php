<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransactionFlow extends BaseModel
{
    protected $baseTable = 'payment_transaction_flows';

    public $timestamps = false;

    protected $fillable = [
        'payment_transaction_id',
        'from_account_id',
        'to_account_id',
        'amount',
        'action', // transfer_sent, transfer_received, deposit, withdrawal, refund, payment
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (! $model->created_at) {
                $model->created_at = now();
            }
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'to_account_id');
    }
}
