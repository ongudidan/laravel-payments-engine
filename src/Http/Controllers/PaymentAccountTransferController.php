<?php

namespace Ongudidan\PaymentsEngine\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ongudidan\PaymentsEngine\Models\PaymentAccountTransfer;
use Ongudidan\PaymentsEngine\Services\PaymentAccountTransferService;

class PaymentAccountTransferController extends Controller
{
    public function __construct(protected PaymentAccountTransferService $transferService) {}

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_account_id' => 'required|integer',
            'to_account_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'charges' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $transfer = $this->transferService->initiateTransfer($data);

        return response()->json([
            'success' => true,
            'message' => 'Transfer initiated successfully',
            'transfer' => $transfer,
        ]);
    }

    public function approve(PaymentAccountTransfer $transfer): JsonResponse
    {
        $approved = $this->transferService->approveTransfer($transfer);

        return response()->json([
            'success' => true,
            'message' => 'Transfer approved successfully',
            'transfer' => $approved,
        ]);
    }

    public function reject(Request $request, PaymentAccountTransfer $transfer): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $rejected = $this->transferService->rejectTransfer($transfer, $request->input('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Transfer rejected successfully',
            'transfer' => $rejected,
        ]);
    }
}
