<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCustomerWalletBalance extends BaseModel
{
    protected $baseTable = 'user_customer_wallet_balances';

    protected $fillable = [
        'user_id',
        'customer_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('customer'), 'customer_id');
    }
}
