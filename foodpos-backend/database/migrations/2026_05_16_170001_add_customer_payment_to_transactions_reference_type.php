<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY COLUMN reference_type ENUM('sale', 'purchase', 'refund', 'expense', 'customer_payment') NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY COLUMN reference_type ENUM('sale', 'purchase', 'refund', 'expense') NULL");
    }
};
