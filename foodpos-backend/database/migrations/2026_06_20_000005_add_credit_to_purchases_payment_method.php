<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow supplier credit purchases to persist payment_method = credit.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE purchases MODIFY COLUMN payment_method ENUM('cash','transfer','card','online','credit') NOT NULL DEFAULT 'cash'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE purchases SET payment_method = 'cash' WHERE payment_method = 'credit'");
            DB::statement("ALTER TABLE purchases MODIFY COLUMN payment_method ENUM('cash','transfer','card','online') NOT NULL DEFAULT 'cash'");
        }
    }
};
