<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');

        if (! Schema::hasTable($prefix.'cash_drawer_transactions')) {
            Schema::create($prefix.'cash_drawer_transactions', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('drawer_id');
                $table->string('transaction_type'); // sale, float_in, change, drop_out, payout
                $table->decimal('amount', 15, 2);
                $table->unsignedBigInteger('sale_id')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->text('notes')->nullable();
                $table->string('reference_number')->nullable()->index();
                $table->timestamps();

                $table->foreign('drawer_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'float_requests')) {
            Schema::create($prefix.'float_requests', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('vault_account_id');
                $table->unsignedBigInteger('drawer_id');
                $table->unsignedBigInteger('cashier_id');
                $table->unsignedBigInteger('approver_id')->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('status')->default('Pending'); // Pending, Approved, Rejected, Cancelled
                $table->text('request_notes')->nullable();
                $table->text('approver_notes')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->foreign('vault_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
                $table->foreign('drawer_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'cash_reconciliations')) {
            Schema::create($prefix.'cash_reconciliations', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('vault_account_id');
                $table->unsignedBigInteger('drawer_id');
                $table->unsignedBigInteger('cashier_id');
                $table->unsignedBigInteger('approver_id')->nullable();
                $table->decimal('amount_submitted', 15, 2);
                $table->decimal('amount_expected', 15, 2);
                $table->decimal('difference', 15, 2)->default(0.00);
                $table->string('status')->default('Pending'); // Pending, Approved, Rejected
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('cashier_notes')->nullable();
                $table->text('approver_notes')->nullable();
                $table->timestamps();

                $table->foreign('vault_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
                $table->foreign('drawer_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');
        Schema::dropIfExists($prefix.'cash_reconciliations');
        Schema::dropIfExists($prefix.'float_requests');
        Schema::dropIfExists($prefix.'cash_drawer_transactions');
    }
};
