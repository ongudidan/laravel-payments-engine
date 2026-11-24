<?php

namespace Ongudidan\PaymentsEngine\Tests\Unit;

use Exception;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;
use Ongudidan\PaymentsEngine\Services\ChequeClearanceService;
use Ongudidan\PaymentsEngine\Tests\TestCase;

class ChequeClearanceServiceTest extends TestCase
{
    protected ChequeClearanceService $chequeService;

    protected PaymentAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chequeService = app(ChequeClearanceService::class);

        $this->bankAccount = PaymentAccount::create([
            'name' => 'Corporate Bank Account',
            'type' => 'bank',
            'balance' => 100000.00,
            'pending_cheque_balance' => 0.00,
            'is_active' => true,
        ]);
    }

    public function test_cheque_lifecycle_defers_balance_deductions_until_cleared_with_bank_reference(): void
    {
        // 1. Issue/Record Outbound Supplier Cheque
        $cheque = $this->chequeService->recordCheque([
            'payment_account_id' => $this->bankAccount->id,
            'amount' => 15000.00,
            'cheque_number' => 'CHQ-882299',
            'bank_name' => 'KCB Bank',
            'direction' => 'outbound',
        ]);

        $this->assertEquals('Pending', $cheque->status);

        // Account balance is untouched, pending_cheque_balance is updated
        $this->bankAccount->refresh();
        $this->assertEquals(100000.00, (float) $this->bankAccount->balance);
        $this->assertEquals(15000.00, (float) $this->bankAccount->pending_cheque_balance);

        // 2. Assign Collector
        $this->chequeService->assignCollector($cheque, 'John Doe Collector', '254711223344');
        $this->assertEquals('Collector Assigned', $cheque->fresh()->status);
        $this->assertEquals('John Doe Collector', $cheque->fresh()->collected_by_name);

        // 3. Attempt clearance without transaction reference should fail
        $failed = false;
        try {
            $this->chequeService->clearCheque($cheque, '');
        } catch (Exception $e) {
            $failed = true;
        }
        $this->assertTrue($failed);

        // 4. Clear Cheque with valid bank transaction reference
        $cleared = $this->chequeService->clearCheque($cheque, 'TXN-BANK-REF-998877');
        $this->assertEquals('Cleared', $cleared->status);
        $this->assertEquals('TXN-BANK-REF-998877', $cleared->transaction_number);

        // Balance should now be deducted and pending balance cleared
        $this->bankAccount->refresh();
        $this->assertEquals(85000.00, (float) $this->bankAccount->balance);
        $this->assertEquals(0.00, (float) $this->bankAccount->pending_cheque_balance);
    }
}
