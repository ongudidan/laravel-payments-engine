<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransactionDenomination extends BaseModel
{
    protected $baseTable = 'payment_transaction_denominations';

    protected $fillable = [
        'payment_transaction_id',
        'denomination_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function denomination(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodDenomination::class, 'denomination_id');
    }
}
