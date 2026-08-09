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
        Schema::table('menu_items', function (Blueprint $table) {
            $table->enum('type', ['single', 'recipe'])->default('single')->after('category_id');
            $table->foreignId('cuisine_id')->nullable()->after('type')->constrained('cuisines')->onDelete('set null');
            $table->index('cuisine_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['cuisine_id']);
            $table->dropIndex(['cuisine_id']);
            $table->dropColumn(['type', 'cuisine_id']);
        });
    }
};
