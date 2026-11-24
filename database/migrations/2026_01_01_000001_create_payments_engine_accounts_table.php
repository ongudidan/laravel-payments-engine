<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');

        if (! Schema::hasTable($prefix.'accounts')) {
            Schema::create($prefix.'accounts', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('type'); // asset, liability, equity, income, expense
                $table->string('subtype')->nullable();
                $table->string('normal_balance')->default('debit'); // debit, credit
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->string('currency')->default('KES');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('parent_id')->references('id')->on($prefix.'accounts')->onDelete('set null');
            });
        }

        if (! Schema::hasTable($prefix.'payment_methods')) {
            Schema::create($prefix.'payment_methods', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->index();
                $table->unsignedBigInteger('payment_account_id')->nullable();
                $table->string('channel')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->text('description')->nullable();
                $table->boolean('requires_reference')->default(false);
                $table->boolean('has_denominations')->default(false);
                $table->boolean('is_wallet')->default(false);
                $table->boolean('is_bank')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_supplier_recharge')->default(false);
                $table->boolean('has_linked_bank')->default(false);
                $table->boolean('is_mpesa_agent')->default(false);
                $table->timestamps();

                $table->foreign('parent_id')->references('id')->on($prefix.'payment_methods')->onDelete('set null');
            });
        }

        if (! Schema::hasTable($prefix.'payment_accounts')) {
            Schema::create($prefix.'payment_accounts', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->string('name');
                $table->string('type')->default('cash'); // cash, bank, mpesa_till, mpesa_paybill, wallet
                $table->string('account_number')->nullable();
                $table->string('nominated_number')->nullable();
                $table->unsignedBigInteger('bank_id')->nullable();
                $table->string('bank_name')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('payment_method_id')->nullable();
                $table->string('currency')->default('KES');
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->decimal('opening_balance', 15, 2)->default(0.00);
                $table->decimal('float_balance', 15, 2)->default(0.00);
                $table->decimal('pending_cheque_balance', 15, 2)->default(0.00);
                $table->unsignedBigInteger('linked_bank_account_id')->nullable();
                $table->unsignedBigInteger('chart_of_account_id')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->boolean('has_denominations')->default(false);
                $table->boolean('is_vault')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('can_make_payments')->default(true);
                $table->boolean('can_receive_payments')->default(true);
                $table->boolean('requires_reconciliation')->default(false);
                $table->string('ledger_reference')->nullable();
                $table->timestamps();

                $table->foreign('linked_bank_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('set null');
                $table->foreign('payment_method_id')->references('id')->on($prefix.'payment_methods')->onDelete('set null');
                $table->foreign('chart_of_account_id')->references('id')->on($prefix.'accounts')->onDelete('set null');
                $table->foreign('account_id')->references('id')->on($prefix.'accounts')->onDelete('set null');
            });
        }

        if (! Schema::hasTable($prefix.'payment_method_mappings')) {
            Schema::create($prefix.'payment_method_mappings', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_method_id');
                $table->string('channel');
                $table->string('provider')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('payment_method_id')->references('id')->on($prefix.'payment_methods')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');
        Schema::dropIfExists($prefix.'payment_method_mappings');
        Schema::dropIfExists($prefix.'payment_accounts');
        Schema::dropIfExists($prefix.'payment_methods');
        Schema::dropIfExists($prefix.'accounts');
    }
};
