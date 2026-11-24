<?php

namespace Ongudidan\PaymentsEngine\Tests\Unit;

use Ongudidan\PaymentsEngine\Models\PaymentAccount;
use Ongudidan\PaymentsEngine\Services\PaymentAccountService;
use Ongudidan\PaymentsEngine\Tests\TestCase;

class DenominationHealingTest extends TestCase
{
    protected PaymentAccountService $accountService;

    protected PaymentAccount $vaultAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountService = app(PaymentAccountService::class);

        $this->vaultAccount = PaymentAccount::create([
            'name' => 'Main Cash Vault',
            'type' => 'cash',
            'balance' => 8700.00, // Ledger balance expects 8,700
            'is_vault' => true,
            'has_denominations' => true,
            'is_active' => true,
        ]);
    }

    public function test_heal_denomination_balance_detects_exact_balance(): void
    {
        // 8 x 1000 (8000) + 1 x 500 (500) + 1 x 200 (200) = 8700
        $counts = [
            1000 => 8,
            500 => 1,
            200 => 1,
            100 => 0,
            50 => 0,
        ];

        $result = $this->accountService->healDenominationBalance($this->vaultAccount, $counts);

        $this->assertTrue($result['is_balanced']);
        $this->assertEquals('balanced', $result['status']);
        $this->assertEquals(8700.00, $result['physical_total']);
        $this->assertEquals(0.00, $result['difference']);
        $this->assertNull($result['adjustment_id']);
    }

    public function test_heal_denomination_balance_detects_shortage(): void
    {
        // 7 x 1000 = 7000 (Ledger is 8700, Shortage of 1700)
        $counts = [
            1000 => 7,
            500 => 0,
            200 => 0,
        ];

        $result = $this->accountService->healDenominationBalance($this->vaultAccount, $counts);

        $this->assertFalse($result['is_balanced']);
        $this->assertEquals('shortage', $result['status']);
        $this->assertEquals(7000.00, $result['physical_total']);
        $this->assertEquals(-1700.00, $result['difference']);
        $this->assertNotNull($result['adjustment_id']);
    }

    public function test_heal_denomination_balance_detects_overage(): void
    {
        // 10 x 1000 = 10000 (Ledger is 8700, Overage of 1300)
        $counts = [
            1000 => 10,
        ];

        $result = $this->accountService->healDenominationBalance($this->vaultAccount, $counts);

        $this->assertFalse($result['is_balanced']);
        $this->assertEquals('overage', $result['status']);
        $this->assertEquals(10000.00, $result['physical_total']);
        $this->assertEquals(1300.00, $result['difference']);
        $this->assertNotNull($result['adjustment_id']);
    }
}
