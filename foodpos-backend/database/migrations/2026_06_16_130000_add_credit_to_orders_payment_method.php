<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow POS credit sales to persist payment_method = credit on orders.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash','card','digital_wallet','split','credit') NULL");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE orders SET payment_method = NULL WHERE payment_method = 'credit'");
            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash','card','digital_wallet','split') NULL");
        }
    }
};
