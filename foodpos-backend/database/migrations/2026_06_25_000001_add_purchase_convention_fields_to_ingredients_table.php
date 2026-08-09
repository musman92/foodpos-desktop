<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->renameColumn('ingredient_unit_id', 'consumption_unit_id');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->foreignId('purchase_unit_id')
                ->nullable()
                ->after('consumption_unit_id')
                ->constrained('ingredient_units')
                ->nullOnDelete();
            $table->decimal('conversion_rate', 14, 4)->default(1)->after('purchase_unit_id');
            $table->decimal('purchase_price', 12, 2)->default(0)->after('conversion_rate');
            $table->foreignId('created_by')
                ->nullable()
                ->after('company_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('ingredients')
            ->whereNotNull('consumption_unit_id')
            ->whereNull('purchase_unit_id')
            ->update([
                'purchase_unit_id' => DB::raw('consumption_unit_id'),
                'conversion_rate' => 1,
            ]);

        DB::table('ingredients')
            ->where('purchase_price', 0)
            ->where('cost_per_unit', '>', 0)
            ->update([
                'purchase_price' => DB::raw('cost_per_unit'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropColumn(['conversion_rate', 'purchase_price']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->renameColumn('consumption_unit_id', 'ingredient_unit_id');
        });
    }
};
