<?php

namespace Ongudidan\PaymentsEngine\Services;

use Carbon\Carbon;
use Ongudidan\PaymentsEngine\Models\MpesaTransaction;
use Ongudidan\PaymentsEngine\Models\PaymentAccountAdjustment;
use Ongudidan\PaymentsEngine\Models\PaymentTransaction;

class PaymentAnomalyReconciliationService
{
    /**
     * Detect discrepancies between payment transactions, recorded flows, and general ledger accounts.
     */
    public function detectTransactionAnomalies(): array
    {
        $anomalies = [];

        // 1. Detect orphaned transactions without flows
        $orphanedTx = PaymentTransaction::whereDoesntHave('flows')
            ->where('status', 'completed')
            ->get();

        foreach ($orphanedTx as $tx) {
            $anomalies[] = [
                'type' => 'orphaned_transaction',
                'severity' => 'warning',
                'transaction_id' => $tx->id,
                'reference' => $tx->reference,
                'amount' => (float) $tx->amount,
                'description' => "Completed payment transaction #{$tx->id} has no recorded flow entries.",
            ];
        }

        // 2. Detect unallocated M-Pesa transactions older than 48 hours
        $cutoff = Carbon::now()->subHours(48);
        $staleSuspense = MpesaTransaction::where('allocation_status', 'unallocated')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($staleSuspense as $tx) {
            $anomalies[] = [
                'type' => 'stale_suspense',
                'severity' => 'info',
                'transaction_id' => $tx->id,
                'reference' => $tx->trans_id ?: $tx->transaction_id,
                'amount' => (float) $tx->amount,
                'description' => "M-Pesa receipt of {$tx->amount} from {$tx->phone_number} has remained unallocated in Suspense (2016) for over 48 hours.",
            ];
        }

        // 3. Detect pending physical adjustments
        $pendingAdjustments = PaymentAccountAdjustment::where('status', 'Pending')
            ->with(['paymentAccount'])
            ->get();

        foreach ($pendingAdjustments as $adj) {
            $anomalies[] = [
                'type' => 'unapproved_cash_discrepancy',
                'severity' => 'high',
                'adjustment_id' => $adj->id,
                'account' => $adj->paymentAccount?->name,
                'amount' => (float) $adj->amount,
                'description' => "Unapproved physical cash variance of {$adj->amount} awaiting checker sign-off on {$adj->paymentAccount?->name}.",
            ];
        }

        return [
            'total_anomalies' => count($anomalies),
            'items' => $anomalies,
        ];
    }
}
