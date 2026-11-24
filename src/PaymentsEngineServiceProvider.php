<?php

namespace Ongudidan\PaymentsEngine;

use Illuminate\Support\ServiceProvider;
use Ongudidan\PaymentsEngine\Console\Commands\ProcessMpesaAutoPullCommand;
use Ongudidan\PaymentsEngine\Console\Commands\ProcessMpesaAutoSweepsCommand;
use Ongudidan\PaymentsEngine\Services\AccountingService;
use Ongudidan\PaymentsEngine\Services\CashDrawerService;
use Ongudidan\PaymentsEngine\Services\ChequeClearanceService;
use Ongudidan\PaymentsEngine\Services\CustomerWalletLedgerService;
use Ongudidan\PaymentsEngine\Services\LedgerService;
use Ongudidan\PaymentsEngine\Services\MpesaAutoPullService;
use Ongudidan\PaymentsEngine\Services\MpesaAutoSweepService;
use Ongudidan\PaymentsEngine\Services\MpesaConfigService;
use Ongudidan\PaymentsEngine\Services\MpesaPullTransactionService;
use Ongudidan\PaymentsEngine\Services\MpesaSettlementService;
use Ongudidan\PaymentsEngine\Services\MpesaTransactionService;
use Ongudidan\PaymentsEngine\Services\PaymentAccountService;
use Ongudidan\PaymentsEngine\Services\PaymentAccountTransferService;
use Ongudidan\PaymentsEngine\Services\PaymentAnomalyReconciliationService;
use Ongudidan\PaymentsEngine\Services\Reports\BalanceSheetReportService;
use Ongudidan\PaymentsEngine\Services\Reports\GeneralLedgerReportService;
use Ongudidan\PaymentsEngine\Services\Reports\MpesaStatementReconciliationService;
use Ongudidan\PaymentsEngine\Services\Reports\ProfitLossReportService;
use Ongudidan\PaymentsEngine\Services\Reports\PurchasePaymentReportService;
use Ongudidan\PaymentsEngine\Services\Reports\SellPaymentReportService;
use Ongudidan\PaymentsEngine\Services\Reports\TrialBalanceReportService;
use Ongudidan\PaymentsEngine\Services\Reports\UnallocatedSuspenseReportService;
use Ongudidan\PaymentsEngine\Services\Reports\VaultAuditReportService;
use Ongudidan\PaymentsEngine\Services\WalletService;

class PaymentsEngineServiceProvider extends ServiceProvider
{
    /**
     * Register services and bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/payments-engine.php',
            'payments-engine'
        );

        $this->app->singleton(LedgerService::class, fn () => new LedgerService);
        $this->app->singleton(AccountingService::class, fn () => new AccountingService);

        $this->app->singleton(PaymentAccountService::class, function ($app) {
            return new PaymentAccountService($app->make(LedgerService::class));
        });

        $this->app->singleton(WalletService::class, function ($app) {
            return new WalletService($app->make(LedgerService::class));
        });

        $this->app->singleton(CustomerWalletLedgerService::class, fn () => new CustomerWalletLedgerService);

        $this->app->singleton(PaymentAccountTransferService::class, function ($app) {
            return new PaymentAccountTransferService($app->make(LedgerService::class));
        });

        $this->app->singleton(ChequeClearanceService::class, function ($app) {
            return new ChequeClearanceService($app->make(LedgerService::class));
        });

        $this->app->singleton(CashDrawerService::class, function ($app) {
            return new CashDrawerService($app->make(PaymentAccountTransferService::class));
        });

        $this->app->singleton(MpesaConfigService::class, fn () => new MpesaConfigService());

        $this->app->singleton(MpesaTransactionService::class, function ($app) {
            return new MpesaTransactionService(
                $app->make(LedgerService::class),
                $app->make(WalletService::class),
                $app->make(MpesaConfigService::class)
            );
        });

        $this->app->singleton(MpesaSettlementService::class, function ($app) {
            return new MpesaSettlementService(
                $app->make(LedgerService::class),
                $app->make(WalletService::class)
            );
        });

        $this->app->singleton(MpesaAutoSweepService::class, function ($app) {
            return new MpesaAutoSweepService($app->make(PaymentAccountTransferService::class));
        });

        $this->app->singleton(MpesaPullTransactionService::class, function ($app) {
            return new MpesaPullTransactionService(
                $app->make(MpesaTransactionService::class),
                $app->make(MpesaSettlementService::class)
            );
        });

        $this->app->singleton(MpesaAutoPullService::class, function ($app) {
            return new MpesaAutoPullService($app->make(MpesaPullTransactionService::class));
        });

        $this->app->singleton(PaymentAnomalyReconciliationService::class, fn () => new PaymentAnomalyReconciliationService);

        // Report Services
        $this->app->singleton(ProfitLossReportService::class, fn () => new ProfitLossReportService);
        $this->app->singleton(PurchasePaymentReportService::class, fn () => new PurchasePaymentReportService);
        $this->app->singleton(SellPaymentReportService::class, fn () => new SellPaymentReportService);
        $this->app->singleton(VaultAuditReportService::class, function ($app) {
            return new VaultAuditReportService($app->make(PaymentAccountService::class));
        });
        $this->app->singleton(UnallocatedSuspenseReportService::class, fn () => new UnallocatedSuspenseReportService);
        $this->app->singleton(TrialBalanceReportService::class, fn () => new TrialBalanceReportService);
        $this->app->singleton(BalanceSheetReportService::class, fn () => new BalanceSheetReportService);
        $this->app->singleton(GeneralLedgerReportService::class, fn () => new GeneralLedgerReportService);
        $this->app->singleton(MpesaStatementReconciliationService::class, fn () => new MpesaStatementReconciliationService);

        // Aliases
        $this->app->alias(LedgerService::class, 'payments-engine.ledger');
        $this->app->alias(AccountingService::class, 'payments-engine.accounting');
        $this->app->alias(PaymentAccountService::class, 'payments-engine.accounts');
        $this->app->alias(WalletService::class, 'payments-engine.wallet');
        $this->app->alias(MpesaTransactionService::class, 'payments-engine.mpesa');
        $this->app->alias(ChequeClearanceService::class, 'payments-engine.cheques');
        $this->app->alias(CashDrawerService::class, 'payments-engine.drawers');
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load API routes
        if (file_exists(__DIR__.'/../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        // Load Views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'payments-engine');

        // Publish resources if console
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessMpesaAutoPullCommand::class,
                ProcessMpesaAutoSweepsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/payments-engine.php' => config_path('payments-engine.php'),
            ], 'payments-engine-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'payments-engine-migrations');

            $this->publishes([
                __DIR__.'/../database/seeders' => database_path('seeders'),
            ], 'payments-engine-seeders');

            $this->publishes([
                __DIR__.'/../resources/js/Components' => resource_path('js/components/payments-engine'),
                __DIR__.'/../resources/js/Pages' => resource_path('js/pages/payments-engine'),
            ], 'payments-engine-vue');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/payments-engine'),
            ], 'payments-engine-views');
        }
    }
}
