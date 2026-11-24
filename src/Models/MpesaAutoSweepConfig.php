<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MpesaAutoSweepConfig extends BaseModel
{
    protected $baseTable = 'mpesa_auto_sweep_configs';

    protected $fillable = [
        'payment_account_id',
        'destination_bank_account_id',
        'is_enabled',
        'sweep_time',
        'min_sweep_amount',
        'last_sweep_at',
        'last_status',
        'last_error',
        'notes',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'min_sweep_amount' => 'decimal:2',
        'last_sweep_at' => 'datetime',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function destinationBankAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'destination_bank_account_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(MpesaAutoSweepRun::class, 'auto_sweep_config_id');
    }
}
