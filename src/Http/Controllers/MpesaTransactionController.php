<?php

namespace Ongudidan\PaymentsEngine\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ongudidan\PaymentsEngine\Models\MpesaTransaction;
use Ongudidan\PaymentsEngine\Services\MpesaTransactionService;

class MpesaTransactionController extends Controller
{
    public function __construct(
        protected MpesaTransactionService $mpesaService
    ) {}

    /**
     * Initiate an M-Pesa STK Push.
     */
    public function stkPush(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'payment_account_id' => 'nullable|integer',
            'shortcode' => 'nullable|string',
            'reference' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:100',
            'customer_id' => 'nullable|integer',
            'sale_id' => 'nullable|integer',
        ]);

        try {
            $result = $this->mpesaService->initiateStkPush($validated);

            return response()->json([
                'success' => true,
                'message' => 'STK Push initiated successfully.',
                'data' => $result,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Query status of an STK Push transaction.
     */
    public function queryStatus(Request $request): JsonResponse
    {
        $request->validate([
            'checkout_request_id' => 'required_without:transaction_id|string',
            'transaction_id' => 'required_without:checkout_request_id|integer',
        ]);

        $query = MpesaTransaction::query();
        if ($request->filled('checkout_request_id')) {
            $query->where('checkout_request_id', $request->checkout_request_id);
        } else {
            $query->where('id', $request->transaction_id);
        }

        $transaction = $query->first();

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $transaction->status,
            'is_matched' => $transaction->is_matched,
            'receipt' => $transaction->trans_id ?? $transaction->transaction_id,
            'amount' => $transaction->amount,
            'transaction' => $transaction,
        ]);
    }
}
