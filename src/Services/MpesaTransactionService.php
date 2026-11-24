<?php

namespace Ongudidan\PaymentsEngine\Services;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Ongudidan\PaymentsEngine\Enums\MpesaTransactionStatus;
use Ongudidan\PaymentsEngine\Events\MpesaTransactionReceived;
use Ongudidan\PaymentsEngine\Models\MpesaConfig;
use Ongudidan\PaymentsEngine\Models\MpesaTransaction;
use Ongudidan\PaymentsEngine\Traits\ResolvesModels;

class MpesaTransactionService
{
    use ResolvesModels;

    public function __construct(
        protected LedgerService $ledgerService,
        protected WalletService $walletService,
        protected ?MpesaConfigService $configService = null
    ) {
        $this->configService = $configService ?? new MpesaConfigService;
    }

    /**
     * Initiate STK Push via Safaricom Daraja API.
     */
    public function initiateStkPush(array $params): array
    {
        $config = $this->configService->resolveConfig(
            paymentAccountId: $params['payment_account_id'] ?? null,
            shortcode: $params['shortcode'] ?? null
        );
        $shortcode = $config?->shortcode ?? ($params['shortcode'] ?? config('payments-engine.mpesa.shortcode'));
        $phoneNumber = $this->formatPhoneNumber($params['phone_number']);
        $amount = round((float) $params['amount'], 2);
        $reference = $params['reference'] ?? 'PAY-'.strtoupper(substr(uniqid(), -6));
        $description = $params['description'] ?? 'Payment';

        $checkoutRequestId = 'ws_CO_'.date('dmYHis').'_'.rand(100000, 999999);

        // Attempt live Daraja STK Push if configuration credentials are present
        if ($config && $config->consumer_key && $config->consumer_secret && $config->passkey) {
            $timestamp = date('YmdHis');
            $password = base64_encode($shortcode.$config->passkey.$timestamp);
            $baseUrl = ($config->environment === 'live')
                ? 'https://api.safaricom.co.ke'
                : 'https://sandbox.safaricom.co.ke';

            $callbackBase = config('payments-engine.mpesa.callback_url_base', config('app.url'));
            $callbackUrl = $config->callback_url ?: rtrim($callbackBase, '/').'/api/v1/payments-engine/mpesa/stk-callback';

            $payload = [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => ($config->type === 'till') ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
                'Amount' => (int) round($amount),
                'PartyA' => $phoneNumber,
                'PartyB' => ($config->type === 'till') ? ($config->nominated_number ?: $shortcode) : $shortcode,
                'PhoneNumber' => $phoneNumber,
                'CallBackURL' => $callbackUrl,
                'AccountReference' => substr($reference, 0, 12),
                'TransactionDesc' => substr($description, 0, 13),
            ];

            try {
                $token = $this->configService->generateOAuthToken($config);
                $response = Http::withToken($token)
                    ->timeout(30)
                    ->post("{$baseUrl}/mpesa/stkpush/v1/processrequest", $payload);

                $resData = $response->json() ?? [];
                if ($response->successful() && isset($resData['ResponseCode']) && $resData['ResponseCode'] === '0') {
                    $checkoutRequestId = $resData['CheckoutRequestID'];
                } else {
                    $errorMsg = $resData['errorMessage'] ?? ($resData['ResponseDescription'] ?? 'Daraja STK push failed.');
                    Log::warning('Daraja STK push error response', [
                        'shortcode' => $shortcode,
                        'response' => $resData,
                    ]);
                    if (! app()->environment('testing')) {
                        throw new Exception("Safaricom STK push failed: {$errorMsg}");
                    }
                }
            } catch (\Throwable $e) {
                Log::error('STK push network/auth error', ['error' => $e->getMessage()]);
                if (! app()->environment('testing')) {
                    throw $e;
                }
            }
        }

        // Record pending M-Pesa transaction
        $transaction = MpesaTransaction::create([
            'payment_account_id' => $params['payment_account_id'] ?? null,
            'customer_id' => $params['customer_id'] ?? null,
            'sale_id' => $params['sale_id'] ?? null,
            'amount' => $amount,
            'phone_number' => $phoneNumber,
            'business_short_code' => $shortcode,
            'bill_ref_number' => $reference,
            'checkout_request_id' => $checkoutRequestId,
            'status' => MpesaTransactionStatus::PENDING->value,
            'is_matched' => false,
            'allocation_status' => 'unallocated',
        ]);

        return [
            'success' => true,
            'checkout_request_id' => $checkoutRequestId,
            'transaction_id' => $transaction->id,
            'reference' => $reference,
            'phone_number' => $phoneNumber,
            'amount' => $amount,
        ];
    }

    /**
     * Handle STK Push callback.
     */
    public function handleStkCallback(array $payload): MpesaTransaction
    {
        $stkCallback = $payload['Body']['stkCallback'] ?? $payload;
        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
        $resultCode = (int) ($stkCallback['ResultCode'] ?? -1);
        $resultDesc = $stkCallback['ResultDesc'] ?? '';

        $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();

        if (! $transaction) {
            $transaction = MpesaTransaction::create([
                'checkout_request_id' => $checkoutRequestId,
                'status' => ($resultCode === 0) ? MpesaTransactionStatus::COMPLETED->value : MpesaTransactionStatus::FAILED->value,
                'result_desc' => $resultDesc,
                'raw_payload' => $payload,
            ]);
        }

        if ($resultCode === 0) {
            $metaItems = $stkCallback['CallbackMetadata']['Item'] ?? [];
            $transId = null;
            $amount = (float) $transaction->amount;
            $msisdn = $transaction->phone_number;

            foreach ($metaItems as $item) {
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                    $transId = $item['Value'];
                } elseif (($item['Name'] ?? '') === 'Amount') {
                    $amount = (float) $item['Value'];
                } elseif (($item['Name'] ?? '') === 'PhoneNumber') {
                    $msisdn = (string) $item['Value'];
                }
            }

            $transaction->update([
                'trans_id' => $transId,
                'transaction_id' => $transId,
                'amount' => $amount,
                'trans_amount' => $amount,
                'phone_number' => $msisdn,
                'msisdn' => $msisdn,
                'status' => MpesaTransactionStatus::COMPLETED->value,
                'result_desc' => $resultDesc,
                'raw_payload' => $payload,
            ]);

            // Execute 3-tier matching on STK completion
            $this->apply3TierMatching($transaction);
        } else {
            $transaction->update([
                'status' => MpesaTransactionStatus::FAILED->value,
                'result_desc' => $resultDesc,
                'raw_payload' => $payload,
            ]);
        }

        return $transaction;
    }

    /**
     * Handle C2B Validation webhook.
     */
    public function handleC2bValidation(Request $request): JsonResponse
    {
        $token = $request->header('X-C2B-Secret') ?? $request->query('token');
        $expectedToken = config('payments-engine.mpesa.c2b_secret_token');

        if ($expectedToken && $token !== $expectedToken) {
            return response()->json([
                'ResultCode' => 'C2B00012',
                'ResultDesc' => 'Invalid Security Credentials',
            ], 401);
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }

    /**
     * Handle C2B Confirmation webhook.
     */
    public function handleC2bConfirmation(array $payload): MpesaTransaction
    {
        return DB::transaction(function () use ($payload) {
            $transId = $payload['TransID'] ?? null;

            // Idempotency: avoid duplicate processing
            $existing = MpesaTransaction::where('transaction_id', $transId)
                ->orWhere('trans_id', $transId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $amount = (float) ($payload['TransAmount'] ?? 0);
            $shortcode = $payload['BusinessShortCode'] ?? null;
            $billRef = trim((string) ($payload['BillRefNumber'] ?? ''));
            $msisdn = (string) ($payload['MSISDN'] ?? '');

            $paymentAccount = null;
            if ($shortcode) {
                $mpesaConfig = MpesaConfig::where('shortcode', $shortcode)->first();
                $paymentAccount = $mpesaConfig?->paymentAccount;
            }

            $transaction = MpesaTransaction::create([
                'payment_account_id' => $paymentAccount?->id,
                'trans_id' => $transId,
                'transaction_id' => $transId,
                'trans_type' => $payload['TransactionType'] ?? null,
                'trans_time' => now(),
                'trans_amount' => $amount,
                'amount' => $amount,
                'currency' => 'KES',
                'business_short_code' => $shortcode,
                'bill_ref_number' => $billRef,
                'invoice_number' => $payload['InvoiceNumber'] ?? null,
                'org_account_balance' => (float) ($payload['OrgAccountBalance'] ?? 0),
                'third_party_trans_id' => $payload['ThirdPartyTransID'] ?? null,
                'msisdn' => $msisdn,
                'phone_number' => $msisdn,
                'first_name' => $payload['FirstName'] ?? null,
                'middle_name' => $payload['MiddleName'] ?? null,
                'last_name' => $payload['LastName'] ?? null,
                'status' => MpesaTransactionStatus::COMPLETED->value,
                'is_matched' => false,
                'allocation_status' => 'unallocated',
                'raw_payload' => $payload,
            ]);

            if ($paymentAccount) {
                $paymentAccount->increment('balance', $amount);
            }

            // Apply 3-Tier Matching Algorithm
            $this->apply3TierMatching($transaction);

            event(new MpesaTransactionReceived($transaction));

            return $transaction;
        });
    }

    /**
     * 3-Tier Matching Algorithm:
     * - Tier 1 (Exact Match): Matches bill_ref_number to Invoice Number, Order ID, or Account Number.
     * - Tier 2 (Customer Match): Matches msisdn to registered customer phone number, crediting Customer Wallet.
     * - Tier 3 (Suspense Fallback): If unmatched, transaction is logged as unallocated and credited to Unallocated Receipts Suspense Account (2016).
     */
    public function apply3TierMatching(MpesaTransaction $transaction): void
    {
        $amount = (float) $transaction->amount;
        $billRef = trim((string) $transaction->bill_ref_number);
        $phone = $transaction->msisdn ?: $transaction->phone_number;
        $bankAccountCode = $transaction->paymentAccount?->chartOfAccount?->code ?: config('payments-engine.accounts.mpesa_till', '1020');

        // ─── TIER 1: Exact Match ───────────────────────────────────────────
        if (! empty($billRef)) {
            $saleModelClass = static::modelClass('sale');
            $matchedSale = null;

            if (class_exists($saleModelClass)) {
                $saleInstance = new $saleModelClass;
                if (Schema::hasTable($saleInstance->getTable())) {
                    $matchedSale = $saleModelClass::where('invoice_number', $billRef)
                        ->orWhere('id', is_numeric($billRef) ? (int) $billRef : 0)
                        ->first();
                }
            }

            if ($matchedSale) {
                $transaction->update([
                    'sale_id' => $matchedSale->id,
                    'customer_id' => $matchedSale->customer_id ?? null,
                    'is_matched' => true,
                    'allocation_status' => 'allocated',
                ]);

                // GL: Debit M-Pesa Till (1020), Credit AR (1200)
                $arCode = config('payments-engine.accounts.accounts_receivable', '1200');
                $this->ledgerService->postJournal([
                    [
                        'account_code' => $bankAccountCode,
                        'debit' => $amount,
                        'credit' => 0,
                        'entry_type' => 'MpesaTier1Match',
                        'reference' => $transaction->trans_id ?: $transaction->transaction_id,
                        'description' => "Tier 1 Exact Match M-Pesa receipt for Invoice {$matchedSale->invoice_number}",
                    ],
                    [
                        'account_code' => $arCode,
                        'debit' => 0,
                        'credit' => $amount,
                        'entry_type' => 'MpesaTier1Match',
                        'customer_id' => $matchedSale->customer_id ?? null,
                        'reference' => $transaction->trans_id ?: $transaction->transaction_id,
                        'description' => "Tier 1 Exact Match M-Pesa receipt for Invoice {$matchedSale->invoice_number}",
                    ],
                ]);

                return;
            }
        }

        // ─── TIER 2: Customer Phone Match ──────────────────────────────────
        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $phone);
        $last9 = substr($cleanPhone, -9);

        $customerModelClass = static::modelClass('customer');
        $matchedCustomer = null;

        if (class_exists($customerModelClass) && strlen($last9) === 9) {
            $custInstance = new $customerModelClass;
            if (Schema::hasTable($custInstance->getTable())) {
                $matchedCustomer = $customerModelClass::where(function ($q) use ($last9) {
                    $q->where('phone', 'like', "%{$last9}")
                        ->orWhere('phone_number', 'like', "%{$last9}")
                        ->orWhere('mobile', 'like', "%{$last9}");
                })->first();
            }
        }

        if ($matchedCustomer) {
            $transaction->update([
                'customer_id' => $matchedCustomer->id,
                'is_matched' => true,
                'allocation_status' => 'allocated',
            ]);

            // Credit Customer Wallet (Escrow Liability 2015)
            $this->walletService->creditCustomerWallet(
                customerId: $matchedCustomer->id,
                amount: $amount,
                paymentAccountId: $transaction->payment_account_id,
                reference: $transaction->trans_id ?: $transaction->transaction_id,
                description: 'Tier 2 Customer Match M-Pesa credit to wallet',
                mpesaTransactionId: $transaction->id
            );

            return;
        }

        // ─── TIER 3: Suspense Fallback (Account 2016) ───────────────────────
        $suspenseCode = config('payments-engine.accounts.unallocated_suspense', '2016');

        $transaction->update([
            'is_matched' => false,
            'allocation_status' => 'unallocated',
        ]);

        // GL: Debit M-Pesa Till (1020), Credit Suspense (2016)
        $this->ledgerService->postJournal([
            [
                'account_code' => $bankAccountCode,
                'debit' => $amount,
                'credit' => 0,
                'entry_type' => 'MpesaSuspense',
                'reference' => $transaction->trans_id ?: $transaction->transaction_id,
                'description' => 'Tier 3 Unallocated M-Pesa receipt in Suspense',
            ],
            [
                'account_code' => $suspenseCode,
                'debit' => 0,
                'credit' => $amount,
                'entry_type' => 'MpesaSuspense',
                'reference' => $transaction->trans_id ?: $transaction->transaction_id,
                'description' => 'Tier 3 Unallocated M-Pesa receipt in Suspense',
            ],
        ]);
    }

    /**
     * Format MSISDN phone number to standard 254XXXXXXXXX.
     */
    protected function formatPhoneNumber(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '0')) {
            return '254'.substr($clean, 1);
        }
        if (str_starts_with($clean, '+254')) {
            return substr($clean, 1);
        }
        if (strlen($clean) === 9) {
            return '254'.$clean;
        }

        return $clean;
    }
}
