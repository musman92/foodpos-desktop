<?php

use App\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')
            ->whereNotNull('deleted_at')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row): void {
                $phone = trim((string) $row->phone);
                if ($phone === '' || str_starts_with($phone, Customer::DELETED_PHONE_PREFIX)) {
                    return;
                }

                DB::table('customers')
                    ->where('id', $row->id)
                    ->update(['phone' => Customer::DELETED_PHONE_PREFIX.$phone]);
            });
    }

    public function down(): void
    {
        DB::table('customers')
            ->whereNotNull('deleted_at')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row): void {
                $phone = trim((string) $row->phone);
                $prefix = Customer::DELETED_PHONE_PREFIX;
                if (! str_starts_with($phone, $prefix)) {
                    return;
                }

                $restored = substr($phone, strlen($prefix));

                DB::table('customers')
                    ->where('id', $row->id)
                    ->update(['phone' => $restored !== '' ? $restored : null]);
            });
    }
};
