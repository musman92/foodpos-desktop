<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->foreignId('base_unit_id')->constrained('units_of_measure')->onDelete('restrict');
            $table->decimal('cost_per_unit', 10, 2)->default(0);
            $table->decimal('min_stock_level', 10, 2)->default(0);
            $table->decimal('max_stock_level', 10, 2)->nullable();
            $table->enum('track_stock', ['yes', 'no'])->default('yes');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('base_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};

