<?php

namespace Ongudidan\PaymentsEngine\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ongudidan\PaymentsEngine\Models\MpesaConfig;
use Ongudidan\PaymentsEngine\Services\MpesaConfigService;

class MpesaConfigController extends Controller
{
    public function __construct(protected MpesaConfigService $configService)
    {
    }

    public function index(): JsonResponse
    {
        $configs = MpesaConfig::with(['paymentAccount'])->latest()->get();

        return response()->json([
            'success' => true,
            'configs' => $configs,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_account_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'shortcode' => 'required|string|max:20',
            'type' => 'nullable|string|in:paybill,till',
            'consumer_key' => 'required|string',
            'consumer_secret' => 'required|string',
            'passkey' => 'nullable|string',
            'initiator_name' => 'nullable|string',
            'initiator_password' => 'nullable|string',
            'environment' => 'required|in:sandbox,live',
            'c2b_secret_token' => 'nullable|string',
        ]);

        $config = MpesaConfig::create($data);

        return response()->json([
            'success' => true,
            'message' => 'M-Pesa configuration created successfully.',
            'config' => $config,
        ]);
    }

    public function update(Request $request, MpesaConfig $config): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'shortcode' => 'sometimes|required|string|max:20',
            'type' => 'nullable|string|in:paybill,till',
            'consumer_key' => 'nullable|string',
            'consumer_secret' => 'nullable|string',
            'passkey' => 'nullable|string',
            'initiator_name' => 'nullable|string',
            'initiator_password' => 'nullable|string',
            'environment' => 'sometimes|required|in:sandbox,live',
        ]);

        // Don't overwrite encrypted credentials if left blank
        foreach (['consumer_key', 'consumer_secret', 'passkey', 'initiator_password'] as $field) {
            if (empty($data[$field])) {
                unset($data[$field]);
            }
        }

        $config->update($data);

        return response()->json([
            'success' => true,
            'message' => 'M-Pesa configuration updated successfully.',
            'config' => $config->fresh(),
        ]);
    }

    public function destroy(MpesaConfig $config): JsonResponse
    {
        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'M-Pesa configuration deleted successfully.',
        ]);
    }

    public function toggleActive(MpesaConfig $config): JsonResponse
    {
        $config->update(['is_active' => !$config->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $config->is_active,
            'message' => $config->is_active ? 'Configuration activated.' : 'Configuration deactivated.',
        ]);
    }

    public function registerUrls(MpesaConfig $config): JsonResponse
    {
        $result = $this->configService->registerC2bUrls($config);

        return response()->json($result);
    }
}
