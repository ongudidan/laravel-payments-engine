<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends BaseModel
{
    protected $baseTable = 'payment_methods';

    protected $fillable = [
        'name',
        'code', // cash, mpesa, bank_transfer, cheque, customer_wallet, card
        'payment_account_id',
        'channel',
        'parent_id',
        'description',
        'requires_reference',
        'has_denominations',
        'is_wallet',
        'is_bank',
        'is_active',
        'is_supplier_recharge',
        'has_linked_bank',
        'is_mpesa_agent',
    ];

    protected $casts = [
        'requires_reference' => 'boolean',
        'has_denominations' => 'boolean',
        'is_wallet' => 'boolean',
        'is_bank' => 'boolean',
        'is_active' => 'boolean',
        'is_supplier_recharge' => 'boolean',
        'has_linked_bank' => 'boolean',
        'is_mpesa_agent' => 'boolean',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function denominations(): HasMany
    {
        return $this->hasMany(PaymentMethodDenomination::class, 'payment_method_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
