<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            // Amount assigned to the category in that month, in cents of base currency.
            $table->bigInteger('assigned')->default(0);
            $table->timestamps();

            $table->unique(['monthly_budget_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_months');
    }
};
