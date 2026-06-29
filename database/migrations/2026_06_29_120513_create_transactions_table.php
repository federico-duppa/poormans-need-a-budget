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

            // Monto en centavos (minor units) y en la moneda de la cuenta.
            // Negativo = egreso (gasto), positivo = ingreso.
            $table->bigInteger('amount');

            // Moneda de la cuenta + conversión a moneda base del presupuesto.
            $table->string('currency', 3)->default('ARS');
            $table->decimal('exchange_rate', 20, 10)->nullable(); // 1 unidad de `currency` = X base
            $table->bigInteger('amount_base')->nullable();        // cache del monto en moneda base

            $table->foreignId('payee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // autor del movimiento

            $table->string('memo')->nullable();
            $table->boolean('cleared')->default(false);
            $table->boolean('has_splits')->default(false);

            // Transferencias: id de la otra "pata" del par.
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
