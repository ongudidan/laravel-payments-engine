<?php

namespace Ongudidan\PaymentsEngine\Services;

use Illuminate\Support\Facades\DB;
use Ongudidan\PaymentsEngine\Models\MpesaAutoSweepConfig;
use Ongudidan\PaymentsEngine\Models\MpesaAutoSweepLog;
use Ongudidan\PaymentsEngine\Models\MpesaAutoSweepRun;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;

class MpesaAutoSweepService
{
    public function __construct(
        protected PaymentAccountTransferService $transferService
    ) {}

    /**
     * Get or create auto-sweep configuration for a payment account.
     */
    public function getOrCreateConfig(PaymentAccount $account): MpesaAutoSweepConfig
    {
        return MpesaAutoSweepConfig::firstOrCreate(
            ['payment_account_id' => $account->id],
            [
                'destination_bank_account_id' => $account->linked_bank_account_id,
                'is_enabled' => false,
                'min_sweep_amount' => (float) config('payments-engine.mpesa.auto_sweep_threshold', 50000),
            ]
        );
    }

    /**
     * Execute auto sweep for all eligible payment accounts.
     */
    public function executeSweeps(): array
    {
        $configs = MpesaAutoSweepConfig::where('is_enabled', true)->get();
        $results = [];

        foreach ($configs as $config) {
            $results[$config->payment_account_id] = $this->sweepAccount($config);
        }

        return $results;
    }

    /**
     * Execute sweep for a single account config.
     */
    public function sweepAccount(MpesaAutoSweepConfig $config): array
    {
        $account = $config->paymentAccount;
        $destination = $config->destinationBankAccount;

        if (! $account || ! $destination) {
            return ['status' => 'skipped', 'reason' => 'Missing source or destination account'];
        }

        $balance = (float) $account->balance;
        $threshold = (float) $config->min_sweep_amount;

        if ($balance < $threshold) {
            return [
                'status' => 'skipped',
                'balance' => $balance,
                'threshold' => $threshold,
                'reason' => 'Balance below sweep threshold',
            ];
        }

        return DB::transaction(function () use ($config, $account, $destination, $balance) {
            $sweepAmount = $balance;

            $run = MpesaAutoSweepRun::create([
                'payment_account_id' => $account->id,
                'auto_sweep_config_id' => $config->id,
                'amount' => $sweepAmount,
                'status' => 'pending',
            ]);

            try {
                $transfer = $this->transferService->initiateTransfer([
                    'from_account_id' => $account->id,
                    'to_account_id' => $destination->id,
                    'amount' => $sweepAmount,
                    'charges' => 0.00,
                    'reference' => 'SWP-'.date('YmdHis').'-'.$account->id,
                    'notes' => "Automated sweep from Till #{$account->name} to Bank #{$destination->name}",
                ]);

                $run->update([
                    'status' => 'completed',
                    'transfer_id' => $transfer->id,
                    'message' => 'Auto-sweep transfer executed successfully',
                ]);

                $config->update([
                    'last_sweep_at' => now(),
                    'last_status' => 'success',
                    'last_error' => null,
                ]);

                MpesaAutoSweepLog::create([
                    'payment_account_id' => $account->id,
                    'auto_sweep_run_id' => $run->id,
                    'status' => 'success',
                    'message' => "Successfully swept {$sweepAmount} to {$destination->name}",
                ]);

                return ['status' => 'success', 'amount' => $sweepAmount, 'transfer_id' => $transfer->id];
            } catch (\Throwable $e) {
                $run->update([
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ]);

                $config->update([
                    'last_status' => 'failed',
                    'last_error' => $e->getMessage(),
                ]);

                MpesaAutoSweepLog::create([
                    'payment_account_id' => $account->id,
                    'auto_sweep_run_id' => $run->id,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ]);

                return ['status' => 'failed', 'error' => $e->getMessage()];
            }
        });
    }
}
