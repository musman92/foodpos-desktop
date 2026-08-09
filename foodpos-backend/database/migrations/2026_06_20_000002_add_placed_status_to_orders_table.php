<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('open','placed','pending','confirmed','preparing','ready','served','completed','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE orders SET status = 'open' WHERE status = 'placed'");
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('open','pending','confirmed','preparing','ready','served','completed','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
