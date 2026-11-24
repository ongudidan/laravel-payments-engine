# Laravel Payments Engine (`ongudidan/laravel-payments-engine`)

Unified enterprise payment accounts, physical cash vault denominations, customer/supplier wallets, M-Pesa STK/C2B automation, double-entry general ledger, and financial reporting engine for Laravel.

## Features

- **Payment Accounts & Methods**: Bank accounts, physical cash drawers/vaults, M-Pesa Tills and Paybills, and wallet channels with customizable routing.
- **Physical Vault Denominations**: Real-time denomination count tracking (1000, 500, 200, 100, 50, 40, 20, 10, 5, 1) and automated reconciliation (`healDenominationBalance()`).
- **Customer & Supplier Wallets**: Multi-entity escrow liabilities (`2015`) and advance prepayments (`1050`) with atomic transaction guarantees.
- **Inter-Account Transfers**: Maker-Checker authorization workflows, transfer charge deduction (`6000`), and idempotency duplicate protection (`healDuplicateFlows()`).
- **M-Pesa Automation Engine**: Daraja STK Push, C2B validation/confirmation, 3-tier matching algorithm (Exact Invoice -> Customer Phone -> Suspense `2016`), 1-click settlement, automated sweeps, and pull transactions.
- **Strict Double-Entry General Ledger**: Mathematical equality guarantee (`debits === credits`), immutable audit records, and running balance calculation.
- **Financial & Payment Reports**: Anomaly reconciliation, sell/purchase payment breakdowns, physical audit statements, and unallocated suspense reports.
- **Frontend Components**: Vue 3 / Inertia (`shadcn-vue` compatible) and Blade components.

## Installation

Add the repository or require via Composer:

```bash
composer require ongudidan/laravel-payments-engine
```

Publish configuration, migrations, seeders, and assets:

```bash
php artisan vendor:publish --tag=payments-engine-config
php artisan vendor:publish --tag=payments-engine-migrations
php artisan vendor:publish --tag=payments-engine-seeders
php artisan vendor:publish --tag=payments-engine-vue
php artisan vendor:publish --tag=payments-engine-views
```

Run migrations:

```bash
php artisan migrate
```

Seed default Chart of Accounts and Denominations:

```bash
php artisan db:seed --class=Ongudidan\\PaymentsEngine\\Database\\Seeders\\ChartOfAccountsSeeder
php artisan db:seed --class=Ongudidan\\PaymentsEngine\\Database\\Seeders\\CashDenominationsSeeder
```

## License

MIT License.
