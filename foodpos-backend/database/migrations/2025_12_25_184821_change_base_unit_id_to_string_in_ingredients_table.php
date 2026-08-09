<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['base_unit_id']);
            // Drop index
            $table->dropIndex(['base_unit_id']);
            // Change column from foreignId to string
            $table->string('base_unit_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            // Change back to foreignId (this might fail if data doesn't match)
            $table->foreignId('base_unit_id')->change();
            $table->foreign('base_unit_id')->references('id')->on('units_of_measure')->onDelete('restrict');
            $table->index('base_unit_id');
        });
    }
};
