<?php

namespace Ongudidan\PaymentsEngine\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ongudidan\PaymentsEngine\Models\Account;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;
use Ongudidan\PaymentsEngine\Services\MpesaTransactionService;
use Ongudidan\PaymentsEngine\Services\WalletService;
use Ongudidan\PaymentsEngine\Tests\TestCase;

class MpesaTierMatchingTest extends TestCase
{
    protected MpesaTransactionService $mpesaService;

    protected WalletService $walletService;

    protected PaymentAccount $mpesaAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mpesaService = app(MpesaTransactionService::class);
        $this->walletService = app(WalletService::class);

        $this->mpesaAccount = PaymentAccount::create([
            'name' => 'Paybill 123456',
            'type' => 'mpesa_paybill',
            'account_number' => '123456',
            'balance' => 0.00,
            'is_active' => true,
        ]);

        if (! Schema::hasTable('sales')) {
            Schema::create('sales', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->unique();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    public function test_tier1_exact_match_to_invoice_number(): void
    {
        DB::table('sales')->insert([
            'id' => 101,
            'invoice_number' => 'INV-2026-001',
            'customer_id' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'TransID' => 'TIER1_EXACT_99',
            'TransactionType' => 'CustomerPayBillOnline',
            'TransAmount' => '5000.00',
            'BusinessShortCode' => '123456',
            'BillRefNumber' => 'INV-2026-001',
            'MSISDN' => '254711223344',
            'FirstName' => 'Jane',
            'LastName' => 'Doe',
        ];

        $tx = $this->mpesaService->handleC2bConfirmation($payload);

        $this->assertEquals('TIER1_EXACT_99', $tx->trans_id);
        $this->assertTrue($tx->is_matched);
        $this->assertEquals('allocated', $tx->allocation_status);
        $this->assertEquals(101, $tx->sale_id);
        $this->assertEquals(42, $tx->customer_id);
    }

    public function test_tier2_customer_phone_match_credits_customer_wallet(): void
    {
        DB::table('customers')->insert([
            'id' => 202,
            'name' => 'VIP Customer',
            'phone' => '0712345678',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'TransID' => 'TIER2_PHONE_88',
            'TransactionType' => 'CustomerPayBillOnline',
            'TransAmount' => '2000.00',
            'BusinessShortCode' => '123456',
            'BillRefNumber' => 'NO_MATCHING_INVOICE',
            'MSISDN' => '254712345678',
            'FirstName' => 'VIP',
            'LastName' => 'Buyer',
        ];

        $tx = $this->mpesaService->handleC2bConfirmation($payload);

        $this->assertEquals('TIER2_PHONE_88', $tx->trans_id);
        $this->assertTrue($tx->is_matched);
        $this->assertEquals('allocated', $tx->allocation_status);
        $this->assertEquals(202, $tx->customer_id);

        // Verify Customer Wallet balance was credited
        $this->assertEquals(2000.00, $this->walletService->getCustomerBalance(202));
    }

    public function test_tier3_unmatched_payment_falls_back_to_suspense_account_2016(): void
    {
        $payload = [
            'TransID' => 'QWE1234567',
            'TransactionType' => 'CustomerPayBillOnline',
            'TransAmount' => '3500.00',
            'BusinessShortCode' => '123456',
            'BillRefNumber' => 'UNKNOWN_REF_9999',
            'MSISDN' => '254799999999',
            'FirstName' => 'Unknown',
            'LastName' => 'Sender',
        ];

        $tx = $this->mpesaService->handleC2bConfirmation($payload);

        $this->assertEquals('QWE1234567', $tx->trans_id);
        $this->assertEquals(3500.00, (float) $tx->amount);
        $this->assertFalse($tx->is_matched);
        $this->assertEquals('unallocated', $tx->allocation_status);

        // Check Suspense Account 2016 balance
        $suspenseAccount = Account::where('code', config('payments-engine.accounts.unallocated_suspense', '2016'))->first();
        $this->assertEquals(3500.00, (float) $suspenseAccount->balance);
    }
}
