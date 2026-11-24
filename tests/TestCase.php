<?php

namespace Ongudidan\PaymentsEngine\Tests;

use Ongudidan\PaymentsEngine\Database\Seeders\CashDenominationsSeeder;
use Ongudidan\PaymentsEngine\Database\Seeders\ChartOfAccountsSeeder;
use Ongudidan\PaymentsEngine\PaymentsEngineServiceProvider;

if (class_exists(\Orchestra\Testbench\TestCase::class)) {
    abstract class BaseTestParent extends \Orchestra\Testbench\TestCase {}
} elseif (class_exists(\Tests\TestCase::class)) {
    abstract class BaseTestParent extends \Tests\TestCase {}
} else {
    abstract class BaseTestParent extends \PHPUnit\Framework\TestCase {}
}

abstract class TestCase extends BaseTestParent
{
    protected function setUp(): void
    {
        parent::setUp();

        if (method_exists($this, 'withoutExceptionHandling')) {
            $this->withoutExceptionHandling();
        }

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            PaymentsEngineServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('payments-engine.table_prefix', 'pe_');
    }

    protected function setUpDatabase(): void
    {
        $migrations = [
            __DIR__.'/../database/migrations/2026_01_01_000001_create_payments_engine_accounts_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000002_create_payments_engine_denominations_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000003_create_payments_engine_transactions_and_transfers_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000004_create_payments_engine_wallets_and_ledgers_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000005_create_payments_engine_mpesa_tables.php',
            __DIR__.'/../database/migrations/2026_01_01_000006_create_payments_engine_journal_entries_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000007_create_payments_engine_cheque_clearances_table.php',
            __DIR__.'/../database/migrations/2026_01_01_000008_create_payments_engine_cash_drawer_sessions_table.php',
        ];

        foreach ($migrations as $migrationFile) {
            if (file_exists($migrationFile)) {
                $migration = require $migrationFile;
                $migration->up();
            }
        }

        (new ChartOfAccountsSeeder)->run();
        (new CashDenominationsSeeder)->run();
    }
}
