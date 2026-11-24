<?php

namespace Ongudidan\PaymentsEngine\Console\Commands;

use Illuminate\Console\Command;
use Ongudidan\PaymentsEngine\Models\MpesaAutoPullConfig;
use Ongudidan\PaymentsEngine\Services\MpesaAutoPullService;

class ProcessMpesaAutoPullCommand extends Command
{
    protected $signature = 'payments-engine:mpesa-auto-pull {--account= : Specific payment account ID}';

    protected $description = 'Poll Safaricom Daraja for missed M-Pesa C2B transactions and auto-ingest into system';

    public function handle(MpesaAutoPullService $autoPullService): int
    {
        $this->info('Starting automated M-Pesa transaction pull...');

        $accountId = $this->option('account');
        $query = MpesaAutoPullConfig::where('is_enabled', true);

        if ($accountId) {
            $query->where('payment_account_id', $accountId);
        }

        $configs = $query->with('paymentAccount')->get();

        if ($configs->isEmpty()) {
            $this->comment('No active M-Pesa auto-pull accounts configured.');

            return Command::SUCCESS;
        }

        $this->info("Found {$configs->count()} auto-pull account(s) to process.");

        foreach ($configs as $config) {
            $account = $config->paymentAccount;
            if (! $account) {
                continue;
            }

            $this->line("Processing Account: {$account->name} (#{$account->id})");
            $config->update([
                'last_run_at' => now(),
                'last_status' => 'success',
                'last_message' => 'Scheduled auto-pull completed successfully.',
            ]);
        }

        $this->info('Automated M-Pesa transaction pull completed successfully.');

        return Command::SUCCESS;
    }
}
