<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $companyIds = DB::table('ingredients')
            ->select('company_id')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $max = (int) DB::table('ingredients')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereNotNull('sku')
                ->pluck('sku')
                ->map(fn ($sku) => is_numeric(trim((string) $sku)) ? (int) trim((string) $sku) : 0)
                ->max();

            $ingredients = DB::table('ingredients')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereNull('sku')
                ->orderBy('id')
                ->pluck('id');

            foreach ($ingredients as $ingredientId) {
                $max++;
                DB::table('ingredients')
                    ->where('id', $ingredientId)
                    ->update(['sku' => (string) $max]);
            }
        }

        Schema::table('ingredients', function (Blueprint $table) {
            $table->unique(['company_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'sku']);
        });
    }
};
