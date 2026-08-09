<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Kilogram", "Liter", "Piece"
            $table->string('abbreviation'); // e.g., "kg", "L", "pcs"
            $table->enum('type', ['weight', 'volume', 'length', 'count', 'other'])->default('other');
            $table->boolean('is_base_unit')->default(false); // Base unit for conversions
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};

