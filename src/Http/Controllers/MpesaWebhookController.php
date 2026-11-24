<?php

namespace Ongudidan\PaymentsEngine\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ongudidan\PaymentsEngine\Services\MpesaTransactionService;

class MpesaWebhookController extends Controller
{
    public function __construct(protected MpesaTransactionService $mpesaService) {}

    /**
     * M-Pesa C2B Validation Webhook.
     */
    public function validation(Request $request): JsonResponse
    {
        return $this->mpesaService->handleC2bValidation($request);
    }

    /**
     * M-Pesa C2B Confirmation Webhook.
     */
    public function confirmation(Request $request): JsonResponse
    {
        $payload = $request->all();
        $this->mpesaService->handleC2bConfirmation($payload);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Confirmation accepted successfully',
        ]);
    }

    /**
     * M-Pesa STK Push Callback Webhook.
     */
    public function stkCallback(Request $request): JsonResponse
    {
        $payload = $request->all();
        $this->mpesaService->handleStkCallback($payload);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'STK Callback processed successfully',
        ]);
    }
}
