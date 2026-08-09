<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('suppliers')->where('phone', '')->update(['phone' => null]);
        DB::table('suppliers')->where('email', '')->update(['email' => null]);

        $suppliers = DB::table('suppliers')
            ->whereNull('deleted_at')
            ->orderBy('company_id')
            ->orderBy('id')
            ->get(['id', 'company_id', 'name', 'phone', 'email']);

        $seenNames = [];
        $seenPhones = [];
        $seenEmails = [];

        foreach ($suppliers as $supplier) {
            $nameKey = $supplier->company_id.':'.strtolower(trim((string) $supplier->name));
            if (isset($seenNames[$nameKey])) {
                DB::table('suppliers')
                    ->where('id', $supplier->id)
                    ->update(['name' => trim((string) $supplier->name).' ('.$supplier->id.')']);
            } else {
                $seenNames[$nameKey] = true;
            }

            if ($supplier->phone !== null) {
                $phoneDigits = preg_replace('/\D/', '', (string) $supplier->phone);
                if ($phoneDigits === '') {
                    DB::table('suppliers')->where('id', $supplier->id)->update(['phone' => null]);
                } else {
                    $phoneKey = $supplier->company_id.':'.$phoneDigits;
                    if (isset($seenPhones[$phoneKey])) {
                        DB::table('suppliers')->where('id', $supplier->id)->update(['phone' => null]);
                    } else {
                        $seenPhones[$phoneKey] = true;
                    }
                }
            }

            if ($supplier->email !== null) {
                $emailKey = $supplier->company_id.':'.strtolower(trim((string) $supplier->email));
                if ($emailKey === $supplier->company_id.':') {
                    DB::table('suppliers')->where('id', $supplier->id)->update(['email' => null]);
                } elseif (isset($seenEmails[$emailKey])) {
                    DB::table('suppliers')->where('id', $supplier->id)->update(['email' => null]);
                } else {
                    $seenEmails[$emailKey] = true;
                    DB::table('suppliers')
                        ->where('id', $supplier->id)
                        ->update(['email' => strtolower(trim((string) $supplier->email))]);
                }
            }
        }

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'phone']);
            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->dropUnique(['company_id', 'phone']);
            $table->dropUnique(['company_id', 'email']);
        });
    }
};
