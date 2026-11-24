<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAccountTransfer extends BaseModel
{
    protected $baseTable = 'payment_account_transfers';

    protected $fillable = [
        'transfer_no',
        'from_account_id',
        'to_account_id',
        'amount',
        'charges',
        'received_amount',
        'reference',
        'transaction_number',
        'notes',
        'status', // pending, approved, rejected, cancelled
        'initiated_by',
        'performed_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'charges' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'to_account_id');
    }

    public function denominations(): HasMany
    {
        return $this->hasMany(PaymentTransferDenomination::class, 'transfer_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'initiated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'approved_by');
    }

    /**
     * Heal duplicate transaction flows for the transfer reference
     */
    public function healDuplicateFlows(): void
    {
        if (empty($this->reference)) {
            return;
        }

        $txIds = PaymentTransaction::where('reference', $this->reference)->pluck('id');
        if ($txIds->isEmpty()) {
            return;
        }

        $flows = PaymentTransactionFlow::whereIn('payment_transaction_id', $txIds)
            ->orderBy('id', 'asc')
            ->get();

        $seenActions = [];
        foreach ($flows as $flow) {
            $key = "{$flow->action}_{$flow->from_account_id}_{$flow->to_account_id}_".(float) $flow->amount;
            if (isset($seenActions[$key])) {
                $flow->delete();
            } else {
                $seenActions[$key] = true;
            }
        }
    }
}
