<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');

        if (! Schema::hasTable($prefix.'cheque_clearances')) {
            Schema::create($prefix.'cheque_clearances', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('direction')->default('inbound'); // inbound (customer received), outbound (supplier issued)
                $table->string('chequeable_type')->nullable();
                $table->unsignedBigInteger('chequeable_id')->nullable();
                $table->unsignedBigInteger('payment_account_id')->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('cheque_number')->index();
                $table->date('cheque_date')->nullable();
                $table->string('bank_name')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('Pending'); // Pending, Collector Assigned, Cleared, Cancelled, Bounced
                $table->string('transaction_number')->nullable(); // bank transaction reference required for clearance
                $table->timestamp('collected_at')->nullable();
                $table->string('collected_by_name')->nullable();
                $table->string('collected_by_mobile')->nullable();
                $table->string('sales_rep_name')->nullable();
                $table->timestamp('cleared_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('cleared_by')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['chequeable_type', 'chequeable_id']);
                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');
        Schema::dropIfExists($prefix.'cheque_clearances');
    }
};
