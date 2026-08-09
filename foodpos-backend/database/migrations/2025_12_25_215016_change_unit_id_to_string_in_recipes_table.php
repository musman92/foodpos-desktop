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
                AND TABLE_NAME = 'recipes'
                AND COLUMN_NAME = 'unit_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (! empty($foreignKeys)) {
                $constraintName = $foreignKeys[0]->CONSTRAINT_NAME;
                DB::statement("ALTER TABLE recipes DROP FOREIGN KEY {$constraintName}");
            }
        }

        if (DB::getDriverName() === 'mysql') {
            $indexNames = collect(DB::select("SHOW INDEX FROM recipes WHERE Column_name = 'unit_id'"))
                ->pluck('Key_name')
                ->unique()
                ->reject(fn (string $name) => $name === 'PRIMARY');

            foreach ($indexNames as $indexName) {
                DB::statement("ALTER TABLE recipes DROP INDEX `{$indexName}`");
            }

            DB::statement('ALTER TABLE recipes MODIFY unit_id VARCHAR(255) NULL');
        } else {
            Schema::table('recipes', function (Blueprint $table) {
                $table->dropForeign(['unit_id']);
                $table->string('unit_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->change();
            $table->foreign('unit_id')->references('id')->on('units_of_measure')->onDelete('restrict');
            $table->index('unit_id');
        });
    }
};
