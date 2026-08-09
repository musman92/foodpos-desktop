<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('ingredient_id')->constrained()->onDelete('cascade');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('reserved_quantity', 10, 2)->default(0); // For pending orders
            $table->foreignId('unit_id')->constrained('units_of_measure')->onDelete('restrict');
            $table->decimal('average_cost', 10, 2)->default(0);
            $table->timestamp('last_restocked_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'ingredient_id']);
            $table->index('branch_id');
            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_stock');
    }
};

