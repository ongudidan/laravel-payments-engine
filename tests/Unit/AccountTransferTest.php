<?php

namespace Ongudidan\PaymentsEngine\Tests\Unit;

use Ongudidan\PaymentsEngine\Enums\TransferStatus;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;
use Ongudidan\PaymentsEngine\Models\PaymentTransaction;
use Ongudidan\PaymentsEngine\Models\PaymentTransactionFlow;
use Ongudidan\PaymentsEngine\Services\PaymentAccountTransferService;
use Ongudidan\PaymentsEngine\Tests\TestCase;

class AccountTransferTest extends TestCase
{
    protected PaymentAccountTransferService $transferService;

    protected PaymentAccount $sourceAccount;

    protected PaymentAccount $destAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transferService = app(PaymentAccountTransferService::class);

        $this->sourceAccount = PaymentAccount::create([
            'name' => 'Cash Vault Alpha',
            'type' => 'cash',
            'balance' => 50000.00,
            'is_active' => true,
        ]);

        $this->destAccount = PaymentAccount::create([
            'name' => 'Bank Account Beta',
            'type' => 'bank',
            'balance' => 10000.00,
            'is_active' => true,
        ]);
    }

    public function test_transfers_below_threshold_are_auto_approved(): void
    {
        // 5000 is below 10,000 threshold
        $transfer = $this->transferService->initiateTransfer([
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id' => $this->destAccount->id,
            'amount' => 5000.00,
            'charges' => 50.00,
            'reference' => 'TRF-AUTO-1',
        ]);

        $this->assertEquals(TransferStatus::APPROVED->value, $transfer->status);

        $this->sourceAccount->refresh();
        $this->destAccount->refresh();

        // 50,000 - (5000 + 50) = 44,950
        $this->assertEquals(44950.00, (float) $this->sourceAccount->balance);
        // 10,000 + 5000 = 15,000
        $this->assertEquals(15000.00, (float) $this->destAccount->balance);
    }

    public function test_transfers_above_threshold_require_checker_approval(): void
    {
        // 25,000 is above 10,000 threshold
        $transfer = $this->transferService->initiateTransfer([
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id' => $this->destAccount->id,
            'amount' => 25000.00,
            'charges' => 100.00,
            'reference' => 'TRF-CHECKER-1',
        ]);

        $this->assertEquals(TransferStatus::PENDING->value, $transfer->status);

        // Funds should NOT be deducted while pending
        $this->sourceAccount->refresh();
        $this->assertEquals(50000.00, (float) $this->sourceAccount->balance);

        // Checker approves
        $this->transferService->approveTransfer($transfer, approvedBy: 1);

        $this->assertEquals(TransferStatus::APPROVED->value, $transfer->fresh()->status);

        $this->sourceAccount->refresh();
        $this->destAccount->refresh();

        // 50,000 - (25000 + 100) = 24,900
        $this->assertEquals(24900.00, (float) $this->sourceAccount->balance);
        // 10,000 + 25,000 = 35,000
        $this->assertEquals(35000.00, (float) $this->destAccount->balance);
    }

    public function test_heal_duplicate_flows_removes_idempotency_duplicates(): void
    {
        $transfer = $this->transferService->initiateTransfer([
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id' => $this->destAccount->id,
            'amount' => 1000.00,
            'charges' => 0.00,
            'reference' => 'TRF-DUP-CHECK',
        ]);

        $tx = PaymentTransaction::where('reference', 'TRF-DUP-CHECK')->first();

        // Intentionally simulate a duplicate network retry flow insertion
        PaymentTransactionFlow::create([
            'payment_transaction_id' => $tx->id,
            'from_account_id' => $this->sourceAccount->id,
            'to_account_id' => $this->destAccount->id,
            'amount' => 1000.00,
            'action' => 'transfer_sent', // duplicate action!
            'created_at' => now(),
        ]);

        $flowsBefore = PaymentTransactionFlow::where('payment_transaction_id', $tx->id)->count();
        $this->assertGreaterThan(2, $flowsBefore);

        // Run healing
        $transfer->healDuplicateFlows();

        $flowsAfter = PaymentTransactionFlow::where('payment_transaction_id', $tx->id)->count();
        $this->assertEquals(2, $flowsAfter); // Exactly one sent and one received flow
    }
}
