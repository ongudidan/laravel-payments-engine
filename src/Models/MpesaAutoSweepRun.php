<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaAutoSweepRun extends BaseModel
{
    protected $baseTable = 'mpesa_auto_sweep_runs';

    protected $fillable = [
        'payment_account_id',
        'auto_sweep_config_id',
        'amount',
        'status', // pending, completed, failed
        'transfer_id',
        'message',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function autoSweepConfig(): BelongsTo
    {
        return $this->belongsTo(MpesaAutoSweepConfig::class, 'auto_sweep_config_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(PaymentAccountTransfer::class, 'transfer_id');
    }
}
