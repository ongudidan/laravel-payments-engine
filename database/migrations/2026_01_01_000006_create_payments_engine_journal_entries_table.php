<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');

        if (! Schema::hasTable($prefix.'journal_entries')) {
            Schema::create($prefix.'journal_entries', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('reference')->unique()->nullable();
                $table->text('description')->nullable();
                $table->string('entry_type')->default('General'); // Sale, Purchase, Payment, Transfer, Reversal, Adjustment
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'journal_items')) {
            Schema::create($prefix.'journal_items', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('journal_entry_id');
                $table->unsignedBigInteger('account_id');
                $table->decimal('debit', 15, 2)->default(0.00);
                $table->decimal('credit', 15, 2)->default(0.00);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->timestamps();

                $table->foreign('journal_entry_id')->references('id')->on($prefix.'journal_entries')->onDelete('cascade');
                $table->foreign('account_id')->references('id')->on($prefix.'accounts')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');
        Schema::dropIfExists($prefix.'journal_items');
        Schema::dropIfExists($prefix.'journal_entries');
    }
};
