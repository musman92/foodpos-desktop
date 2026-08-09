<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_menu_item', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
            $table->dropForeign(['menu_item_id']);
        });
        Schema::table('deal_menu_item', function (Blueprint $table) {
            $table->dropUnique(['deal_id', 'menu_item_id']);
        });
        Schema::table('deal_menu_item', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('menu_item_id')->constrained()->onDelete('cascade');
            $table->string('option_name')->nullable()->after('variant_id');
            $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');
        });
        Schema::table('deal_menu_item', function (Blueprint $table) {
            $table->foreign('deal_id')->references('id')->on('deals')->onDelete('cascade');
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('deal_menu_item', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
            $table->dropForeign(['menu_item_id']);
        });
        Schema::table('deal_menu_item', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropColumn(['variant_id', 'option_name', 'unit_price']);
        });
        Schema::table('deal_menu_item', function (Blueprint $table) {
            $table->unique(['deal_id', 'menu_item_id']);
            $table->foreign('deal_id')->references('id')->on('deals')->onDelete('cascade');
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->onDelete('cascade');
        });
    }
};
