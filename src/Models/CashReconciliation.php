<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashReconciliation extends BaseModel
{
    protected $baseTable = 'cash_reconciliations';

    protected $fillable = [
        'vault_account_id',
        'drawer_id',
        'cashier_id',
        'approver_id',
        'amount_submitted',
        'amount_expected',
        'difference',
        'status', // Pending, Approved, Rejected
        'submitted_at',
        'approved_at',
        'cashier_notes',
        'approver_notes',
    ];

    protected $casts = [
        'amount_submitted' => 'decimal:2',
        'amount_expected' => 'decimal:2',
        'difference' => 'decimal:2',
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
