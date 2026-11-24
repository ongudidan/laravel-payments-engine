<?php

namespace Ongudidan\PaymentsEngine\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MpesaPullTransaction extends BaseModel
{
    protected $baseTable = 'mpesa_pull_transactions';

    protected $fillable = [
        'payment_account_id',
        'mpesa_pull_request_id',
        'trans_id',
        'transaction_type',
        'trans_time',
        'trans_amount',
        'business_short_code',
        'bill_ref_number',
        'invoice_number',
        'org_account_balance',
        'third_party_trans_id',
        'msisdn',
        'first_name',
        'middle_name',
        'last_name',
        'reconciliation_status',
        'matched_mpesa_transaction_id',
        'matched_sale_id',
        'matched_customer_id',
        'reconciled_at',
        'reconciled_by',
        'raw_payload',
    ];

    protected $casts = [
        'trans_amount' => 'decimal:2',
        'org_account_balance' => 'decimal:2',
        'trans_time' => 'datetime',
        'reconciled_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(MpesaPullRequest::class, 'mpesa_pull_request_id');
    }
}
