<?php

namespace Ongudidan\PaymentsEngine\Tests\Unit;

use Ongudidan\PaymentsEngine\Models\PaymentAccount;
use Ongudidan\PaymentsEngine\Services\CashDrawerService;
use Ongudidan\PaymentsEngine\Tests\TestCase;

class CashDrawerServiceTest extends TestCase
{
    protected CashDrawerService $drawerService;

    protected PaymentAccount $vault;

    protected PaymentAccount $drawer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->drawerService = app(CashDrawerService::class);

        $this->vault = PaymentAccount::create([
            'name' => 'Main Head Vault',
            'type' => 'cash',
            'balance' => 80000.00,
            'is_vault' => true,
            'is_active' => true,
        ]);

        $this->drawer = PaymentAccount::create([
            'name' => 'POS Till Drawer 1',
            'type' => 'cash',
            'balance' => 0.00,
            'is_active' => true,
        ]);
    }

    public function test_float_request_and_approval_moves_cash_from_vault_to_drawer(): void
    {
        $request = $this->drawerService->requestFloat(
            vault: $this->vault,
            drawer: $this->drawer,
            amount: 5000.00,
            cashierId: 1,
            notes: 'Morning shift float'
        );

        $this->assertEquals('Pending', $request->status);

        $this->drawerService->approveFloat($request, approverId: 2);

        $this->vault->refresh();
        $this->drawer->refresh();

        $this->assertEquals(75000.00, (float) $this->vault->balance);
        $this->assertEquals(5000.00, (float) $this->drawer->balance);
    }

    public function test_end_of_shift_reconciliation_and_approval_sweeps_cash_to_vault(): void
    {
        // Drawer has 12,000 cash at end of shift
        $this->drawer->update(['balance' => 12000.00]);

        $recon = $this->drawerService->submitReconciliation(
            vault: $this->vault,
            drawer: $this->drawer,
            amountSubmitted: 12000.00,
            cashierId: 1
        );

        $this->assertEquals(0.00, (float) $recon->difference);

        $this->drawerService->approveReconciliation($recon, approverId: 2);

        $this->vault->refresh();
        $this->drawer->refresh();

        $this->assertEquals(92000.00, (float) $this->vault->balance);
        $this->assertEquals(0.00, (float) $this->drawer->balance);
    }
}
