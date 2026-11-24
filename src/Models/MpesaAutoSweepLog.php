<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaAutoSweepLog extends BaseModel
{
    protected $baseTable = 'mpesa_auto_sweep_logs';

    protected $fillable = [
        'payment_account_id',
        'auto_sweep_run_id',
        'status',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(MpesaAutoSweepRun::class, 'auto_sweep_run_id');
    }
}
