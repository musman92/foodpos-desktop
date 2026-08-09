<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['ingredient_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable()->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->nullOnDelete();
            $table->foreignId('menu_item_id')->nullable()->after('ingredient_id')->constrained('menu_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('stock_movements')->whereNull('ingredient_id')->delete();

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('menu_item_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['ingredient_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable(false)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
        });
    }
};
