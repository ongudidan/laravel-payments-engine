<?php

namespace Ongudidan\PaymentsEngine\Tests\Unit;

use Ongudidan\PaymentsEngine\Exceptions\InsufficientFundsException;
use Ongudidan\PaymentsEngine\Models\Account;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;
use Ongudidan\PaymentsEngine\Services\WalletService;
use Ongudidan\PaymentsEngine\Tests\TestCase;

class WalletServiceTest extends TestCase
{
    protected WalletService $walletService;

    protected PaymentAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);

        $this->bankAccount = PaymentAccount::create([
            'name' => 'Main Test Bank',
            'type' => 'bank',
            'balance' => 50000.00,
            'is_active' => true,
        ]);
    }

    public function test_credit_customer_wallet_updates_balance_and_posts_to_liability_2015(): void
    {
        $customerId = 999;
        $amount = 2500.00;

        $tx = $this->walletService->creditCustomerWallet(
            customerId: $customerId,
            amount: $amount,
            paymentAccountId: $this->bankAccount->id,
            reference: 'TOPUP-TEST-1'
        );

        $this->assertEquals('credit', $tx->type);
        $this->assertEquals(2500.00, (float) $tx->amount);
        $this->assertEquals(2500.00, $this->walletService->getCustomerBalance($customerId));

        $liabilityAccount = Account::where('code', config('payments-engine.accounts.customer_wallet', '2015'))->first();
        $this->assertEquals(2500.00, (float) $liabilityAccount->balance);
    }

    public function test_debit_customer_wallet_prevents_negative_balance_when_disallowed(): void
    {
        $customerId = 888;
        $this->expectException(InsufficientFundsException::class);

        // Attempting to spend 500 without prior balance
        $this->walletService->debitCustomerWallet(
            customerId: $customerId,
            amount: 500.00,
            reference: 'PAY-FAIL'
        );
    }

    public function test_debit_customer_wallet_succeeds_when_funded(): void
    {
        $customerId = 777;

        $this->walletService->creditCustomerWallet(
            customerId: $customerId,
            amount: 3000.00,
            paymentAccountId: $this->bankAccount->id,
            reference: 'TOPUP-OK'
        );

        $debitTx = $this->walletService->debitCustomerWallet(
            customerId: $customerId,
            amount: 1200.00,
            reference: 'INVOICE-PAY-1'
        );

        $this->assertEquals('debit', $debitTx->type);
        $this->assertEquals(1800.00, (float) $debitTx->balance_after);
        $this->assertEquals(1800.00, $this->walletService->getCustomerBalance($customerId));
    }

    public function test_supplier_advance_updates_asset_1050_and_settlement_reduces_it(): void
    {
        $supplierId = 555;
        $advanceAmount = 10000.00;

        $advTx = $this->walletService->advanceSupplier(
            supplierId: $supplierId,
            amount: $advanceAmount,
            paymentAccountId: $this->bankAccount->id,
            reference: 'ADV-555'
        );

        $this->assertEquals(10000.00, $this->walletService->getSupplierBalance($supplierId));

        $prepaymentAccount = Account::where('code', config('payments-engine.accounts.supplier_prepayments', '1050'))->first();
        $this->assertEquals(10000.00, (float) $prepaymentAccount->balance);

        // Settle a bill against the advance
        $settleTx = $this->walletService->debitSupplierAdvance(
            supplierId: $supplierId,
            amount: 4000.00,
            reference: 'BILL-SETTLE-1'
        );

        $this->assertEquals(6000.00, (float) $settleTx->balance_after);
        $this->assertEquals(6000.00, $this->walletService->getSupplierBalance($supplierId));
    }
}
