<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');

        if (! Schema::hasTable($prefix.'payment_transactions')) {
            Schema::create($prefix.'payment_transactions', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_method_id')->nullable();
                $table->unsignedBigInteger('payment_method_mapping_id')->nullable();
                $table->unsignedBigInteger('payment_account_id')->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('reference')->nullable()->index();
                $table->string('provider_reference')->nullable()->index();
                $table->text('notes')->nullable();
                $table->json('cheque_details')->nullable();
                $table->string('status')->default('pending');
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->timestamp('initiated_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->decimal('amount_due', 15, 2)->nullable();
                $table->decimal('amount_given', 15, 2)->nullable();
                $table->decimal('change_given', 15, 2)->nullable();
                $table->string('change_status')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('payment_method_id')->references('id')->on($prefix.'payment_methods')->onDelete('set null');
                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('set null');
            });
        }

        if (! Schema::hasTable($prefix.'payment_transaction_flows')) {
            Schema::create($prefix.'payment_transaction_flows', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_transaction_id');
                $table->unsignedBigInteger('from_account_id')->nullable();
                $table->unsignedBigInteger('to_account_id')->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('action'); // transfer_sent, transfer_received, deposit, withdrawal, refund
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('payment_transaction_id')->references('id')->on($prefix.'payment_transactions')->onDelete('cascade');
                $table->foreign('from_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
                $table->foreign('to_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'payment_transaction_denominations')) {
            Schema::create($prefix.'payment_transaction_denominations', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_transaction_id');
                $table->unsignedBigInteger('denomination_id');
                $table->integer('quantity');
                $table->timestamps();

                $table->foreign('payment_transaction_id')->references('id')->on($prefix.'payment_transactions')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'payment_account_transfers')) {
            Schema::create($prefix.'payment_account_transfers', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->string('transfer_no')->nullable()->unique();
                $table->unsignedBigInteger('from_account_id');
                $table->unsignedBigInteger('to_account_id');
                $table->decimal('amount', 15, 2);
                $table->decimal('charges', 15, 2)->default(0.00);
                $table->decimal('received_amount', 15, 2)->nullable();
                $table->string('reference')->unique()->nullable();
                $table->string('transaction_number')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
                $table->unsignedBigInteger('initiated_by')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();

                $table->foreign('from_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
                $table->foreign('to_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'payment_transfer_denominations')) {
            Schema::create($prefix.'payment_transfer_denominations', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('transfer_id');
                $table->unsignedBigInteger('denomination_id');
                $table->integer('quantity');
                $table->integer('received_quantity')->nullable();
                $table->timestamps();

                $table->foreign('transfer_id')->references('id')->on($prefix.'payment_account_transfers')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'payment_account_adjustments')) {
            Schema::create($prefix.'payment_account_adjustments', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('payment_account_id');
                $table->unsignedBigInteger('reconciliation_id')->nullable();
                $table->string('type')->default('adjustment');
                $table->decimal('old_balance', 15, 2)->default(0.00);
                $table->decimal('new_balance', 15, 2)->default(0.00);
                $table->decimal('old_float_balance', 15, 2)->default(0.00);
                $table->decimal('new_float_balance', 15, 2)->default(0.00);
                $table->decimal('amount', 15, 2)->default(0.00);
                $table->json('denominations_data')->nullable();
                $table->text('reason')->nullable();
                $table->string('status')->default('Pending'); // Pending, Approved, Rejected
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');
        Schema::dropIfExists($prefix.'payment_account_adjustments');
        Schema::dropIfExists($prefix.'payment_transfer_denominations');
        Schema::dropIfExists($prefix.'payment_account_transfers');
        Schema::dropIfExists($prefix.'payment_transaction_denominations');
        Schema::dropIfExists($prefix.'payment_transaction_flows');
        Schema::dropIfExists($prefix.'payment_transactions');
    }
};
