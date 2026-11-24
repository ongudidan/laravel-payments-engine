<?php

namespace Ongudidan\PaymentsEngine\Models;

class CashDenomination extends BaseModel
{
    protected $baseTable = 'cash_denominations';

    protected $fillable = [
        'type', // bill, coin
        'value',
        'currency',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];
}
