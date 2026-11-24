<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentAccount extends BaseModel
{
    protected $baseTable = 'payment_accounts';

    protected $fillable = [
        'name',
        'type', // cash, bank, mpesa_till, mpesa_paybill, wallet
        'account_number',
        'nominated_number',
        'bank_id',
        'bank_name',
        'branch_id',
        'company_id',
        'user_id',
        'payment_method_id',
        'currency',
        'balance',
        'opening_balance',
        'float_balance',
        'pending_cheque_balance',
        'linked_bank_account_id',
        'chart_of_account_id',
        'account_id',
        'has_denominations',
        'is_vault',
        'is_active',
        'can_make_payments',
        'can_receive_payments',
        'requires_reconciliation',
        'ledger_reference',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'float_balance' => 'decimal:2',
        'pending_cheque_balance' => 'decimal:2',
        'has_denominations' => 'boolean',
        'is_vault' => 'boolean',
        'is_active' => 'boolean',
        'can_make_payments' => 'boolean',
        'can_receive_payments' => 'boolean',
        'requires_reconciliation' => 'boolean',
    ];

    public function linkedBankAccount(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_bank_account_id');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'chart_of_account_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function denominations(): HasMany
    {
        return $this->hasMany(PaymentAccountDenomination::class, 'payment_account_id');
    }

    public function currentDenominations(): HasMany
    {
        return $this->hasMany(PaymentAccountDenomination::class, 'payment_account_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PaymentAccountAdjustment::class, 'payment_account_id');
    }

    public function transfersFrom(): HasMany
    {
        return $this->hasMany(PaymentAccountTransfer::class, 'from_account_id');
    }

    public function transfersTo(): HasMany
    {
        return $this->hasMany(PaymentAccountTransfer::class, 'to_account_id');
    }

    public function mpesaConfig(): HasOne
    {
        return $this->hasOne(MpesaConfig::class, 'payment_account_id');
    }

    public function autoSweepConfig(): HasOne
    {
        return $this->hasOne(MpesaAutoSweepConfig::class, 'payment_account_id');
    }

    public function autoPullConfig(): HasOne
    {
        return $this->hasOne(MpesaAutoPullConfig::class, 'payment_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'user_id');
    }
}
