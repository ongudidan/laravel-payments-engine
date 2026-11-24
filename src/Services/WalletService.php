<?php

namespace Ongudidan\PaymentsEngine\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\PaymentsEngine\Exceptions\InsufficientFundsException;
use Ongudidan\PaymentsEngine\Models\CustomerWalletTransaction;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;
use Ongudidan\PaymentsEngine\Models\SupplierWalletTransaction;

class WalletService
{
    public function __construct(protected LedgerService $ledgerService) {}

    /**
     * Get current customer wallet balance.
     */
    public function getCustomerBalance(int $customerId): float
    {
        $lastTx = CustomerWalletTransaction::where('customer_id', $customerId)
            ->latest('id')
            ->first();

        return $lastTx ? (float) $lastTx->balance_after : 0.00;
    }

    /**
     * Get current supplier advance/wallet balance.
     */
    public function getSupplierBalance(int $supplierId): float
    {
        $lastTx = SupplierWalletTransaction::where('supplier_id', $supplierId)
            ->latest('id')
            ->first();

        return $lastTx ? (float) $lastTx->balance_after : 0.00;
    }

    /**
     * Top-up / Credit Customer Wallet (Escrow Liability 2015).
     *
     * Accounting:
     * - Debit: Cash/Bank Account (Asset)
     * - Credit: Customer Wallet Liability (2015)
     */
    public function creditCustomerWallet(
        int $customerId,
        float $amount,
        ?int $paymentAccountId = null,
        ?string $reference = null,
        ?string $description = null,
        ?int $saleId = null,
        ?int $mpesaTransactionId = null
    ): CustomerWalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($customerId, $amount, $paymentAccountId, $reference, $description, $saleId, $mpesaTransactionId) {
            $currentBalance = $this->getCustomerBalance($customerId);
            $newBalance = round($currentBalance + $amount, 2);

            $walletTx = CustomerWalletTransaction::create([
                'customer_id' => $customerId,
                'sale_id' => $saleId,
                'mpesa_transaction_id' => $mpesaTransactionId,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => $saleId ? 'sale' : ($mpesaTransactionId ? 'mpesa' : 'manual'),
                'reference_id' => $saleId ?: $mpesaTransactionId,
                'reference_number' => $reference ?: ('WLT-CR-'.uniqid()),
                'description' => $description ?: 'Customer wallet top-up / credit',
                'created_by' => auth()->id(),
            ]);

            // Determine accounts for GL posting
            $walletLiabilityCode = config('payments-engine.accounts.customer_wallet', '2015');
            $assetCode = config('payments-engine.accounts.main_vault', '1001');

            if ($paymentAccountId) {
                $paymentAccount = PaymentAccount::find($paymentAccountId);
                if ($paymentAccount) {
                    $assetCode = $paymentAccount->chartOfAccount?->code ?: config('payments-engine.accounts.bank_operating', '1010');
                    $paymentAccount->increment('balance', $amount);
                }
            }

            // Post double-entry
            $this->ledgerService->postJournal([
                [
                    'account_code' => $assetCode,
                    'debit' => $amount,
                    'credit' => 0,
                    'entry_type' => 'WalletTopUp',
                    'customer_id' => $customerId,
                    'reference' => $walletTx->reference_number,
                    'description' => $walletTx->description,
                ],
                [
                    'account_code' => $walletLiabilityCode,
                    'debit' => 0,
                    'credit' => $amount,
                    'entry_type' => 'WalletTopUp',
                    'customer_id' => $customerId,
                    'reference' => $walletTx->reference_number,
                    'description' => $walletTx->description,
                ],
            ]);

            return $walletTx;
        });
    }

    /**
     * Debit Customer Wallet (Spend from Escrow Liability 2015 to settle invoice/sale).
     *
     * Accounting:
     * - Debit: Customer Wallet Liability (2015)
     * - Credit: Accounts Receivable / Revenue (1200 / 4000)
     */
    public function debitCustomerWallet(
        int $customerId,
        float $amount,
        ?string $reference = null,
        ?string $description = null,
        ?int $saleId = null,
        string $targetCreditAccount = '1200'
    ): CustomerWalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($customerId, $amount, $reference, $description, $saleId, $targetCreditAccount) {
            $currentBalance = $this->getCustomerBalance($customerId);
            $allowNegative = (bool) config('payments-engine.wallet.allow_negative_balance', false);

            if (! $allowNegative && ($currentBalance < $amount)) {
                throw new InsufficientFundsException(
                    "Insufficient wallet funds. Current balance: {$currentBalance}, Requested: {$amount}"
                );
            }

            $newBalance = round($currentBalance - $amount, 2);

            $walletTx = CustomerWalletTransaction::create([
                'customer_id' => $customerId,
                'sale_id' => $saleId,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => $saleId ? 'sale' : 'manual',
                'reference_id' => $saleId,
                'reference_number' => $reference ?: ('WLT-DR-'.uniqid()),
                'description' => $description ?: 'Payment from customer wallet',
                'created_by' => auth()->id(),
            ]);

            $walletLiabilityCode = config('payments-engine.accounts.customer_wallet', '2015');
            $creditCode = $targetCreditAccount ?: config('payments-engine.accounts.accounts_receivable', '1200');

            // Post double-entry
            $this->ledgerService->postJournal([
                [
                    'account_code' => $walletLiabilityCode,
                    'debit' => $amount,
                    'credit' => 0,
                    'entry_type' => 'WalletPayment',
                    'customer_id' => $customerId,
                    'reference' => $walletTx->reference_number,
                    'description' => $walletTx->description,
                ],
                [
                    'account_code' => $creditCode,
                    'debit' => 0,
                    'credit' => $amount,
                    'entry_type' => 'WalletPayment',
                    'customer_id' => $customerId,
                    'reference' => $walletTx->reference_number,
                    'description' => $walletTx->description,
                ],
            ]);

            return $walletTx;
        });
    }

    /**
     * Advance / Prepayment to Supplier (Prepayments Asset 1050).
     *
     * Accounting:
     * - Debit: Prepayments / Supplier Advances (Asset 1050)
     * - Credit: Cash/Bank Account (Asset)
     */
    public function advanceSupplier(
        int $supplierId,
        float $amount,
        ?int $paymentAccountId = null,
        ?string $reference = null,
        ?string $description = null
    ): SupplierWalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($supplierId, $amount, $paymentAccountId, $reference, $description) {
            $currentBalance = $this->getSupplierBalance($supplierId);
            $newBalance = round($currentBalance + $amount, 2);

            $walletTx = SupplierWalletTransaction::create([
                'supplier_id' => $supplierId,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => 'advance',
                'reference_number' => $reference ?: ('SUP-ADV-'.uniqid()),
                'description' => $description ?: 'Advance prepayment to supplier',
                'created_by' => auth()->id(),
            ]);

            $advanceAssetCode = config('payments-engine.accounts.supplier_prepayments', '1050');
            $bankCode = config('payments-engine.accounts.bank_operating', '1010');

            if ($paymentAccountId) {
                $paymentAccount = PaymentAccount::find($paymentAccountId);
                if ($paymentAccount) {
                    $bankCode = $paymentAccount->chartOfAccount?->code ?: $bankCode;
                    $paymentAccount->decrement('balance', $amount);
                }
            }

            // Post double-entry
            $this->ledgerService->postJournal([
                [
                    'account_code' => $advanceAssetCode,
                    'debit' => $amount,
                    'credit' => 0,
                    'entry_type' => 'SupplierAdvance',
                    'supplier_id' => $supplierId,
                    'reference' => $walletTx->reference_number,
                    'description' => $walletTx->description,
                ],
                [
                    'account_code' => $bankCode,
                    'debit' => 0,
                    'credit' => $amount,
                    'entry_type' => 'SupplierAdvance',
                    'supplier_id' => $supplierId,
                    'reference' => $walletTx->reference_number,
                    'description' => $walletTx->description,
                ],
            ]);

            return $walletTx;
        });
    }

    /**
     * Settle Supplier Bill using existing advance prepayment (reduces Asset 1050 and AP 2000).
     *
     * Accounting:
     * - Debit: Accounts Payable (2000)
     * - Credit: Prepayments / Supplier Advances (Asset 1050)
     */
    public function debitSupplierAdvance(
        int $supplierId,
        float $amount,
        ?string $reference = null,
        ?string $description = null
    ): SupplierWalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($supplierId, $amount, $reference, $description) {
            $currentBalance = $this->getSupplierBalance($supplierId);
            $newBalance = round($currentBalance - $amount, 2);

            $walletTx = SupplierWalletTransaction::create([
                'supplier_id' => $supplierId,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => 'settlement',
                'reference_number' => $reference ?: ('SUP-SET-'.uniqid()),
                'description' => $description ?: 'Settlement against supplier advance',
                'created_by' => auth()->id(),
            ]);

            $apCode = config('payments-engine.accounts.accounts_payable', '2000');
            $advanceAssetCode = config('payments-engine.accounts.supplier_prepayments', '1050');

            // Post double-entry
            $this->ledgerService->postJournal([
                [
                    'account_code' => $apCode,
                    'debit' => $amount,
                    'credit' => 0,
                    'entry_type' => 'SupplierSettlement',
                    'supplier_id' => $supplierId,
                    'reference' => $walletTx->reference_number,
                    'description' => $walletTx->description,
                ],
                [
                    'account_code' => $advanceAssetCode,
                    'debit' => 0,
                    'credit' => $amount,
                    'entry_type' => 'SupplierSettlement',
                    'supplier_id' => $supplierId,
                    'reference' => $walletTx->reference_number,
                    'description' => $walletTx->description,
                ],
            ]);

            return $walletTx;
        });
    }
}
