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
        if (DB::getDriverName() === 'mysql') {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'stock_movements'
                AND COLUMN_NAME = 'unit_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            foreach ($foreignKeys as $foreignKey) {
                Schema::table('stock_movements', function (Blueprint $table) use ($foreignKey) {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                });
            }
        }

        if (DB::getDriverName() === 'mysql') {
            $indexNames = collect(DB::select("SHOW INDEX FROM stock_movements WHERE Column_name = 'unit_id'"))
                ->pluck('Key_name')
                ->unique()
                ->reject(fn (string $name) => $name === 'PRIMARY');

            foreach ($indexNames as $indexName) {
                DB::statement("ALTER TABLE stock_movements DROP INDEX `{$indexName}`");
            }

            DB::statement('ALTER TABLE stock_movements MODIFY unit_id VARCHAR(255) NOT NULL');
        } else {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropForeign(['unit_id']);
                $table->string('unit_id')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('unit_id')->change();
            $table->foreign('unit_id')->references('id')->on('units_of_measure')->onDelete('restrict');
            $table->index('unit_id');
        });
    }
};
