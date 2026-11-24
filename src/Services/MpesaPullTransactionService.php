<?php

namespace Ongudidan\PaymentsEngine\Services;

use Carbon\Carbon;
use Ongudidan\PaymentsEngine\Models\MpesaPullRequest;
use Ongudidan\PaymentsEngine\Models\MpesaPullTransaction;
use Ongudidan\PaymentsEngine\Models\MpesaTransaction;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;

class MpesaPullTransactionService
{
    public function __construct(
        protected MpesaTransactionService $mpesaTransactionService,
        protected MpesaSettlementService $settlementService
    ) {}

    /**
     * Record a batch of pulled M-Pesa transactions.
     */
    public function recordPulledTransactions(PaymentAccount $account, array $transactionsData, ?MpesaPullRequest $pullRequest = null): array
    {
        $savedCount = 0;
        $matchedCount = 0;

        foreach ($transactionsData as $tx) {
            $transId = $tx['trans_id'] ?? $tx['TransID'] ?? null;
            if (! $transId) {
                continue;
            }

            $amount = (float) ($tx['trans_amount'] ?? $tx['TransAmount'] ?? 0);
            $billRef = $tx['bill_ref_number'] ?? $tx['BillRefNumber'] ?? null;
            $msisdn = $tx['msisdn'] ?? $tx['MSISDN'] ?? null;

            $pullTx = MpesaPullTransaction::firstOrCreate(
                [
                    'payment_account_id' => $account->id,
                    'trans_id' => $transId,
                ],
                [
                    'mpesa_pull_request_id' => $pullRequest?->id,
                    'transaction_type' => $tx['transaction_type'] ?? 'CustomerPayBillOnline',
                    'trans_time' => isset($tx['trans_time']) ? Carbon::parse($tx['trans_time']) : now(),
                    'trans_amount' => $amount,
                    'business_short_code' => $tx['business_short_code'] ?? $account->account_number,
                    'bill_ref_number' => $billRef,
                    'invoice_number' => $tx['invoice_number'] ?? null,
                    'org_account_balance' => (float) ($tx['org_account_balance'] ?? 0),
                    'msisdn' => $msisdn,
                    'first_name' => $tx['first_name'] ?? null,
                    'middle_name' => $tx['middle_name'] ?? null,
                    'last_name' => $tx['last_name'] ?? null,
                    'reconciliation_status' => 'pending',
                    'raw_payload' => $tx,
                ]
            );

            $savedCount++;

            // Reconcile with mpesa_transactions table
            $existingCoreTx = MpesaTransaction::where('transaction_id', $transId)
                ->orWhere('trans_id', $transId)
                ->first();

            if ($existingCoreTx) {
                $pullTx->update([
                    'reconciliation_status' => 'matched',
                    'matched_mpesa_transaction_id' => $existingCoreTx->id,
                    'reconciled_at' => now(),
                ]);
                $matchedCount++;
            } else {
                // If missing in core transactions, auto-ingest through Confirmation webhook pipeline
                $ingested = $this->mpesaTransactionService->handleC2bConfirmation([
                    'TransID' => $transId,
                    'TransactionType' => $pullTx->transaction_type,
                    'TransAmount' => $amount,
                    'BusinessShortCode' => $pullTx->business_short_code,
                    'BillRefNumber' => $billRef,
                    'MSISDN' => $msisdn,
                    'FirstName' => $pullTx->first_name,
                    'MiddleName' => $pullTx->middle_name,
                    'LastName' => $pullTx->last_name,
                ]);

                $pullTx->update([
                    'reconciliation_status' => 'ingested',
                    'matched_mpesa_transaction_id' => $ingested->id,
                    'reconciled_at' => now(),
                ]);
            }
        }

        return [
            'total_processed' => count($transactionsData),
            'saved' => $savedCount,
            'matched' => $matchedCount,
        ];
    }
}
