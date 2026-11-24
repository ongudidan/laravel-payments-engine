<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends BaseModel
{
    protected $baseTable = 'accounts';

    protected $fillable = [
        'code',
        'name',
        'type', // asset, liability, equity, income, expense
        'subtype',
        'normal_balance', // debit, credit
        'balance',
        'currency',
        'parent_id',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(EntityAccountLedger::class, 'account_id');
    }
}
