<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDrawerTransaction extends BaseModel
{
    protected $baseTable = 'cash_drawer_transactions';

    protected $fillable = [
        'drawer_id',
        'transaction_type', // sale, float_in, change, drop_out, payout
        'amount',
        'sale_id',
        'performed_by',
        'notes',
        'reference_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function drawer(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'drawer_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'performed_by');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('sale'), 'sale_id');
    }
}
