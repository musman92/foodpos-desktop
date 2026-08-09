<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `open` for unpaid POS tabs (dine-in / takeaway / delivery) before checkout.
     * MySQL enforces ENUM; SQLite stores as string and accepts new values without ALTER.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('open','pending','confirmed','preparing','ready','served','completed','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE orders SET status = 'pending' WHERE status = 'open'");
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','confirmed','preparing','ready','served','completed','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
