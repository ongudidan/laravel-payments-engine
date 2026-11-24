<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MpesaConfig extends BaseModel
{
    protected $baseTable = 'mpesa_configs';

    protected $fillable = [
        'payment_account_id',
        'company_id',
        'branch_id',
        'name',
        'shortcode',
        'type', // paybill, till
        'nominated_number',
        'consumer_key',
        'consumer_secret',
        'passkey',
        'initiator_name',
        'initiator_password',
        'security_credential',
        'environment', // sandbox, live
        'c2b_secret_token',
        'callback_url',
        'is_active',
        'urls_registered_at',
        'last_registration_response',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'consumer_key' => 'encrypted',
        'consumer_secret' => 'encrypted',
        'passkey' => 'encrypted',
        'initiator_password' => 'encrypted',
        'security_credential' => 'encrypted',
        'urls_registered_at' => 'datetime',
        'last_registration_response' => 'array',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MpesaTransaction::class, 'business_short_code', 'shortcode');
    }
}
