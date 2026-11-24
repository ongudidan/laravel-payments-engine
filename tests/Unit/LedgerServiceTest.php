<?php

namespace Ongudidan\PaymentsEngine\Tests\Unit;

use Ongudidan\PaymentsEngine\Exceptions\UnbalancedJournalException;
use Ongudidan\PaymentsEngine\Models\Account;
use Ongudidan\PaymentsEngine\Services\LedgerService;
use Ongudidan\PaymentsEngine\Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledgerService = app(LedgerService::class);
    }

    public function test_balanced_journal_posting_succeeds_and_updates_account_balance(): void
    {
        $vaultCode = config('payments-engine.accounts.main_vault', '1001');
        $revenueCode = config('payments-engine.accounts.sales_revenue', '4000');

        $initialVault = Account::where('code', $vaultCode)->first();
        $initialBalance = (float) $initialVault->balance;

        $lines = [
            [
                'account_code' => $vaultCode,
                'debit' => 5000.00,
                'credit' => 0.00,
                'entry_type' => 'Sale',
                'description' => 'Cash sale receipt',
            ],
            [
                'account_code' => $revenueCode,
                'debit' => 0.00,
                'credit' => 5000.00,
                'entry_type' => 'Sale',
                'description' => 'Cash sale receipt',
            ],
        ];

        $entries = $this->ledgerService->postJournal($lines);

        $this->assertCount(2, $entries);
        $this->assertEquals(5000.00, (float) $entries[0]->debit);
        $this->assertEquals(5000.00, (float) $entries[1]->credit);

        $updatedVault = Account::where('code', $vaultCode)->first();
        $this->assertEquals($initialBalance + 5000.00, (float) $updatedVault->balance);
    }

    public function test_unbalanced_journal_posting_throws_unbalanced_journal_exception(): void
    {
        $this->expectException(UnbalancedJournalException::class);

        $lines = [
            [
                'account_code' => '1001',
                'debit' => 5000.00,
                'credit' => 0.00,
            ],
            [
                'account_code' => '4000',
                'debit' => 0.00,
                'credit' => 4500.00, // 500 discrepancy
            ],
        ];

        $this->ledgerService->postJournal($lines);
    }

    public function test_reversal_creates_exact_offsetting_ledger_entry(): void
    {
        $vaultCode = config('payments-engine.accounts.main_vault', '1001');
        $revenueCode = config('payments-engine.accounts.sales_revenue', '4000');

        $entries = $this->ledgerService->postJournal([
            ['account_code' => $vaultCode, 'debit' => 1200.00, 'credit' => 0.00, 'reference' => 'TX-100'],
            ['account_code' => $revenueCode, 'debit' => 0.00, 'credit' => 1200.00, 'reference' => 'TX-100'],
        ]);

        $reversed = $this->ledgerService->reverseLine($entries[0], 'Test reversal');

        $this->assertEquals(1200.00, (float) $reversed->credit);
        $this->assertEquals(0.00, (float) $reversed->debit);
        $this->assertStringContainsString('REV-TX-100', $reversed->reference);
    }
}
