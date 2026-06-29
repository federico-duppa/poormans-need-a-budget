<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            // Amount in cents (minor units) and in the account's currency.
            // Negative = outflow (expense), positive = inflow (income).
            $table->bigInteger('amount');

            // Account currency + conversion to the budget's base currency.
            $table->string('currency', 3)->default('ARS');
            $table->decimal('exchange_rate', 20, 10)->nullable(); // 1 unit of `currency` = X base
            $table->bigInteger('amount_base')->nullable();        // cache of the amount in base currency

            $table->foreignId('payee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // transaction author

            $table->string('memo')->nullable();
            $table->boolean('cleared')->default(false);
            $table->boolean('has_splits')->default(false);

            // Transfers: id of the other "leg" of the pair.
            $table->foreignId('transfer_pair_id')->nullable()
                ->constrained('transactions')->nullOnDelete();

            $table->timestamps();

            $table->index(['account_id', 'date']);
            $table->index(['category_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
