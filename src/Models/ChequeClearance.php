<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChequeClearance extends BaseModel
{
    use SoftDeletes;

    protected $baseTable = 'cheque_clearances';

    protected $fillable = [
        'company_id',
        'branch_id',
        'direction', // inbound, outbound
        'chequeable_type',
        'chequeable_id',
        'payment_account_id',
        'amount',
        'cheque_number',
        'cheque_date',
        'bank_name',
        'notes',
        'status', // Pending, Collector Assigned, Cleared, Cancelled, Bounced
        'transaction_number',
        'cleared_at',
        'cancelled_at',
        'collected_at',
        'collected_by_name',
        'collected_by_mobile',
        'sales_rep_name',
        'cancel_reason',
        'created_by',
        'cleared_by',
        'cancelled_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cheque_date' => 'date',
        'cleared_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function chequeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'created_by');
    }

    public function clearer(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('user'), 'cleared_by');
    }
}
