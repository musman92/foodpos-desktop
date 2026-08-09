<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('waiter_id')->nullable()->after('cashier_id')->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_rider_id')->nullable()->after('waiter_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_rider_id');
            $table->dropConstrainedForeignId('waiter_id');
        });
    }
};
