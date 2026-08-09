<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('deal_id')->nullable()->after('order_id')->constrained()->onDelete('set null');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Remove order items that have no menu_item_id (deal-only rows) so we can make the column NOT NULL again
        DB::table('order_items')->whereNull('menu_item_id')->delete();

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_item_id')->nullable(false)->change();
        });
    }
};
