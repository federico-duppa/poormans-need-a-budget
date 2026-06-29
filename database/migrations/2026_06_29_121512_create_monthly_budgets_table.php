<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->date('month'); // primer día del mes (YYYY-MM-01)
            $table->timestamps();

            $table->unique(['budget_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_budgets');
    }
};
