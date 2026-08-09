<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('purchase_unit_id')
                ->nullable()
                ->after('min_stock_level')
                ->constrained('ingredient_units')
                ->nullOnDelete();
            $table->foreignId('consumption_unit_id')
                ->nullable()
                ->after('purchase_unit_id')
                ->constrained('ingredient_units')
                ->nullOnDelete();
            $table->decimal('conversion_rate', 14, 4)->default(1)->after('consumption_unit_id');
            $table->decimal('purchase_price', 12, 2)->default(0)->after('conversion_rate');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropConstrainedForeignId('consumption_unit_id');
            $table->dropColumn(['conversion_rate', 'purchase_price']);
        });
    }
};
