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
                if ($phone === '') {
                    return;
                }

                if (preg_match('/^del-\d+-/', $phone)) {
                    return;
                }

                $original = str_starts_with($phone, Customer::DELETED_PHONE_PREFIX)
                    ? Customer::originalPhoneFromArchived($phone)
                    : $phone;

                if ($original === null || $original === '') {
                    return;
                }

                DB::table('customers')
                    ->where('id', $row->id)
                    ->update(['phone' => Customer::formatArchivedPhone((int) $row->id, $original)]);
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
                if ($phone === '' || ! preg_match('/^del-(\d+)-(.+)$/', $phone, $matches)) {
                    return;
                }

                DB::table('customers')
                    ->where('id', $row->id)
                    ->update(['phone' => Customer::DELETED_PHONE_PREFIX.$matches[2]]);
            });
    }
};
