<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FloatRequest extends BaseModel
{
    protected $baseTable = 'float_requests';

    protected $fillable = [
        'vault_account_id',
        'drawer_id',
        'cashier_id',
        'approver_id',
        'amount',
        'status', // Pending, Approved, Rejected, Cancelled
        'request_notes',
        'approver_notes',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function vault(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'vault_account_id');
    }

    public function drawer(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'drawer_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'cashier_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'approver_id');
    }
}
