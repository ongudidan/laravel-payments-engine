<?php

namespace Ongudidan\PaymentsEngine\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ongudidan\PaymentsEngine\Models\MpesaConfig;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;

class MpesaConfigService
{
    /**
     * Resolve the active MpesaConfig for a given PaymentAccount or shortcode.
     */
    public function resolveConfig(?int $paymentAccountId = null, ?string $shortcode = null): ?MpesaConfig
    {
        if ($paymentAccountId) {
            $config = MpesaConfig::where('payment_account_id', $paymentAccountId)->where('is_active', true)->first();
            if ($config) {
                return $config;
            }
        }

        if ($shortcode) {
            $config = MpesaConfig::where('shortcode', $shortcode)->where('is_active', true)->first();
            if ($config) {
                return $config;
            }
        }

        // Fallback to first active configuration in DB
        return MpesaConfig::where('is_active', true)->first();
    }

    /**
     * Generate Daraja OAuth token for the given config.
     */
    public function generateOAuthToken(MpesaConfig $config): string
    {
        $baseUrl = ($config->environment === 'live')
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        $url = "{$baseUrl}/oauth/v1/generate?grant_type=client_credentials";

        $response = Http::withBasicAuth($config->consumer_key, $config->consumer_secret)
            ->timeout(20)
            ->get($url);

        if (!$response->successful()) {
            throw new Exception("Daraja OAuth authentication failed: " . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Register C2B URLs with Safaricom Daraja for the specified MpesaConfig.
     */
    public function registerC2bUrls(MpesaConfig $config, ?string $callbackBaseUrl = null): array
    {
        $token = $this->generateOAuthToken($config);

        $baseUrl = ($config->environment === 'live')
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        $base = rtrim($callbackBaseUrl ?: config('payments-engine.mpesa.callback_url_base', config('app.url')), '/');
        $secretToken = $config->c2b_secret_token ?: config('payments-engine.mpesa.c2b_secret_token', 'default_secret');

        $payload = [
            'ShortCode' => $config->shortcode,
            'ResponseType' => 'Completed',
            'ConfirmationURL' => "{$base}/api/v1/payments-engine/mpesa/confirmation?token={$secretToken}",
            'ValidationURL' => "{$base}/api/v1/payments-engine/mpesa/validation?token={$secretToken}",
        ];

        $response = Http::withToken($token)
            ->timeout(25)
            ->post("{$baseUrl}/mpesa/c2b/v1/registerurl", $payload);

        $resData = $response->json() ?? [];

        if ($response->successful() && (($resData['ResponseCode'] ?? '') == '0')) {
            $config->update([
                'urls_registered_at' => now(),
                'last_registration_response' => $resData,
            ]);

            return [
                'success' => true,
                'message' => 'C2B URLs successfully registered with Safaricom.',
                'response' => $resData,
            ];
        }

        $config->update(['last_registration_response' => $resData]);

        return [
            'success' => false,
            'message' => $resData['errorMessage'] ?? ($resData['ResponseDescription'] ?? 'C2B URL registration failed.'),
            'response' => $resData,
        ];
    }
}
