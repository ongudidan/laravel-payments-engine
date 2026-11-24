# Laravel Payments Engine (`ongudidan/laravel-payments-engine`)

[![Latest Stable Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/ongudidan/laravel-payments-engine)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-%5E8.2%7C%5E8.3%7C%5E8.4-777bb4.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-%5E11.0%7C%5E12.0-ff2d20.svg)](https://laravel.com)

A battle-tested, enterprise-grade financial core for modern Laravel applications. It provides full double-entry general ledger accounting, multi-shortcode M-Pesa automation (STK push, C2B webhooks, 3-tier reconciliation matching, auto-sweeps, and statement reconciliation), customer & supplier wallets (escrow liabilities and prepayments), physical cash vault denomination inventory with automated balance healing, maker-checker account transfers, cashier drawer sessions, and cheque lifecycle management.

---

## Table of Contents
- [1. Domain Architecture](#1-domain-architecture)
  - [General Ledger & Chart of Accounts](#general-ledger--chart-of-accounts)
  - [Payment Accounts & Methods](#payment-accounts--methods)
  - [Cash Vaults & Physical Denominations](#cash-vaults--physical-denominations)
  - [Customer & Supplier Wallets](#customer--supplier-wallets)
  - [Inter-Account Transfers & Maker-Checker](#inter-account-transfers--maker-checker)
  - [M-Pesa Automation Engine](#m-pesa-automation-engine)
  - [Cheque Clearance Lifecycle](#cheque-clearance-lifecycle)
  - [Cash Drawer & Float Sessions](#cash-drawer--float-sessions)
- [2. Installation & Quickstart](#2-installation--quickstart)
- [3. Configuration (`config/payments-engine.php`)](#3-configuration-configpayments-enginephp)
- [4. Developer Usage & Code Examples](#4-developer-usage--code-examples)
  - [Double-Entry Journal Entry Posting](#double-entry-journal-entry-posting)
  - [STK Push & Status Polling](#stk-push--status-polling)
  - [Customer & Supplier Wallets](#customer--supplier-wallets-1)
  - [Vault Denomination Reconciliation](#vault-denomination-reconciliation)
  - [Maker-Checker Inter-Account Transfers](#maker-checker-inter-account-transfers)
  - [Financial & Audit Reports](#financial--audit-reports)
- [5. Frontend UI (Vue 3 / Inertia & Blade)](#5-frontend-ui-vue-3--inertia--blade)
- [6. Schedulers & Background Workers](#6-schedulers--background-workers)
- [7. AI Agent Integration Playbook](#7-ai-agent-integration-playbook)
- [8. Copy-Paste Master Prompt for AI Agents](#8-copy-paste-master-prompt-for-ai-agents)

---

## 1. Domain Architecture

```
                       ┌──────────────────────────────────────────────┐
                       │               Payments Engine                │
                       └──────────────────────┬───────────────────────┘
                                              │
    ┌──────────────────┬──────────────────────┼──────────────────────┬──────────────────┐
    ▼                  ▼                      ▼                      ▼                  ▼
┌──────────────┐ ┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│General Ledger│ │Payment Accts │     │M-Pesa Daraja │     │Multi-Entity  │     │Cash Vault &  │
│  (GAAP COA)  │ │& Tender Meth.│     │STK / C2B /3T │     │   Wallets    │     │Denominations │
└──────────────┘ └──────────────┘     └──────────────┘     └──────────────┘     └──────────────┘
    │                  │                      │                      │                  │
    ├─ Journal Entries ├─ Cash, Bank, Till    ├─ STK Push            ├─ Cust Escrow2015 ├─ 1000..1 Notes
    ├─ Multi-line GL   ├─ Channel Routing     ├─ C2B Confirmation    ├─ Supp Advance1050├─ Auto-Healing
    └─ Trial Balance   └─ Maker-Checker Trans └─ Suspense Fallback2016 └─ Offsets & Stmts └─ Opening Counts
```

### General Ledger & Chart of Accounts
- Strict double-entry invariants: every financial transaction must mathematically balance (`SUM(debits) === SUM(credits)`). Unbalanced entries throw `UnbalancedJournalException`.
- Seeded standard GAAP accounts:
  - `1001` Cash on Hand (Asset)
  - `1002` Bank Accounts (Asset)
  - `1010` M-Pesa Till / Paybill (Asset)
  - `1020` Cash Vault (Asset)
  - `1050` Supplier Advances / Prepayments (Asset)
  - `1200` Accounts Receivable (Asset)
  - `2000` Accounts Payable (Liability)
  - `2015` Customer Advance Deposits / Wallet Escrow (Liability)
  - `2016` Unallocated Receipts Suspense (Liability)
  - `4000` Operating Revenue (Income)
  - `6000` Bank & Transfer Charges (Expense)

### Payment Accounts & Methods
- Distinguishes **Payment Accounts** (actual balance-holding containers such as bank accounts, cash vaults, cash drawers, and M-Pesa shortcodes) from **Payment Methods** (customer-facing tender channels such as Cash, M-Pesa, Cheque, Bank Transfer, Card).
- Multi-channel routing via `PaymentMethodMapping`.

### Cash Vaults & Physical Denominations
- Real-time inventory count of physical notes and coins (1000, 500, 200, 100, 50, 40, 20, 10, 5, 1).
- Automated healing: `PaymentAccountService::healDenominationBalance(PaymentAccount $account)` calculates the total physical note value (`SUM(denomination * quantity)`), compares it against the ledger account balance, and flags/records overages or shortages.

### Customer & Supplier Wallets
- **Customer Wallet (Liability `2015` - Customer Advance Deposits)**:
  - Top-ups & change: `Debit Cash/Bank`, `Credit Liability 2015`.
  - Paying an invoice with wallet: `Debit Liability 2015`, `Credit Revenue/AR 1200`.
- **Supplier Wallet (Asset `1050` - Supplier Advances / Prepayments)**:
  - Advancing money to a vendor: `Debit Supplier Advances 1050`, `Credit Cash/Bank`.
  - Offsetting an incoming supplier bill: `Debit Accounts Payable 2000`, `Credit Supplier Advances 1050`.

### Inter-Account Transfers & Maker-Checker
- Complete workflow for moving funds between Cash Drawers, Vaults, Bank Accounts, and Tills.
- Maker-Checker approval: transfers exceeding threshold require approval before ledger balances move.
- Automated allocation of transfer fees (e.g. M-Pesa B2B / Bank charges) directly debited to Expense `6000`.
- Idempotency healing: `healDuplicateFlows()` prevents duplicate ledger postings from network retry bursts.

### M-Pesa Automation Engine
- **Multi-Config Architecture**: Store, encrypt, and manage multiple Paybills and Tills (per company, branch, or outlet) in database table `pe_mpesa_configs` with at-rest encryption (`'encrypted'` Eloquent casts).
- **STK Push**: Initiates Daraja STK Push requests with dynamic token generation, password hashing, and instant transaction creation.
- **C2B Webhooks**: Full validation and confirmation webhooks with HMAC security tokens.
- **3-Tier Matching Algorithm**:
  1. *Tier 1 (Exact Match)*: Matches `bill_ref_number` to an outstanding Invoice, Order, or Account number.
  2. *Tier 2 (Customer Match)*: Matches `msisdn` to customer phone, crediting their Customer Wallet (`2015`).
  3. *Tier 3 (Suspense Fallback)*: Credits **Unallocated Receipts Suspense Account (`2016`)** and marks record `unallocated` for 1-click manual settlement.
- **1-Click Safaricom URL Registration**: Daraja endpoint registration (`/mpesa/c2b/v1/registerurl`) directly via API/UI.
- **Auto-Sweeps & Auto-Pulls**: Threshold-based sweeps to bank accounts and polling for missed transactions.

### Cheque Clearance Lifecycle
- **Deferred GL Invariant**: Cheque issuance or receipt does **NOT** touch bank balances or post GL items until:
  1. A collector is assigned (`collected`).
  2. A bank transaction reference is verified and cleared (`cleared`).
- Full support for cheque cancellation and bouncing (`bounced`), generating reversal entries.

### Cash Drawer & Float Sessions
- Cashier opening float requests with manager approval limits.
- Mid-day cash drops, vault top-ups, and petty cash logs.
- End-of-day cash reconciliation: compares counted notes vs calculated POS drawer balances, logs shortages/overages, and automatically sweeps closing cash to the main vault.

---

## 2. Installation & Quickstart

### Step 1: Install Package

#### Via Composer (when published to Packagist):
```bash
composer require ongudidan/laravel-payments-engine
```

#### Via Path Repository (Local Monorepo / Workspace):
Add the repository to your host application's `composer.json`:
```json
"repositories": [
    {
        "type": "path",
        "url": "packages/ongudidan/laravel-payments-engine",
        "options": {
            "symlink": true
        }
    }
]
```
Then require it:
```bash
composer require ongudidan/laravel-payments-engine:"*@dev"
```

### Step 2: Register Service Provider
In Laravel 11/12, register the provider in `bootstrap/providers.php`:
```php
return [
    App\Providers\AppServiceProvider::class,
    Ongudidan\PaymentsEngine\PaymentsEngineServiceProvider::class,
];
```

### Step 3: Publish Package Assets
Publish configuration, migrations, seeders, and Vue components:
```bash
# Publish configuration
php artisan vendor:publish --tag=payments-engine-config

# Publish migrations (Optional: package loads them automatically)
php artisan vendor:publish --tag=payments-engine-migrations

# Publish seeders
php artisan vendor:publish --tag=payments-engine-seeders

# Publish Vue components & pages
php artisan vendor:publish --tag=payments-engine-vue

# Publish Blade views
php artisan vendor:publish --tag=payments-engine-views
```

### Step 4: Run Migrations & Seeders
```bash
php artisan migrate

# Seed Chart of Accounts & Cash Denominations
php artisan db:seed --class="Ongudidan\PaymentsEngine\Database\Seeders\ChartOfAccountsSeeder"
php artisan db:seed --class="Ongudidan\PaymentsEngine\Database\Seeders\CashDenominationsSeeder"
```

---

## 3. Configuration (`config/payments-engine.php`)

After publishing `config/payments-engine.php`, you can customize table prefixes, model resolutions, and default account codes:

```php
return [
    // Table prefix to isolate package tables from host tables
    'table_prefix' => env('PAYMENTS_ENGINE_TABLE_PREFIX', 'pe_'),

    // Dynamic model bindings
    'models' => [
        'user' => App\Models\User::class,
        'customer' => App\Models\Customer::class,
        'supplier' => App\Models\Supplier::class,
        'sale' => App\Models\Sale::class,
        'purchase' => App\Models\Purchase::class,
    ],

    // Default Chart of Account Codes
    'accounts' => [
        'cash' => '1001',
        'bank' => '1002',
        'till' => '1010',
        'vault' => '1020',
        'supplier_advance' => '1050',
        'accounts_receivable' => '1200',
        'accounts_payable' => '2000',
        'customer_wallet' => '2015',
        'unallocated_suspense' => '2016',
        'revenue' => '4000',
        'transfer_charges' => '6000',
    ],

    // Maker-checker limits
    'transfers' => [
        'require_approval_above' => 10000.00,
        'max_unapproved_amount' => 10000.00,
    ],

    // M-Pesa configuration
    'mpesa' => [
        'shortcode' => env('MPESA_SHORTCODE', '174379'),
        'c2b_secret_token' => env('MPESA_C2B_SECRET_TOKEN'),
        'callback_url_base' => env('MPESA_CALLBACK_URL_BASE', env('APP_URL')),
        'auto_sweep_threshold' => 50000.00,
    ],
];
```

---

## 4. Developer Usage & Code Examples

### Double-Entry Journal Entry Posting
All journal entries must have equal debits and credits:
```php
use Ongudidan\PaymentsEngine\Services\AccountingService;

$accountingService = app(AccountingService::class);

$entry = $accountingService->postJournalEntry([
    'entry_date' => now()->toDateString(),
    'reference_type' => 'sale_receipt',
    'reference_id' => 101,
    'description' => 'Customer Cash Payment for Invoice #INV-101',
    'items' => [
        [
            'account_code' => '1001', // Cash on Hand
            'debit' => 5000.00,
            'credit' => 0.00,
            'description' => 'Received cash at POS',
        ],
        [
            'account_code' => '1200', // Accounts Receivable
            'debit' => 0.00,
            'credit' => 5000.00,
            'description' => 'Settled invoice balance',
        ],
    ],
]);
```

### STK Push & Status Polling
Trigger an STK push using configured M-Pesa credentials:
```php
use Ongudidan\PaymentsEngine\Services\MpesaTransactionService;

$mpesaService = app(MpesaTransactionService::class);

$response = $mpesaService->initiateStkPush([
    'payment_account_id' => 1,          // Links to the M-Pesa Till/Paybill account
    'phone_number' => '0712345678',      // Auto-formatted to 254712345678
    'amount' => 1500.00,
    'reference' => 'INV-2026-001',
    'description' => 'Invoice Payment',
]);

// Returns:
// [
//     'success' => true,
//     'checkout_request_id' => 'ws_CO_24112026090000_123456',
//     'transaction_id' => 45,
//     'reference' => 'INV-2026-001',
//     'phone_number' => '254712345678',
//     'amount' => 1500.00
// ]
```

Or consume via the built-in HTTP API endpoint:
```bash
curl -X POST http://localhost:8000/api/v1/payments-engine/mpesa/stk-push \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "254712345678",
    "amount": 1500,
    "payment_account_id": 1,
    "reference": "INV-2026-001"
  }'
```

Query status:
```bash
curl "http://localhost:8000/api/v1/payments-engine/mpesa/stk-query?checkout_request_id=ws_CO_24112026090000_123456"
```

### Customer & Supplier Wallets
```php
use Ongudidan\PaymentsEngine\Services\WalletService;

$walletService = app(WalletService::class);

// 1. Customer deposits change to wallet
$walletTx = $walletService->creditCustomerWallet(
    customerId: 42,
    amount: 500.00,
    paymentAccountId: 1, // Cash drawer account
    notes: 'POS Change deposit'
);

// 2. Customer pays for sale invoice with wallet
$debitTx = $walletService->debitCustomerWallet(
    customerId: 42,
    amount: 300.00,
    referenceType: 'sale',
    referenceId: 99,
    notes: 'Partial payment for Invoice #99'
);

// 3. Supplier prepayment advance
$suppTx = $walletService->advanceSupplier(
    supplierId: 10,
    amount: 25000.00,
    paymentAccountId: 2, // Bank Account
    notes: 'Advance for raw materials purchase order'
);
```

### Vault Denomination Reconciliation
Verify physical cash notes against the database ledger and heal anomalies:
```php
use Ongudidan\PaymentsEngine\Services\PaymentAccountService;
use Ongudidan\PaymentsEngine\Models\PaymentAccount;

$account = PaymentAccount::find(1); // Main Vault Account

// Recalculates note total and checks against ledger balance
$result = app(PaymentAccountService::class)->healDenominationBalance($account);

// $result:
// [
//     'calculated_denomination_balance' => 85000.00,
//     'ledger_balance' => 85000.00,
//     'discrepancy' => 0.00,
//     'is_reconciled' => true
// ]
```

### Maker-Checker Inter-Account Transfers
```php
use Ongudidan\PaymentsEngine\Services\PaymentAccountTransferService;

$transferService = app(PaymentAccountTransferService::class);

// 1. Cashier initiates drawer-to-vault transfer
$transfer = $transferService->initiateTransfer([
    'from_account_id' => 3, // Drawer #1
    'to_account_id' => 1,   // Main Vault
    'amount' => 50000.00,
    'charges' => 0.00,
    'reference' => 'EOD-DROP-2026',
    'initiated_by' => auth()->id(),
]);
// If amount > config('transfers.require_approval_above'), status is 'pending'

// 2. Manager approves the transfer
$approvedTransfer = $transferService->approveTransfer($transfer->id, auth()->id());
// Balances update and double-entry flow records are created
```

### Financial & Audit Reports
```php
use Ongudidan\PaymentsEngine\Services\Reports\TrialBalanceReportService;
use Ongudidan\PaymentsEngine\Services\Reports\BalanceSheetReportService;
use Ongudidan\PaymentsEngine\Services\Reports\ProfitLossReportService;
use Ongudidan\PaymentsEngine\Services\Reports\UnallocatedSuspenseReportService;

// Trial Balance (guaranteed balanced debits === credits)
$trialBalance = app(TrialBalanceReportService::class)->generate(now()->toDateString());

// Balance Sheet
$balanceSheet = app(BalanceSheetReportService::class)->generate(now()->toDateString());

// Profit & Loss Statement
$pnl = app(ProfitLossReportService::class)->generate('2026-01-01', '2026-12-31');

// Unallocated Suspense Receipts (Account 2016)
$suspense = app(UnallocatedSuspenseReportService::class)->generate();
```

---

## 5. Frontend UI (Vue 3 / Inertia & Blade)

When published with `php artisan vendor:publish --tag=payments-engine-vue`, all components are placed in your application's `resources/js/`:

### Included Pages & Tabs:
- **`Pages/PaymentAccounts/Index.vue`**: Master financial dashboard showing all accounts, balances, and quick actions.
- **`Pages/PaymentAccounts/Show.vue`**: Comprehensive 8-tab account details view:
  - `OverviewTab.vue`: Account balance cards, stats, and settings.
  - `TransactionsTab.vue`: Complete transaction history.
  - `TransfersTab.vue`: Inter-account transfer history and Maker-Checker approval actions.
  - `VaultBatchesTab.vue`: Cash vault drops, batch history, and receipt printing.
  - `ChequesTab.vue`: Cheque collection, clearing modal, and cancellation.
  - `PayoutsTab.vue`: Direct disbursements and vendor payouts.
  - `PulledTransactionsTab.vue`: Missed M-Pesa transactions.
  - `PullRequestsTab.vue`: Historical M-Pesa pull jobs.
- **`Pages/Mpesa/Index.vue` & `Mpesa/MpesaConfigTable.vue`**: Manage shortcodes, environments, encryption, and Daraja C2B URL registrations.
- **`Pages/Mpesa/CompareStatement.vue`**: Interactive statement reconciliation matching Safaricom CSV/Excel statements against system transactions.

### Included Modals & Utility Components:
- **`SearchableSelect.vue`**: High-performance searchable dropdown replacing all standard `<select>` inputs.
- **`DenominationCounter.vue`**: Real-time physical note calculator for 1000 down to 1.
- **`MpesaPaymentModal.vue`**: Checkout modal triggering STK push with live status polling.
- **`AccountTransferModal.vue`**: Maker-Checker inter-account transfer modal.
- **`MultiTenderPaymentModal.vue`**: Split payments across Cash, M-Pesa, Card, and Customer Wallet.
- **`UnallocatedPaymentsTable.vue`**: 1-click settlement table for M-Pesa Suspense Account (`2016`).
- **`Layouts/AppLayout.vue`**: Zero-dependency layout with breadcrumb support.
- **`Services/qzPrinter.ts`**: Resilient QZ Tray thermal and document printing service with browser fallback.

---

## 6. Schedulers & Background Workers

Register automated workers in `routes/console.php` or `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Automatically poll for missed M-Pesa transactions every 5 minutes
Schedule::command('payments-engine:mpesa-auto-pull')->everyFiveMinutes();

// Automatically sweep accumulated funds from Till/Paybill to Bank accounts hourly
Schedule::command('payments-engine:mpesa-auto-sweep')->hourly();
```

---

## 7. AI Agent Integration Playbook

When instructing an AI coding agent (e.g., Cursor, Windsurf, Claude Code, Gemini CLI, or Antigravity) to integrate `ongudidan/laravel-payments-engine` into a new or existing application, ensure the agent adheres to these strict rules:

### Golden Rules for AI Agents:
1. **Never Run `npm run build`**: Do not build frontend assets unprompted during development.
2. **Never Run Test Suites or Cache Clears Unprompted**: Rely on static inspection and run targeted unit tests only.
3. **Axios Ban**: In Vue 3 / Inertia components, **NEVER** use `axios`. Always use Inertia `router`, `useForm`, or standard browser `fetch`.
4. **SearchableSelect Only**: Never generate raw HTML `<select>` elements. Always deploy the included `SearchableSelect.vue`.
5. **Cheque Clearance Invariant**: Always defer GL posting and bank deductions until a collector is assigned and the bank transaction reference is cleared.
6. **Double-Entry Balance Guarantee**: Every journal entry generated by services must mathematically satisfy `SUM(debit) === SUM(credit)`.
7. **Configurable Models**: Offload all relationships to `config('payments-engine.models')` (e.g. `User`, `Customer`, `Supplier`, `Sale`, `Purchase`). Do not hardcode foreign table names.
8. **Table Prefix**: Do not alter migration prefixes. All tables use `config('payments-engine.table_prefix', 'pe_')`.

---

## 8. Copy-Paste Master Prompt for AI Agents

Copy and paste the exact prompt below into any AI agent working on a Laravel application to wire this package seamlessly:

```markdown
# TASK: Integrate `ongudidan/laravel-payments-engine` into this Laravel Application

You are tasked with integrating the enterprise package `ongudidan/laravel-payments-engine` into this application to handle all payment accounts, cash vault denominations, customer/supplier wallets, M-Pesa STK/C2B automation, double-entry general ledger, and financial reporting.

---

## 1. PRE-FLIGHT CHECKS & INSTALLATION
1. Check `composer.json` for `ongudidan/laravel-payments-engine`.
   - If in a monorepo, verify the `repositories` path points to `packages/ongudidan/laravel-payments-engine`.
   - Run `composer require ongudidan/laravel-payments-engine`.
2. Register the provider in `bootstrap/providers.php`:
   `Ongudidan\PaymentsEngine\PaymentsEngineServiceProvider::class`.
3. Publish package resources:
   ```bash
   php artisan vendor:publish --tag=payments-engine-config
   php artisan vendor:publish --tag=payments-engine-migrations
   php artisan vendor:publish --tag=payments-engine-seeders
   php artisan vendor:publish --tag=payments-engine-vue
   ```
4. Run migrations and seed the Chart of Accounts and Denominations:
   ```bash
   php artisan migrate
   php artisan db:seed --class="Ongudidan\PaymentsEngine\Database\Seeders\ChartOfAccountsSeeder"
   php artisan db:seed --class="Ongudidan\PaymentsEngine\Database\Seeders\CashDenominationsSeeder"
   ```

---

## 2. CONFIGURATION & MODEL BINDING
Edit `config/payments-engine.php` to bind this application's models:
- Set `'models.user'` to this app's User model (e.g., `App\Models\User::class`).
- Set `'models.customer'` to this app's Customer model.
- Set `'models.supplier'` to this app's Supplier model.
- Set `'models.sale'` to this app's Sales/Invoice model.
- Set `'models.purchase'` to this app's Purchase model.
- Keep table prefix as `'pe_'` to ensure clean separation from existing tables.

---

## 3. DOMAIN WIRING REQUIREMENTS
1. **Double-Entry General Ledger**:
   - Inject `Ongudidan\PaymentsEngine\Services\AccountingService` or `LedgerService`.
   - When orders, sales, or bills are paid, post double-entry `JournalEntry` records. Ensure every entry balances (`debit === credit`).
2. **Customer & Supplier Wallets**:
   - Use `Ongudidan\PaymentsEngine\Services\WalletService`.
   - Customer change deposits and top-ups credit Liability `2015`.
   - Paying invoices from wallet debits Liability `2015`.
   - Supplier advances debit Asset `1050`.
3. **M-Pesa Checkout & C2B Automation**:
   - Route POS and online checkout payments through `POST /api/v1/payments-engine/mpesa/stk-push`.
   - Point Safaricom C2B URLs to `/api/v1/payments-engine/mpesa/validation` and `/confirmation`.
   - Suspense transactions automatically credit Account `2016` (Unallocated Receipts). Use `MpesaSettlementService` for 1-click allocation to target invoices.
4. **Cheques**:
   - Issue cheques through `ChequeClearanceService`. Never deduct bank balances or post GL entries until collector is assigned and bank reference is cleared.
5. **Cash Vault & Denominations**:
   - For physical cash vault and drawer payments, bind `DenominationCounter.vue` and trigger `PaymentAccountService::healDenominationBalance()`.

---

## 4. STRICT ARCHITECTURAL RULES
- **No Builds**: Do NOT run `npm run build` unless explicitly instructed.
- **No Diagnostics**: Do not run ESLint, Pint, or cache clears unprompted. Rely on static checks.
- **Axios Ban**: In Vue/Inertia contexts, NEVER use `axios`. Use Inertia `router`, `useForm`, or browser `fetch`.
- **SearchableSelect**: Never use standard `<select>` tags in UI components. Deploy `SearchableSelect.vue`.

Verify all route endpoints and service bindings once complete.
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
