<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredient_units', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('name');
            $table->unique(['company_id', 'code']);
        });

        $companyIds = DB::table('ingredient_units')
            ->select('company_id')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $units = DB::table('ingredient_units')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id']);

            $counter = 1;
            foreach ($units as $unit) {
                DB::table('ingredient_units')
                    ->where('id', $unit->id)
                    ->update([
                        'code' => 'C'.str_pad((string) $counter, 2, '0', STR_PAD_LEFT),
                    ]);
                $counter++;
            }
        }
    }

    public function down(): void
    {
        Schema::table('ingredient_units', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
