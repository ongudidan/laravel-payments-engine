<?php

namespace Ongudidan\PaymentsEngine\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\PaymentsEngine\Models\CustomerWalletTransaction;
use Ongudidan\PaymentsEngine\Models\MpesaTransaction;
use Ongudidan\PaymentsEngine\Traits\ResolvesModels;

class MpesaSettlementService
{
    use ResolvesModels;

    public function __construct(
        protected LedgerService $ledgerService,
        protected WalletService $walletService
    ) {}

    /**
     * Settle an unallocated Suspense M-Pesa transaction to an Invoice / Sale.
     *
     * Accounting:
     * - Debit: Unallocated Suspense (2016)
     * - Credit: Accounts Receivable (1200)
     */
    public function settleToInvoice(MpesaTransaction $transaction, int $saleId, ?int $customerId = null): MpesaTransaction
    {
        return DB::transaction(function () use ($transaction, $saleId, $customerId) {
            $amount = (float) $transaction->amount;
            $suspenseCode = config('payments-engine.accounts.unallocated_suspense', '2016');
            $arCode = config('payments-engine.accounts.accounts_receivable', '1200');

            // Reallocate from Suspense to AR
            $this->ledgerService->postJournal([
                [
                    'account_code' => $suspenseCode,
                    'debit' => $amount,
                    'credit' => 0,
                    'entry_type' => 'SuspenseSettlement',
                    'reference' => $transaction->trans_id ?: $transaction->transaction_id,
                    'description' => "Settlement of Suspense M-Pesa transaction to Invoice #{$saleId}",
                ],
                [
                    'account_code' => $arCode,
                    'debit' => 0,
                    'credit' => $amount,
                    'entry_type' => 'SuspenseSettlement',
                    'customer_id' => $customerId,
                    'reference' => $transaction->trans_id ?: $transaction->transaction_id,
                    'description' => "Settlement of Suspense M-Pesa transaction to Invoice #{$saleId}",
                ],
            ]);

            $transaction->update([
                'sale_id' => $saleId,
                'customer_id' => $customerId ?: $transaction->customer_id,
                'is_matched' => true,
                'allocation_status' => 'allocated',
            ]);

            return $transaction;
        });
    }

    /**
     * Settle an unallocated Suspense M-Pesa transaction to Customer Wallet.
     *
     * Accounting:
     * - Debit: Unallocated Suspense (2016)
     * - Credit: Customer Wallet Liability (2015)
     */
    public function settleToCustomerWallet(MpesaTransaction $transaction, int $customerId): MpesaTransaction
    {
        return DB::transaction(function () use ($transaction, $customerId) {
            $amount = (float) $transaction->amount;
            $suspenseCode = config('payments-engine.accounts.unallocated_suspense', '2016');
            $walletLiabilityCode = config('payments-engine.accounts.customer_wallet', '2015');

            // Reallocate from Suspense to Customer Wallet Liability
            $this->ledgerService->postJournal([
                [
                    'account_code' => $suspenseCode,
                    'debit' => $amount,
                    'credit' => 0,
                    'entry_type' => 'SuspenseToWallet',
                    'reference' => $transaction->trans_id ?: $transaction->transaction_id,
                    'description' => "Settlement of Suspense M-Pesa transaction to Customer Wallet #{$customerId}",
                ],
                [
                    'account_code' => $walletLiabilityCode,
                    'debit' => 0,
                    'credit' => $amount,
                    'entry_type' => 'SuspenseToWallet',
                    'customer_id' => $customerId,
                    'reference' => $transaction->trans_id ?: $transaction->transaction_id,
                    'description' => "Settlement of Suspense M-Pesa transaction to Customer Wallet #{$customerId}",
                ],
            ]);

            $currentBalance = $this->walletService->getCustomerBalance($customerId);
            $newBalance = round($currentBalance + $amount, 2);

            CustomerWalletTransaction::create([
                'customer_id' => $customerId,
                'mpesa_transaction_id' => $transaction->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => 'mpesa_suspense_settlement',
                'reference_id' => $transaction->id,
                'reference_number' => $transaction->trans_id ?: ('WLT-SUS-'.$transaction->id),
                'description' => 'Credit from allocated M-Pesa suspense transaction',
                'created_by' => auth()->id(),
            ]);

            $transaction->update([
                'customer_id' => $customerId,
                'is_matched' => true,
                'allocation_status' => 'allocated',
            ]);

            return $transaction;
        });
    }
}
