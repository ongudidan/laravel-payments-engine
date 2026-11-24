<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Database Table Configuration
    |--------------------------------------------------------------------------
    |
    | Define the table prefix for all tables created and used by the payments
    | engine package. By default, 'pe_' is used.
    |
    */
    'table_prefix' => env('PAYMENTS_ENGINE_TABLE_PREFIX', 'pe_'),

    /*
    |--------------------------------------------------------------------------
    | Model Mapping Registry
    |--------------------------------------------------------------------------
    |
    | Maps domain model keys to host application Eloquent models.
    |
    */
    'models' => [
        'user' => User::class,
        'customer' => Customer::class,
        'supplier' => Supplier::class,
        'company' => Company::class,
        'branch' => Branch::class,
        'sale' => Sale::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Host Table Mapping Registry
    |--------------------------------------------------------------------------
    |
    | Maps foreign reference tables in the host application.
    |
    */
    'tables' => [
        'users' => 'users',
        'customers' => 'customers',
        'suppliers' => 'suppliers',
        'companies' => 'companies',
        'branches' => 'branches',
        'sales' => 'sales',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Chart of Accounts Mapping
    |--------------------------------------------------------------------------
    |
    | Chart of accounts code identifiers for automatic double-entry posting.
    |
    */
    'accounts' => [
        'main_vault' => '1001',
        'cash_drawer' => '1002',
        'bank_operating' => '1010',
        'mpesa_till' => '1020',
        'supplier_prepayments' => '1050',
        'accounts_receivable' => '1200',
        'accounts_payable' => '2000',
        'customer_wallet' => '2015',
        'unallocated_suspense' => '2016',
        'sales_revenue' => '4000',
        'transaction_charges' => '6000',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Physical Denominations
    |--------------------------------------------------------------------------
    |
    | Standard currency bill and coin values.
    |
    */
    'denominations' => [1000, 500, 200, 100, 50, 40, 20, 10, 5, 1],

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Daraja Configuration
    |--------------------------------------------------------------------------
    |
    | Integration settings for Safaricom Daraja STK Push, C2B, and automated sweeps.
    |
    */
    'mpesa' => [
        'env' => env('MPESA_ENV', 'sandbox'),
        'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),
        'passkey' => env('MPESA_PASSKEY', ''),
        'shortcode' => env('MPESA_SHORTCODE', ''),
        'initiator_name' => env('MPESA_INITIATOR_NAME', ''),
        'initiator_password' => env('MPESA_INITIATOR_PASSWORD', ''),
        'c2b_secret_token' => env('MPESA_C2B_SECRET_TOKEN', ''),
        'callback_url' => env('MPESA_CALLBACK_URL', '/api/v1/mpesa/callback'),
        'auto_sweep_enabled' => env('MPESA_AUTO_SWEEP', false),
        'auto_sweep_threshold' => env('MPESA_AUTO_SWEEP_THRESHOLD', 50000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer & Supplier Wallet Configuration
    |--------------------------------------------------------------------------
    |
    */
    'wallet' => [
        'allow_negative_balance' => false,
        'auto_refund_overpayments' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Inter-Account Transfers & Approval Flow
    |--------------------------------------------------------------------------
    |
    */
    'transfers' => [
        'require_maker_checker' => true,
        'max_unapproved_amount' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing Configuration
    |--------------------------------------------------------------------------
    |
    */
    'routes' => [
        'prefix' => 'api/v1/payments-engine',
        'middleware' => ['api'],
    ],
];
