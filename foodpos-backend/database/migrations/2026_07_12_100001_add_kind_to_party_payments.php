<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->string('kind', 20)->default('collection')->after('payment_number');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->string('kind', 20)->default('payment')->after('payment_number');
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
