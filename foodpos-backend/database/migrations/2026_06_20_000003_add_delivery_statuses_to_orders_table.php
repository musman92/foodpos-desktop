<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
                'open',
                'placed',
                'pending',
                'confirmed',
                'preparing',
                'ready',
                'served',
                'out_for_delivery',
                'delivered',
                'completed',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE orders SET status = 'served' WHERE status IN ('out_for_delivery', 'delivered')");
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
                'open',
                'placed',
                'pending',
                'confirmed',
                'preparing',
                'ready',
                'served',
                'completed',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'");
        }
    }
};
