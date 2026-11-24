<?php

namespace Ongudidan\PaymentsEngine\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\PaymentsEngine\Enums\NormalBalance;
use Ongudidan\PaymentsEngine\Exceptions\UnbalancedJournalException;
use Ongudidan\PaymentsEngine\Models\Account;
use Ongudidan\PaymentsEngine\Models\EntityAccountLedger;

class LedgerService
{
    /**
     * Post a set of balanced double-entry lines atomically.
     *
     * Each line array contains:
     * - account_code: string (Chart of Accounts code)
     * - debit: float (>= 0)
     * - credit: float (>= 0)
     * - entry_type: string
     * - reference: string|null
     * - description: string|null
     * - posting_date: string|null (Y-m-d)
     * - customer_id: int|null
     * - supplier_id: int|null
     * - company_id: int|null
     * - branch_id: int|null
     * - source_type: string|null
     * - source_id: int|null
     * - created_by: int|null
     *
     * @param  array<int, array>  $lines
     * @return array<int, EntityAccountLedger>
     *
     * @throws UnbalancedJournalException
     */
    public function postJournal(array $lines): array
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $totalDebit += (float) ($line['debit'] ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.0001) {
            throw new UnbalancedJournalException(
                "Unbalanced double-entry journal: Total Debits ({$totalDebit}) != Total Credits ({$totalCredit})"
            );
        }

        return DB::transaction(function () use ($lines) {
            $posted = [];
            foreach ($lines as $line) {
                $posted[] = $this->postLine($line);
            }

            return $posted;
        });
    }

    /**
     * Post a single line to the entity account ledger and update account running balance.
     */
    public function postLine(array $data): EntityAccountLedger
    {
        $accountCode = $data['account_code'] ?? null;
        $account = Account::where('code', $accountCode)->first();

        if (! $account) {
            $account = Account::create([
                'code' => $accountCode,
                'name' => $data['account_name'] ?? "Account {$accountCode}",
                'type' => $data['account_type'] ?? 'asset',
                'normal_balance' => $data['normal_balance'] ?? 'debit',
                'balance' => 0.00,
                'is_active' => true,
            ]);
        }

        $debit = round((float) ($data['debit'] ?? 0), 4);
        $credit = round((float) ($data['credit'] ?? 0), 4);

        // Calculate account running balance impact based on normal balance
        // Asset/Expense: Debit increases (+), Credit decreases (-)
        // Liability/Equity/Income: Credit increases (+), Debit decreases (-)
        $isNormalDebit = ($account->normal_balance === NormalBalance::DEBIT->value || $account->normal_balance === 'debit');
        $netChange = $isNormalDebit ? ($debit - $credit) : ($credit - $debit);

        $newAccountBalance = (float) $account->balance + $netChange;
        $account->update(['balance' => $newAccountBalance]);

        // Entity running balance (if entity-specific)
        $entityBalanceQuery = EntityAccountLedger::where('account_id', $account->id)
            ->orderByDesc('posting_date')
            ->orderByDesc('id');

        if (! empty($data['customer_id'])) {
            $entityBalanceQuery->where('customer_id', $data['customer_id']);
        } elseif (! empty($data['supplier_id'])) {
            $entityBalanceQuery->where('supplier_id', $data['supplier_id']);
        }

        $lastEntry = $entityBalanceQuery->first();
        $previousEntityBalance = $lastEntry ? (float) $lastEntry->balance : 0.0;
        $newEntityBalance = $previousEntityBalance + $netChange;

        return EntityAccountLedger::create([
            'account_id' => $account->id,
            'account_code' => $account->code,
            'account_type' => $account->type,
            'customer_id' => $data['customer_id'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $newEntityBalance,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'entry_type' => $data['entry_type'] ?? 'General',
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'posting_date' => $data['posting_date'] ?? now()->toDateString(),
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Reverse an existing journal entry or ledger line by posting exact offsetting entries.
     */
    public function reverseLine(EntityAccountLedger $ledgerEntry, string $reason = 'Reversal'): EntityAccountLedger
    {
        return DB::transaction(function () use ($ledgerEntry, $reason) {
            return $this->postLine([
                'account_code' => $ledgerEntry->account_code,
                'debit' => (float) $ledgerEntry->credit,
                'credit' => (float) $ledgerEntry->debit,
                'entry_type' => 'Reversal',
                'reference' => 'REV-'.($ledgerEntry->reference ?: $ledgerEntry->id),
                'description' => "Reversal of #{$ledgerEntry->id}: {$reason}",
                'posting_date' => now()->toDateString(),
                'customer_id' => $ledgerEntry->customer_id,
                'supplier_id' => $ledgerEntry->supplier_id,
                'company_id' => $ledgerEntry->company_id,
                'branch_id' => $ledgerEntry->branch_id,
                'source_type' => $ledgerEntry->source_type,
                'source_id' => $ledgerEntry->source_id,
                'created_by' => auth()->id() ?? $ledgerEntry->created_by,
            ]);
        });
    }

    /**
     * Get statement/ledger entries for an account code.
     */
    public function getAccountStatement(string $accountCode, ?string $startDate = null, ?string $endDate = null)
    {
        $query = EntityAccountLedger::where('account_code', $accountCode)
            ->orderBy('posting_date', 'asc')
            ->orderBy('id', 'asc');

        if ($startDate && $endDate) {
            $query->whereBetween('posting_date', [$startDate, $endDate]);
        }

        return $query->get();
    }
}
