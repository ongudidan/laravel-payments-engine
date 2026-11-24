<?php

namespace Ongudidan\PaymentsEngine\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\PaymentsEngine\Exceptions\UnbalancedJournalException;
use Ongudidan\PaymentsEngine\Models\Account;
use Ongudidan\PaymentsEngine\Models\JournalEntry;
use Ongudidan\PaymentsEngine\Models\JournalItem;

class AccountingService
{
    /**
     * Post a formal multi-line journal entry with balanced debit and credit items.
     *
     * @param  array  $data  {
     *                       date: string,
     *                       reference: string,
     *                       description: string,
     *                       entry_type: string,
     *                       lines: array [
     *                       {
     *                       account_id: int,
     *                       debit: float,
     *                       credit: float,
     *                       description: string
     *                       }
     *                       ]
     *                       }
     *
     * @throws UnbalancedJournalException
     */
    public function post(array $data): JournalEntry
    {
        $lines = $data['lines'] ?? [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $totalDebit += (float) ($line['debit'] ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new UnbalancedJournalException(
                "Unbalanced journal entry: Total Debits ({$totalDebit}) != Total Credits ({$totalCredit})"
            );
        }

        return DB::transaction(function () use ($data, $lines) {
            $entry = JournalEntry::create([
                'date' => $data['date'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? ('JRN-'.strtoupper(substr(uniqid(), -6))),
                'description' => $data['description'] ?? null,
                'entry_type' => $data['entry_type'] ?? 'General',
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            foreach ($lines as $line) {
                $account = Account::findOrFail($line['account_id']);
                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);

                JournalItem::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $account->id,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $line['description'] ?? $entry->description,
                    'company_id' => $entry->company_id,
                ]);

                // Update account running balance
                $isNormalDebit = ($account->normal_balance === 'debit');
                $change = $isNormalDebit ? ($debit - $credit) : ($credit - $debit);
                $account->increment('balance', $change);
            }

            return $entry->load('items.account');
        });
    }
}
