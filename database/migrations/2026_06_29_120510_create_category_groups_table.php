<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_system')->default(false); // e.g.: "Pagos de tarjeta"
            $table->timestamps();

            $table->index('budget_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_groups');
    }
};
