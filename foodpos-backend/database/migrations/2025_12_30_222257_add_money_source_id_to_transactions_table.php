<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('money_source_id')->nullable()->after('payment_method')->constrained('money_sources')->onDelete('set null');
            $table->index('money_source_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['money_source_id']);
            $table->dropIndex(['money_source_id']);
            $table->dropColumn('money_source_id');
        });
    }
};
