<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')->where('phone', '')->update(['phone' => null]);

        $customers = DB::table('customers')
            ->whereNull('deleted_at')
            ->whereNotNull('phone')
            ->orderBy('company_id')
            ->orderBy('id')
            ->get(['id', 'company_id', 'phone']);

        $seen = [];
        foreach ($customers as $customer) {
            $digits = preg_replace('/\D/', '', (string) $customer->phone);
            if ($digits === '') {
                DB::table('customers')->where('id', $customer->id)->update(['phone' => null]);

                continue;
            }

            $key = $customer->company_id.':'.$digits;
            if (isset($seen[$key])) {
                DB::table('customers')->where('id', $customer->id)->update(['phone' => null]);
            } else {
                $seen[$key] = true;
            }
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['company_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'phone']);
        });
    }
};
