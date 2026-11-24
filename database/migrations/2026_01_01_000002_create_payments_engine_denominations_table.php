<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');

        if (! Schema::hasTable($prefix.'cash_denominations')) {
            Schema::create($prefix.'cash_denominations', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['bill', 'coin'])->default('bill');
                $table->decimal('value', 10, 2);
                $table->string('currency')->default('KES');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($prefix.'payment_method_denominations')) {
            Schema::create($prefix.'payment_method_denominations', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_method_id');
                $table->decimal('value', 10, 2);
                $table->timestamps();

                $table->foreign('payment_method_id')->references('id')->on($prefix.'payment_methods')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'payment_account_denominations')) {
            Schema::create($prefix.'payment_account_denominations', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id');
                $table->unsignedBigInteger('denomination_id');
                $table->integer('quantity')->default(0);
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable($prefix.'payment_account_opening_denominations')) {
            Schema::create($prefix.'payment_account_opening_denominations', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->unsignedBigInteger('payment_account_id');
                $table->unsignedBigInteger('denomination_id');
                $table->integer('quantity')->default(0);
                $table->timestamps();

                $table->foreign('payment_account_id')->references('id')->on($prefix.'payment_accounts')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        $prefix = config('payments-engine.table_prefix', 'pe_');
        Schema::dropIfExists($prefix.'payment_account_opening_denominations');
        Schema::dropIfExists($prefix.'payment_account_denominations');
        Schema::dropIfExists($prefix.'payment_method_denominations');
        Schema::dropIfExists($prefix.'cash_denominations');
    }
};
