<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityAccountLedger extends BaseModel
{
    protected $baseTable = 'entity_account_ledgers';

    protected $fillable = [
        'account_id',
        'account_code',
        'account_type', // asset, liability, equity, income, expense
        'customer_id',
        'supplier_id',
        'company_id',
        'branch_id',
        'debit',
        'credit',
        'balance', // running balance
        'source_type',
        'source_id',
        'entry_type', // Invoice, Payment, WalletCredit, WalletDebit, Transfer, Sweep, Suspense, Reversal, Adjustment
        'reference',
        'description',
        'posting_date',
        'created_by',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
        'balance' => 'decimal:4',
        'posting_date' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

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

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
