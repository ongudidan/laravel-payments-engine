<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaTransaction extends BaseModel
{
    protected $baseTable = 'mpesa_transactions';

    protected $fillable = [
        'payment_account_id',
        'customer_id',
        'sale_id',
        'trans_id',
        'transaction_id',
        'trans_type',
        'trans_time',
        'trans_amount',
        'amount',
        'currency',
        'business_short_code',
        'bill_ref_number',
        'invoice_number',
        'org_account_balance',
        'third_party_trans_id',
        'msisdn',
        'phone_number',
        'first_name',
        'middle_name',
        'last_name',
        'status', // pending, completed, failed, reversed, unallocated
        'result_desc',
        'checkout_request_id',
        'mpesa_conversation_id',
        'mpesa_originator_conversation_id',
        'reversal_transaction_id',
        'is_matched',
        'allocation_status', // unallocated, allocated, refunded
        'raw_payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'trans_amount' => 'decimal:2',
        'org_account_balance' => 'decimal:2',
        'is_matched' => 'boolean',
        'raw_payload' => 'array',
        'trans_time' => 'datetime',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('customer'), 'customer_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(static::modelClass('sale'), 'sale_id');
    }
}
