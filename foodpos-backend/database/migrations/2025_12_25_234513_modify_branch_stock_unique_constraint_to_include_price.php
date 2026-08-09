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
        Schema::table('branch_stock', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'ingredient_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE branch_stock ADD UNIQUE KEY branch_stock_unique (branch_id, ingredient_id, average_cost)');
        } else {
            Schema::table('branch_stock', function (Blueprint $table) {
                $table->unique(['branch_id', 'ingredient_id', 'average_cost'], 'branch_stock_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE branch_stock DROP INDEX branch_stock_unique');
        } else {
            Schema::table('branch_stock', function (Blueprint $table) {
                $table->dropUnique('branch_stock_unique');
            });
        }

        Schema::table('branch_stock', function (Blueprint $table) {
            $table->unique(['branch_id', 'ingredient_id']);
        });
    }
};
