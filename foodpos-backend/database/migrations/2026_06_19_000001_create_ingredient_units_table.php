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
        Schema::create('ingredient_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->unique(['company_id', 'name']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->foreignId('ingredient_unit_id')
                ->nullable()
                ->after('base_unit_id')
                ->constrained('ingredient_units')
                ->nullOnDelete();
        });

        $this->seedUnitsFromConfig();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ingredient_unit_id');
        });

        Schema::dropIfExists('ingredient_units');
    }

    private function seedUnitsFromConfig(): void
    {
        $configUnits = config('pos.units', []);
        if ($configUnits === []) {
            return;
        }

        $companyIds = DB::table('companies')->pluck('id');
        $now = now();

        foreach ($companyIds as $companyId) {
            $unitIdsByKey = [];

            foreach ($configUnits as $key => $label) {
                $unitIdsByKey[$key] = DB::table('ingredient_units')->insertGetId([
                    'company_id' => $companyId,
                    'name' => $label,
                    'description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $ingredients = DB::table('ingredients')
                ->where('company_id', $companyId)
                ->whereNotNull('base_unit_id')
                ->get(['id', 'base_unit_id']);

            foreach ($ingredients as $ingredient) {
                $unitId = $unitIdsByKey[$ingredient->base_unit_id] ?? null;
                if ($unitId) {
                    DB::table('ingredients')
                        ->where('id', $ingredient->id)
                        ->update(['ingredient_unit_id' => $unitId]);
                }
            }
        }
    }
};
