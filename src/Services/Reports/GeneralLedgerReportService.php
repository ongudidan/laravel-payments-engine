<?php

namespace Ongudidan\PaymentsEngine\Services\Reports;

use Carbon\Carbon;
use Ongudidan\PaymentsEngine\Models\Account;
use Ongudidan\PaymentsEngine\Models\JournalItem;

class GeneralLedgerReportService
{
    /**
     * Generate General Ledger account statement.
     */
    public function getAccountStatement(int $accountId, ?string $startDate = null, ?string $endDate = null): array
    {
        $account = Account::findOrFail($accountId);

        $query = JournalItem::where('account_id', $account->id)
            ->with(['journalEntry'])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }

        $items = $query->get();
        $runningBalance = 0.0;
        $rows = [];

        foreach ($items as $item) {
            $debit = (float) $item->debit;
            $credit = (float) $item->credit;
            $change = ($account->normal_balance === 'debit') ? ($debit - $credit) : ($credit - $debit);
            $runningBalance += $change;

            $rows[] = [
                'id' => $item->id,
                'date' => $item->journalEntry?->date?->toDateString() ?: $item->created_at->toDateString(),
                'reference' => $item->journalEntry?->reference,
                'entry_type' => $item->journalEntry?->entry_type,
                'description' => $item->description ?: $item->journalEntry?->description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => round($runningBalance, 2),
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'normal_balance' => $account->normal_balance,
            ],
            'closing_balance' => round($runningBalance, 2),
            'rows' => $rows,
        ];
    }
}
