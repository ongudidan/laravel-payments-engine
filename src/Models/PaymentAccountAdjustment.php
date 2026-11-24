<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAccountAdjustment extends BaseModel
{
    protected $baseTable = 'payment_account_adjustments';

    protected $fillable = [
        'user_id',
        'company_id',
        'branch_id',
        'payment_account_id',
        'reconciliation_id',
        'type', // adjustment, physical_count, opening, reconciliation
        'old_balance',
        'new_balance',
        'old_float_balance',
        'new_float_balance',
        'amount',
        'denominations_data',
        'reason',
        'status', // Pending, Approved, Rejected
        'approved_by',
        'approved_at',
        'journal_entry_id',
        'notes',
    ];

    protected $casts = [
        'old_balance' => 'decimal:2',
        'new_balance' => 'decimal:2',
        'old_float_balance' => 'decimal:2',
        'new_float_balance' => 'decimal:2',
        'amount' => 'decimal:2',
        'denominations_data' => 'array',
        'approved_at' => 'datetime',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'approved_by');
    }
}
