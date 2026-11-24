<?php

namespace Ongudidan\PaymentsEngine\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\PaymentsEngine\Enums\TransferStatus;
use Ongudidan\PaymentsEngine\Events\PaymentAccountTransferred;
use Ongudidan\PaymentsEngine\Exceptions\InsufficientFundsException;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;
use Ongudidan\PaymentsEngine\Models\PaymentAccountTransfer;
use Ongudidan\PaymentsEngine\Models\PaymentTransaction;
use Ongudidan\PaymentsEngine\Models\PaymentTransactionFlow;

class PaymentAccountTransferService
{
    public function __construct(protected LedgerService $ledgerService) {}

    /**
     * Initiate a transfer between two payment accounts.
     */
    public function initiateTransfer(array $data): PaymentAccountTransfer
    {
        $fromAccount = PaymentAccount::findOrFail($data['from_account_id']);
        $toAccount = PaymentAccount::findOrFail($data['to_account_id']);

        if ($fromAccount->id === $toAccount->id) {
            throw new \InvalidArgumentException('Source and destination accounts must be distinct.');
        }

        $amount = round((float) $data['amount'], 2);
        $charges = round((float) ($data['charges'] ?? 0), 2);
        $totalRequired = $amount + $charges;

        if ((float) $fromAccount->balance < $totalRequired) {
            throw new InsufficientFundsException(
                "Source account has insufficient funds. Balance: {$fromAccount->balance}, Required: {$totalRequired}"
            );
        }

        $requireMakerChecker = (bool) config('payments-engine.transfers.require_maker_checker', true);
        $maxUnapproved = (float) config('payments-engine.transfers.max_unapproved_amount', 10000);

        $transferNo = 'TRF-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));
        $reference = $data['reference'] ?? $transferNo;

        return DB::transaction(function () use ($data, $fromAccount, $toAccount, $amount, $charges, $requireMakerChecker, $maxUnapproved, $transferNo, $reference) {
            $isAutoApproved = ! $requireMakerChecker || ($amount <= $maxUnapproved);

            $transfer = PaymentAccountTransfer::create([
                'transfer_no' => $transferNo,
                'from_account_id' => $fromAccount->id,
                'to_account_id' => $toAccount->id,
                'amount' => $amount,
                'charges' => $charges,
                'received_amount' => $data['received_amount'] ?? $amount,
                'reference' => $reference,
                'notes' => $data['notes'] ?? null,
                'status' => $isAutoApproved ? TransferStatus::APPROVED->value : TransferStatus::PENDING->value,
                'initiated_by' => $data['initiated_by'] ?? auth()->id(),
                'performed_by' => $data['performed_by'] ?? auth()->id(),
                'approved_by' => $isAutoApproved ? ($data['initiated_by'] ?? auth()->id()) : null,
                'approved_at' => $isAutoApproved ? now() : null,
            ]);

            if ($isAutoApproved) {
                $this->executeTransferAccounting($transfer);
            }

            return $transfer;
        });
    }

    /**
     * Approve a pending payment account transfer (Checker step).
     */
    public function approveTransfer(PaymentAccountTransfer $transfer, ?int $approvedBy = null): PaymentAccountTransfer
    {
        if ($transfer->status === TransferStatus::APPROVED->value) {
            return $transfer;
        }

        if ($transfer->status !== TransferStatus::PENDING->value) {
            throw new \InvalidArgumentException('Only pending transfers can be approved.');
        }

        return DB::transaction(function () use ($transfer, $approvedBy) {
            $this->executeTransferAccounting($transfer);

            $transfer->update([
                'status' => TransferStatus::APPROVED->value,
                'approved_by' => $approvedBy ?: auth()->id(),
                'approved_at' => now(),
            ]);

            event(new PaymentAccountTransferred($transfer));

            return $transfer;
        });
    }

    /**
     * Reject a pending payment account transfer.
     */
    public function rejectTransfer(PaymentAccountTransfer $transfer, string $reason, ?int $rejectedBy = null): PaymentAccountTransfer
    {
        if ($transfer->status !== TransferStatus::PENDING->value) {
            throw new \InvalidArgumentException('Only pending transfers can be rejected.');
        }

        $transfer->update([
            'status' => TransferStatus::REJECTED->value,
            'approved_by' => $rejectedBy ?: auth()->id(),
            'rejection_reason' => $reason,
        ]);

        return $transfer;
    }

    /**
     * Execute GL postings and account updates for approved transfer.
     */
    protected function executeTransferAccounting(PaymentAccountTransfer $transfer): void
    {
        $fromAccount = $transfer->fromAccount;
        $toAccount = $transfer->toAccount;
        $amount = (float) $transfer->amount;
        $charges = (float) $transfer->charges;
        $totalDeduction = $amount + $charges;

        // Deduct from sender
        $fromAccount->decrement('balance', $totalDeduction);
        // Credit receiver
        $toAccount->increment('balance', $amount);

        // Record Transaction & Flows
        $tx = PaymentTransaction::create([
            'payment_account_id' => $fromAccount->id,
            'amount' => $amount,
            'reference' => $transfer->reference,
            'notes' => "Transfer to {$toAccount->name}".($charges > 0 ? " (Charges: {$charges})" : ''),
            'status' => 'completed',
            'posted_at' => now(),
        ]);

        PaymentTransactionFlow::create([
            'payment_transaction_id' => $tx->id,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => $amount,
            'action' => 'transfer_sent',
            'created_at' => now(),
        ]);

        PaymentTransactionFlow::create([
            'payment_transaction_id' => $tx->id,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
            'amount' => $amount,
            'action' => 'transfer_received',
            'created_at' => now(),
        ]);

        // GL Double-Entry Posting
        $fromGlCode = $fromAccount->chartOfAccount?->code ?: config('payments-engine.accounts.main_vault', '1001');
        $toGlCode = $toAccount->chartOfAccount?->code ?: config('payments-engine.accounts.bank_operating', '1010');
        $chargesGlCode = config('payments-engine.accounts.transaction_charges', '6000');

        $journalLines = [
            [
                'account_code' => $toGlCode,
                'debit' => $amount,
                'credit' => 0,
                'entry_type' => 'Transfer',
                'reference' => $transfer->reference,
                'description' => "Transfer received from {$fromAccount->name}",
            ],
            [
                'account_code' => $fromGlCode,
                'debit' => 0,
                'credit' => $totalDeduction,
                'entry_type' => 'Transfer',
                'reference' => $transfer->reference,
                'description' => "Transfer sent to {$toAccount->name}",
            ],
        ];

        if ($charges > 0) {
            $journalLines[] = [
                'account_code' => $chargesGlCode,
                'debit' => $charges,
                'credit' => 0,
                'entry_type' => 'TransferFee',
                'reference' => $transfer->reference,
                'description' => "Transfer charges for {$transfer->reference}",
            ];
        }

        $this->ledgerService->postJournal($journalLines);
    }

    /**
     * Heal duplicate transaction flows for a transfer (Idempotency protection).
     */
    public function healDuplicateFlows(PaymentAccountTransfer $transfer): void
    {
        $transfer->healDuplicateFlows();
    }
}
