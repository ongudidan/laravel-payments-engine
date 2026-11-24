<?php

namespace Ongudidan\PaymentsEngine\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Ongudidan\PaymentsEngine\Models\ChequeClearance;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;

class ChequeClearanceService
{
    public function __construct(protected LedgerService $ledgerService) {}

    /**
     * Step 1: Record a new Cheque transaction.
     * Defer balance deductions and GL items until the cheque is cleared.
     */
    public function recordCheque(array $data): ChequeClearance
    {
        return DB::transaction(function () use ($data) {
            $account = PaymentAccount::findOrFail($data['payment_account_id']);
            $amount = round((float) $data['amount'], 2);

            $cheque = ChequeClearance::create([
                'company_id' => $data['company_id'] ?? $account->company_id,
                'branch_id' => $data['branch_id'] ?? $account->branch_id,
                'direction' => $data['direction'] ?? 'inbound',
                'chequeable_type' => $data['chequeable_type'] ?? null,
                'chequeable_id' => $data['chequeable_id'] ?? null,
                'payment_account_id' => $account->id,
                'amount' => $amount,
                'cheque_number' => $data['cheque_number'],
                'cheque_date' => $data['cheque_date'] ?? now()->toDateString(),
                'bank_name' => $data['bank_name'] ?? $account->bank_name,
                'notes' => $data['notes'] ?? null,
                'status' => 'Pending',
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            // Defer GL and balance deductions, only track in pending_cheque_balance
            $account->increment('pending_cheque_balance', $amount);

            return $cheque;
        });
    }

    /**
     * Step 2: Assign Collector.
     * First step before clearance; cheque remains pending.
     */
    public function assignCollector(ChequeClearance $cheque, string $collectorName, ?string $collectorMobile = null, ?string $salesRepName = null): ChequeClearance
    {
        if ($cheque->status === 'Cleared' || $cheque->status === 'Cancelled') {
            throw new Exception("Cannot assign collector to a {$cheque->status} cheque.");
        }

        $cheque->update([
            'status' => 'Collector Assigned',
            'collected_at' => now(),
            'collected_by_name' => $collectorName,
            'collected_by_mobile' => $collectorMobile,
            'sales_rep_name' => $salesRepName,
        ]);

        return $cheque;
    }

    /**
     * Step 3: Clear Cheque.
     * Requires bank transaction reference input to decrement balance and post journal entries.
     */
    public function clearCheque(ChequeClearance $cheque, string $transactionNumber, ?int $clearedBy = null): ChequeClearance
    {
        if (empty(trim($transactionNumber))) {
            throw new Exception('A bank transaction reference number is strictly required to clear a cheque.');
        }

        if ($cheque->status === 'Cleared') {
            return $cheque;
        }

        return DB::transaction(function () use ($cheque, $transactionNumber, $clearedBy) {
            $account = $cheque->paymentAccount;
            $amount = (float) $cheque->amount;

            // Decrement pending cheque balance
            if ($account) {
                $account->decrement('pending_cheque_balance', min((float) $account->pending_cheque_balance, $amount));

                if ($cheque->direction === 'outbound') {
                    // Outbound supplier cheque clearance: reduces actual bank balance
                    $account->decrement('balance', $amount);
                } else {
                    // Inbound customer cheque clearance: increases actual bank balance
                    $account->increment('balance', $amount);
                }
            }

            // Post double-entry general ledger journal entries
            $bankGlCode = $account?->chartOfAccount?->code ?: config('payments-engine.accounts.bank_operating', '1010');
            $offsetGlCode = ($cheque->direction === 'outbound')
                ? config('payments-engine.accounts.accounts_payable', '2000')
                : config('payments-engine.accounts.accounts_receivable', '1200');

            if ($cheque->direction === 'outbound') {
                $lines = [
                    [
                        'account_code' => $offsetGlCode,
                        'debit' => $amount,
                        'credit' => 0,
                        'entry_type' => 'ChequeCleared',
                        'reference' => $transactionNumber,
                        'description' => "Outbound cheque #{$cheque->cheque_number} cleared via {$transactionNumber}",
                    ],
                    [
                        'account_code' => $bankGlCode,
                        'debit' => 0,
                        'credit' => $amount,
                        'entry_type' => 'ChequeCleared',
                        'reference' => $transactionNumber,
                        'description' => "Outbound cheque #{$cheque->cheque_number} cleared via {$transactionNumber}",
                    ],
                ];
            } else {
                $lines = [
                    [
                        'account_code' => $bankGlCode,
                        'debit' => $amount,
                        'credit' => 0,
                        'entry_type' => 'ChequeCleared',
                        'reference' => $transactionNumber,
                        'description' => "Inbound customer cheque #{$cheque->cheque_number} cleared via {$transactionNumber}",
                    ],
                    [
                        'account_code' => $offsetGlCode,
                        'debit' => 0,
                        'credit' => $amount,
                        'entry_type' => 'ChequeCleared',
                        'reference' => $transactionNumber,
                        'description' => "Inbound customer cheque #{$cheque->cheque_number} cleared via {$transactionNumber}",
                    ],
                ];
            }

            $this->ledgerService->postJournal($lines);

            $cheque->update([
                'status' => 'Cleared',
                'transaction_number' => $transactionNumber,
                'cleared_at' => now(),
                'cleared_by' => $clearedBy ?: auth()->id(),
            ]);

            return $cheque;
        });
    }

    /**
     * Bounce or cancel a cheque.
     */
    public function bounceCheque(ChequeClearance $cheque, string $reason, ?int $cancelledBy = null): ChequeClearance
    {
        return DB::transaction(function () use ($cheque, $reason, $cancelledBy) {
            $account = $cheque->paymentAccount;
            if ($account && $cheque->status !== 'Cleared') {
                $account->decrement('pending_cheque_balance', min((float) $account->pending_cheque_balance, (float) $cheque->amount));
            }

            $cheque->update([
                'status' => 'Bounced',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'cancelled_by' => $cancelledBy ?: auth()->id(),
            ]);

            return $cheque;
        });
    }
}
