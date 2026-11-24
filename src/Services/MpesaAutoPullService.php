<?php

namespace Ongudidan\PaymentsEngine\Services;

use Ongudidan\PaymentsEngine\Models\MpesaAutoPullConfig;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;

class MpesaAutoPullService
{
    public function __construct(
        protected MpesaPullTransactionService $pullService
    ) {}

    /**
     * Get or create default auto-pull configuration for a PaymentAccount.
     */
    public function getOrCreateConfig(PaymentAccount $paymentAccount): MpesaAutoPullConfig
    {
        return MpesaAutoPullConfig::firstOrCreate(
            ['payment_account_id' => $paymentAccount->id],
            [
                'is_enabled' => false,
                'interval_minutes' => 30,
                'auto_record' => true,
                'discrepancy_only' => true,
                'lookback_hours' => 2,
                'last_status' => 'idle',
                'last_message' => 'Configured. Awaiting first scheduled run.',
            ]
        );
    }

    /**
     * Update auto-pull configuration.
     */
    public function saveConfig(PaymentAccount $paymentAccount, array $data): MpesaAutoPullConfig
    {
        $config = $this->getOrCreateConfig($paymentAccount);
        $config->update($data);

        return $config->fresh();
    }
}
