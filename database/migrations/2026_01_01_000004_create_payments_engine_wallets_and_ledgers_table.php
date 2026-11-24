<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');

        if (! Schema::hasTable($prefix.'customer_wallet_transactions')) {
            Schema::create($prefix.'customer_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('sale_id')->nullable();
                $table->unsignedBigInteger('mpesa_transaction_id')->nullable();
                $table->string('type'); // credit, debit
                $table->decimal('amount', 15, 2);
                $table->decimal('balance_after', 15, 2);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_number')->nullable()->index();
                $table->text('notes')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'supplier_wallet_transactions')) {
            Schema::create($prefix.'supplier_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                $table->string('type'); // credit, debit
                $table->decimal('amount', 15, 2);
                $table->decimal('balance_after', 15, 2);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_number')->nullable()->index();
                $table->text('notes')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'user_customer_wallet_balances')) {
            Schema::create($prefix.'user_customer_wallet_balances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('customer_id');
                $table->decimal('balance', 15, 2)->default(0.00);
                $table->timestamps();

                $table->unique(['user_id', 'customer_id']);
            });
        }

        if (! Schema::hasTable($prefix.'account_offsets')) {
            Schema::create($prefix.'account_offsets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'entity_account_ledgers')) {
            Schema::create($prefix.'entity_account_ledgers', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->string('account_code')->index();
                $table->string('account_type'); // asset, liability, equity, income, expense
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('supplier_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->decimal('debit', 15, 4)->default(0);
                $table->decimal('credit', 15, 4)->default(0);
                $table->decimal('balance', 15, 4)->default(0); // running balance
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('entry_type')->index();
                $table->string('reference')->nullable()->index();
                $table->text('description')->nullable();
                $table->date('posting_date')->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['source_type', 'source_id']);
                $table->foreign('account_id')->references('id')->on($prefix.'accounts')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');
        Schema::dropIfExists($prefix.'entity_account_ledgers');
        Schema::dropIfExists($prefix.'account_offsets');
        Schema::dropIfExists($prefix.'user_customer_wallet_balances');
        Schema::dropIfExists($prefix.'supplier_wallet_transactions');
        Schema::dropIfExists($prefix.'customer_wallet_transactions');
    }
};
