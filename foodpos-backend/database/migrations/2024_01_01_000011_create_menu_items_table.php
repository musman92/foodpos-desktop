<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->default(0); // Calculated from recipe
            $table->string('sku')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('track_inventory')->default(true); // Whether to auto-deduct ingredients
            $table->integer('preparation_time')->nullable(); // Minutes
            $table->integer('sort_order')->default(0);
            // $table->json('variants')->nullable(); // Size, flavor, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('category_id');
            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};

