<?php

namespace Ongudidan\PaymentsEngine\Services\Reports;

use Carbon\Carbon;
use Ongudidan\PaymentsEngine\Models\Account;
use Ongudidan\PaymentsEngine\Models\JournalItem;

class BalanceSheetReportService
{
    /**
     * Generate Balance Sheet report (Assets = Liabilities + Equity).
     */
    public function generateReport(?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate)->endOfDay() : Carbon::now()->endOfDay();

        $accounts = Account::where('is_active', true)->get();

        $assets = [];
        $liabilities = [];
        $equity = [];

        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($accounts as $acc) {
            $debits = (float) JournalItem::where('account_id', $acc->id)->where('created_at', '<=', $asOf)->sum('debit');
            $credits = (float) JournalItem::where('account_id', $acc->id)->where('created_at', '<=', $asOf)->sum('credit');

            $balance = ($acc->normal_balance === 'debit') ? ($debits - $credits) : ($credits - $debits);
            $balance = round($balance, 2);

            $item = [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'balance' => $balance,
            ];

            if ($acc->type === 'asset') {
                $assets[] = $item;
                $totalAssets += $balance;
            } elseif ($acc->type === 'liability') {
                $liabilities[] = $item;
                $totalLiabilities += $balance;
            } elseif ($acc->type === 'equity') {
                $equity[] = $item;
                $totalEquity += $balance;
            }
        }

        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);
        $totalAssets = round($totalAssets, 2);
        $difference = round($totalAssets - $totalLiabilitiesAndEquity, 2);

        return [
            'as_of_date' => $asOf->toDateString(),
            'assets' => [
                'accounts' => $assets,
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'accounts' => $liabilities,
                'total' => round($totalLiabilities, 2),
            ],
            'equity' => [
                'accounts' => $equity,
                'total' => round($totalEquity, 2),
            ],
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'difference' => $difference,
            'is_balanced' => (abs($difference) < 0.01),
        ];
    }
}
