<?php

namespace Ongudidan\PaymentsEngine\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Ongudidan\PaymentsEngine\Models\CashDrawerTransaction;
use Ongudidan\PaymentsEngine\Models\CashReconciliation;
use Ongudidan\PaymentsEngine\Models\FloatRequest;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;

class CashDrawerService
{
    public function __construct(protected PaymentAccountTransferService $transferService) {}

    /**
     * Request float injection from vault to cashier cash drawer.
     */
    public function requestFloat(PaymentAccount $vault, PaymentAccount $drawer, float $amount, int $cashierId, ?string $notes = null): FloatRequest
    {
        return FloatRequest::create([
            'vault_account_id' => $vault->id,
            'drawer_id' => $drawer->id,
            'cashier_id' => $cashierId,
            'amount' => $amount,
            'status' => 'Pending',
            'request_notes' => $notes,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Approve and disburse float from vault to drawer.
     */
    public function approveFloat(FloatRequest $request, int $approverId, ?string $notes = null): FloatRequest
    {
        if ($request->status !== 'Pending') {
            throw new Exception('Only pending float requests can be approved.');
        }

        return DB::transaction(function () use ($request, $approverId, $notes) {
            $vault = $request->vault;
            $drawer = $request->drawer;
            $amount = (float) $request->amount;

            // Execute inter-account transfer
            $this->transferService->initiateTransfer([
                'from_account_id' => $vault->id,
                'to_account_id' => $drawer->id,
                'amount' => $amount,
                'reference' => 'FLT-'.$request->id,
                'notes' => "Float injection to drawer {$drawer->name}",
            ]);

            CashDrawerTransaction::create([
                'drawer_id' => $drawer->id,
                'transaction_type' => 'float_in',
                'amount' => $amount,
                'performed_by' => $approverId,
                'notes' => $notes ?: "Approved float request #{$request->id}",
                'reference_number' => 'FLT-'.$request->id,
            ]);

            $request->update([
                'status' => 'Approved',
                'approver_id' => $approverId,
                'approver_notes' => $notes,
                'approved_at' => now(),
            ]);

            return $request;
        });
    }

    /**
     * Record cashier mid-shift cash drop or payout from drawer.
     */
    public function recordDrop(PaymentAccount $drawer, float $amount, int $cashierId, ?string $notes = null): CashDrawerTransaction
    {
        return DB::transaction(function () use ($drawer, $amount, $cashierId, $notes) {
            $drawer->decrement('balance', $amount);

            return CashDrawerTransaction::create([
                'drawer_id' => $drawer->id,
                'transaction_type' => 'drop_out',
                'amount' => $amount,
                'performed_by' => $cashierId,
                'notes' => $notes ?: 'Mid-shift cash drop',
                'reference_number' => 'DROP-'.date('YmdHis'),
            ]);
        });
    }

    /**
     * Submit end-of-shift cash drawer reconciliation.
     */
    public function submitReconciliation(PaymentAccount $vault, PaymentAccount $drawer, float $amountSubmitted, int $cashierId, ?string $notes = null): CashReconciliation
    {
        $expected = (float) $drawer->balance;
        $difference = round($amountSubmitted - $expected, 2);

        return CashReconciliation::create([
            'vault_account_id' => $vault->id,
            'drawer_id' => $drawer->id,
            'cashier_id' => $cashierId,
            'amount_submitted' => $amountSubmitted,
            'amount_expected' => $expected,
            'difference' => $difference,
            'status' => 'Pending',
            'submitted_at' => now(),
            'cashier_notes' => $notes,
        ]);
    }

    /**
     * Approve end-of-shift reconciliation and sweep physical cash to vault.
     */
    public function approveReconciliation(CashReconciliation $recon, int $approverId, ?string $notes = null): CashReconciliation
    {
        if ($recon->status !== 'Pending') {
            throw new Exception('Only pending reconciliations can be approved.');
        }

        return DB::transaction(function () use ($recon, $approverId, $notes) {
            $vault = $recon->vault;
            $drawer = $recon->drawer;
            $submitted = (float) $recon->amount_submitted;

            if ($submitted > 0) {
                $transfer = $this->transferService->initiateTransfer([
                    'from_account_id' => $drawer->id,
                    'to_account_id' => $vault->id,
                    'amount' => $submitted,
                    'reference' => 'RECON-'.$recon->id,
                    'notes' => "End-of-shift reconciliation sweep from {$drawer->name} to {$vault->name}",
                ]);

                if ($transfer->status === 'pending') {
                    $this->transferService->approveTransfer($transfer, $approverId);
                }
            }

            $recon->update([
                'status' => 'Approved',
                'approver_id' => $approverId,
                'approver_notes' => $notes,
                'approved_at' => now(),
            ]);

            return $recon;
        });
    }
}
