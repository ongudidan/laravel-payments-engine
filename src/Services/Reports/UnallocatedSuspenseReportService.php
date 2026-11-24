<?php

namespace Ongudidan\PaymentsEngine\Services\Reports;

use Carbon\Carbon;
use Ongudidan\PaymentsEngine\Models\EntityAccountLedger;
use Ongudidan\PaymentsEngine\Models\MpesaTransaction;

class UnallocatedSuspenseReportService
{
    /**
     * Real-time statement of account 2016 (Unallocated Receipts Suspense).
     */
    public function getSuspenseReport(?string $startDate = null, ?string $endDate = null): array
    {
        $suspenseCode = config('payments-engine.accounts.unallocated_suspense', '2016');

        $ledgerQuery = EntityAccountLedger::where('account_code', $suspenseCode);
        $txQuery = MpesaTransaction::where('allocation_status', 'unallocated');

        if ($startDate && $endDate) {
            $ledgerQuery->whereBetween('posting_date', [$startDate, $endDate]);
            $txQuery->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }

        $totalSuspenseCredits = (clone $ledgerQuery)->sum('credit');
        $totalSuspenseDebits = (clone $ledgerQuery)->sum('debit');
        $netSuspenseBalance = round($totalSuspenseCredits - $totalSuspenseDebits, 2);

        $unallocatedTransactions = $txQuery->latest('id')->get();

        return [
            'suspense_account_code' => $suspenseCode,
            'total_credits' => (float) $totalSuspenseCredits,
            'total_settled_debits' => (float) $totalSuspenseDebits,
            'current_suspense_balance' => (float) $netSuspenseBalance,
            'unallocated_count' => $unallocatedTransactions->count(),
            'unallocated_transactions' => $unallocatedTransactions,
        ];
    }
}
