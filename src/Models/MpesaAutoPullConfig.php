<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaAutoPullConfig extends BaseModel
{
    protected $baseTable = 'mpesa_auto_pull_configs';

    protected $fillable = [
        'payment_account_id',
        'is_enabled',
        'interval_minutes',
        'auto_record',
        'discrepancy_only',
        'lookback_hours',
        'last_status',
        'last_message',
        'last_run_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'auto_record' => 'boolean',
        'discrepancy_only' => 'boolean',
        'interval_minutes' => 'integer',
        'lookback_hours' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }
}
