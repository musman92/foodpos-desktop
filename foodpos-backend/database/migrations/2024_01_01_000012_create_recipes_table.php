<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('ingredient_id')->constrained()->onDelete('cascade');
            $table->decimal('quantity', 10, 2); // e.g., 50 (grams of cheese)
            $table->foreignId('unit_id')->constrained('units_of_measure')->onDelete('restrict');
            $table->decimal('waste_percentage', 5, 2)->default(0); // Waste factor
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['menu_item_id', 'ingredient_id']);
            $table->index('menu_item_id');
            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};

