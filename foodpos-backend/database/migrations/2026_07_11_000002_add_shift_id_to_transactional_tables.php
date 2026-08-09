<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('cashier_id')->constrained()->nullOnDelete();
            $table->index('shift_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
            $table->index('shift_id');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
            $table->index('shift_id');
        });

        if (Schema::hasTable('money_source_fund_movements')) {
            Schema::table('money_source_fund_movements', function (Blueprint $table) {
                $table->foreignId('shift_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
                $table->index('shift_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('money_source_fund_movements')) {
            Schema::table('money_source_fund_movements', function (Blueprint $table) {
                $table->dropConstrainedForeignId('shift_id');
            });
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
