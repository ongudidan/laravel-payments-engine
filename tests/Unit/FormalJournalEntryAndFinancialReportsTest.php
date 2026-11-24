<?php

namespace Ongudidan\PaymentsEngine\Tests\Unit;

use Ongudidan\PaymentsEngine\Models\Account;
use Ongudidan\PaymentsEngine\Models\MpesaTransaction;
use Ongudidan\PaymentsEngine\Services\AccountingService;
use Ongudidan\PaymentsEngine\Services\Reports\BalanceSheetReportService;
use Ongudidan\PaymentsEngine\Services\Reports\MpesaStatementReconciliationService;
use Ongudidan\PaymentsEngine\Services\Reports\TrialBalanceReportService;
use Ongudidan\PaymentsEngine\Tests\TestCase;

class FormalJournalEntryAndFinancialReportsTest extends TestCase
{
    protected AccountingService $accountingService;

    protected TrialBalanceReportService $trialBalanceService;

    protected BalanceSheetReportService $balanceSheetService;

    protected MpesaStatementReconciliationService $statementReconService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountingService = app(AccountingService::class);
        $this->trialBalanceService = app(TrialBalanceReportService::class);
        $this->balanceSheetService = app(BalanceSheetReportService::class);
        $this->statementReconService = app(MpesaStatementReconciliationService::class);
    }

    public function test_formal_multi_line_journal_entry_posts_and_balances(): void
    {
        $cashAccount = Account::where('code', config('payments-engine.accounts.main_vault', '1001'))->first();
        $revenueAccount = Account::where('code', config('payments-engine.accounts.sales_revenue', '4000'))->first();

        $entry = $this->accountingService->post([
            'date' => now()->toDateString(),
            'reference' => 'JRN-TEST-1',
            'description' => 'Test balanced entry',
            'lines' => [
                ['account_id' => $cashAccount->id, 'debit' => 20000.00, 'credit' => 0.00],
                ['account_id' => $revenueAccount->id, 'debit' => 0.00, 'credit' => 20000.00],
            ],
        ]);

        $this->assertNotNull($entry->id);
        $this->assertCount(2, $entry->items);

        // Check Trial Balance Report
        $tb = $this->trialBalanceService->generateReport();
        $this->assertTrue($tb['is_balanced']);
        $this->assertGreaterThanOrEqual(20000.00, $tb['total_debit']);
        $this->assertEquals($tb['total_debit'], $tb['total_credit']);
    }

    public function test_mpesa_statement_reconciler_identifies_matched_and_missing_transactions(): void
    {
        MpesaTransaction::create([
            'trans_id' => 'REC_MATCH_001',
            'transaction_id' => 'REC_MATCH_001',
            'amount' => 1500.00,
            'status' => 'completed',
        ]);

        $statementData = [
            ['receipt_number' => 'REC_MATCH_001', 'amount' => 1500.00],
            ['receipt_number' => 'REC_MISSING_002', 'amount' => 3200.00, 'phone' => '254711000000'],
        ];

        $recon = $this->statementReconService->reconcileStatement($statementData);

        $this->assertEquals(1, $recon['matched_count']);
        $this->assertEquals(1, $recon['missing_in_system_count']);
        $this->assertEquals('REC_MISSING_002', $recon['missing_in_system'][0]['receipt_number']);
    }
}
