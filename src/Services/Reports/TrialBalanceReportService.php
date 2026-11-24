<?php

namespace Ongudidan\PaymentsEngine\Services\Reports;

use Carbon\Carbon;
use Ongudidan\PaymentsEngine\Models\Account;
use Ongudidan\PaymentsEngine\Models\JournalItem;

class TrialBalanceReportService
{
    /**
     * Generate Trial Balance report for all accounts.
     */
    public function generateReport(?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate)->endOfDay() : Carbon::now()->endOfDay();

        $accounts = Account::where('is_active', true)->orderBy('code')->get();
        $rows = [];
        $totalDebitBalance = 0.0;
        $totalCreditBalance = 0.0;

        foreach ($accounts as $acc) {
            $debitSum = (float) JournalItem::where('account_id', $acc->id)
                ->where('created_at', '<=', $asOf)
                ->sum('debit');

            $creditSum = (float) JournalItem::where('account_id', $acc->id)
                ->where('created_at', '<=', $asOf)
                ->sum('credit');

            $netDebit = 0.0;
            $netCredit = 0.0;

            if ($acc->normal_balance === 'debit') {
                $net = $debitSum - $creditSum;
                if ($net >= 0) {
                    $netDebit = $net;
                } else {
                    $netCredit = abs($net);
                }
            } else {
                $net = $creditSum - $debitSum;
                if ($net >= 0) {
                    $netCredit = $net;
                } else {
                    $netDebit = abs($net);
                }
            }

            $rows[] = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type,
                'normal_balance' => $acc->normal_balance,
                'total_debit_movement' => $debitSum,
                'total_credit_movement' => $creditSum,
                'debit_balance' => round($netDebit, 2),
                'credit_balance' => round($netCredit, 2),
            ];

            $totalDebitBalance += $netDebit;
            $totalCreditBalance += $netCredit;
        }

        $totalDebitBalance = round($totalDebitBalance, 2);
        $totalCreditBalance = round($totalCreditBalance, 2);
        $difference = round($totalDebitBalance - $totalCreditBalance, 2);

        return [
            'as_of_date' => $asOf->toDateString(),
            'rows' => $rows,
            'total_debit' => $totalDebitBalance,
            'total_credit' => $totalCreditBalance,
            'difference' => $difference,
            'is_balanced' => (abs($difference) < 0.01),
        ];
    }
}
