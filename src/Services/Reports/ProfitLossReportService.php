<?php

namespace Ongudidan\PaymentsEngine\Services\Reports;

use Ongudidan\PaymentsEngine\Models\EntityAccountLedger;

class ProfitLossReportService
{
    /**
     * Get financial revenues and transaction charges slice for the P&L report.
     */
    public function getPaymentSlice(?string $startDate = null, ?string $endDate = null): array
    {
        $revenueCode = config('payments-engine.accounts.sales_revenue', '4000');
        $chargesCode = config('payments-engine.accounts.transaction_charges', '6000');

        $query = EntityAccountLedger::query();

        if ($startDate && $endDate) {
            $query->whereBetween('posting_date', [$startDate, $endDate]);
        }

        $totalRevenueCredits = (clone $query)->where('account_code', $revenueCode)->sum('credit');
        $totalRevenueDebits = (clone $query)->where('account_code', $revenueCode)->sum('debit');
        $netRevenue = round($totalRevenueCredits - $totalRevenueDebits, 2);

        $totalChargeDebits = (clone $query)->where('account_code', $chargesCode)->sum('debit');
        $totalChargeCredits = (clone $query)->where('account_code', $chargesCode)->sum('credit');
        $netCharges = round($totalChargeDebits - $totalChargeCredits, 2);

        $netFinancialResult = round($netRevenue - $netCharges, 2);

        return [
            'gross_payment_revenue' => (float) $netRevenue,
            'transaction_charges_expense' => (float) $netCharges,
            'net_financial_result' => (float) $netFinancialResult,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }
}
