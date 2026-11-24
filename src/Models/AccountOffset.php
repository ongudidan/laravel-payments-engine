<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountOffset extends BaseModel
{
    protected $baseTable = 'account_offsets';

    protected $fillable = [
        'customer_id',
        'supplier_id',
        'amount',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

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
