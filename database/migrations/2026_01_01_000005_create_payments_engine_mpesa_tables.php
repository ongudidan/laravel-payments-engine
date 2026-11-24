<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');

        if (! Schema::hasTable($prefix.'mpesa_configs')) {
            Schema::create($prefix.'mpesa_configs', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id')->nullable()->unique();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('name');
                $table->string('shortcode');
                $table->string('type')->default('paybill'); // paybill, till
                $table->string('nominated_number')->nullable();
                $table->text('consumer_key');
                $table->text('consumer_secret');
                $table->text('passkey')->nullable();
                $table->string('initiator_name')->nullable();
                $table->text('initiator_password')->nullable();
                $table->text('security_credential')->nullable();
                $table->string('environment')->default('sandbox'); // sandbox, live
                $table->string('c2b_secret_token')->nullable();
                $table->string('callback_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('urls_registered_at')->nullable();
                $table->json('last_registration_response')->nullable();
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('set null');
            });
        }

        if (! Schema::hasTable($prefix.'mpesa_transactions')) {
            Schema::create($prefix.'mpesa_transactions', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('sale_id')->nullable();
                $table->string('trans_id')->nullable()->index();
                $table->string('transaction_id')->nullable()->unique();
                $table->string('trans_type')->nullable();
                $table->timestamp('trans_time')->nullable();
                $table->decimal('trans_amount', 15, 2)->default(0.00);
                $table->decimal('amount', 15, 2)->default(0.00);
                $table->string('currency')->default('KES');
                $table->string('business_short_code')->nullable()->index();
                $table->string('bill_ref_number')->nullable()->index();
                $table->string('invoice_number')->nullable()->index();
                $table->decimal('org_account_balance', 15, 2)->default(0.00);
                $table->string('third_party_trans_id')->nullable();
                $table->string('msisdn')->nullable()->index();
                $table->string('phone_number')->nullable()->index();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('status')->default('pending')->index(); // pending, completed, failed, reversed, unallocated
                $table->text('result_desc')->nullable();
                $table->string('checkout_request_id')->nullable()->index();
                $table->string('mpesa_conversation_id')->nullable();
                $table->string('mpesa_originator_conversation_id')->nullable();
                $table->string('reversal_transaction_id')->nullable()->index();
                $table->boolean('is_matched')->default(false)->index();
                $table->string('allocation_status')->default('unallocated')->index(); // unallocated, allocated, refunded
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('set null');
            });
        }

        if (! Schema::hasTable($prefix.'mpesa_pull_requests')) {
            Schema::create($prefix.'mpesa_pull_requests', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id');
                $table->string('request_id')->index();
                $table->string('status')->default('pending');
                $table->timestamp('from_time')->nullable();
                $table->timestamp('to_time')->nullable();
                $table->integer('transactions_count')->default(0);
                $table->json('response_payload')->nullable();
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'mpesa_pull_transactions')) {
            Schema::create($prefix.'mpesa_pull_transactions', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id');
                $table->unsignedBigInteger('mpesa_pull_request_id')->nullable();
                $table->string('trans_id')->index();
                $table->string('transaction_type')->nullable();
                $table->timestamp('trans_time')->nullable();
                $table->decimal('trans_amount', 15, 2);
                $table->string('business_short_code')->nullable();
                $table->string('bill_ref_number')->nullable();
                $table->string('invoice_number')->nullable();
                $table->decimal('org_account_balance', 15, 2)->default(0.00);
                $table->string('third_party_trans_id')->nullable();
                $table->string('msisdn')->nullable();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('reconciliation_status')->default('pending');
                $table->unsignedBigInteger('matched_mpesa_transaction_id')->nullable();
                $table->unsignedBigInteger('matched_sale_id')->nullable();
                $table->unsignedBigInteger('matched_customer_id')->nullable();
                $table->timestamp('reconciled_at')->nullable();
                $table->unsignedBigInteger('reconciled_by')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
                $table->foreign('mpesa_pull_request_id')->references('id')->on($prefix.'mpesa_pull_requests')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'mpesa_auto_pull_configs')) {
            Schema::create($prefix.'mpesa_auto_pull_configs', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id')->unique();
                $table->boolean('is_enabled')->default(false);
                $table->integer('interval_minutes')->default(30);
                $table->boolean('auto_record')->default(true);
                $table->boolean('discrepancy_only')->default(true);
                $table->integer('lookback_hours')->default(2);
                $table->string('last_status')->default('idle');
                $table->text('last_message')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'mpesa_auto_pull_logs')) {
            Schema::create($prefix.'mpesa_auto_pull_logs', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id');
                $table->string('status');
                $table->integer('pulled_count')->default(0);
                $table->integer('matched_count')->default(0);
                $table->integer('unmatched_count')->default(0);
                $table->text('message')->nullable();
                $table->json('details')->nullable();
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'mpesa_auto_sweep_configs')) {
            Schema::create($prefix.'mpesa_auto_sweep_configs', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id')->unique();
                $table->unsignedBigInteger('destination_bank_account_id')->nullable();
                $table->boolean('is_enabled')->default(false);
                $table->time('sweep_time')->nullable();
                $table->decimal('min_sweep_amount', 15, 2)->default(50000.00);
                $table->timestamp('last_sweep_at')->nullable();
                $table->string('last_status')->nullable();
                $table->text('last_error')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
                $table->foreign('destination_bank_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('set null');
            });
        }

        if (! Schema::hasTable($prefix.'mpesa_auto_sweep_runs')) {
            Schema::create($prefix.'mpesa_auto_sweep_runs', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id');
                $table->unsignedBigInteger('auto_sweep_config_id');
                $table->decimal('amount', 15, 2);
                $table->string('status')->default('pending'); // pending, completed, failed
                $table->unsignedBigInteger('transfer_id')->nullable();
                $table->text('message')->nullable();
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
                $table->foreign('auto_sweep_config_id')->references('id')->on($prefix.'mpesa_auto_sweep_configs')->onDelete('cascade');
                $table->foreign('transfer_id')->references('id')->on($prefix.'payment_account_transfers')->onDelete('set null');
            });
        }

        if (! Schema::hasTable($prefix.'mpesa_auto_sweep_logs')) {
            Schema::create($prefix.'mpesa_auto_sweep_logs', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id');
                $table->unsignedBigInteger('auto_sweep_run_id')->nullable();
                $table->string('status');
                $table->text('message')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
                $table->foreign('auto_sweep_run_id')->references('id')->on($prefix.'mpesa_auto_sweep_runs')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');
        Schema::dropIfExists($prefix.'mpesa_auto_sweep_logs');
        Schema::dropIfExists($prefix.'mpesa_auto_sweep_runs');
        Schema::dropIfExists($prefix.'mpesa_auto_sweep_configs');
        Schema::dropIfExists($prefix.'mpesa_auto_pull_logs');
        Schema::dropIfExists($prefix.'mpesa_auto_pull_configs');
        Schema::dropIfExists($prefix.'mpesa_pull_transactions');
        Schema::dropIfExists($prefix.'mpesa_pull_requests');
        Schema::dropIfExists($prefix.'mpesa_transactions');
        Schema::dropIfExists($prefix.'mpesa_configs');
    }
};
