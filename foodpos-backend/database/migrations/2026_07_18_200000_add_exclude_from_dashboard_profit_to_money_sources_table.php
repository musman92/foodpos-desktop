<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('money_sources', function (Blueprint $table) {
            $table->boolean('exclude_from_dashboard_profit')
                ->default(false)
                ->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('money_sources', function (Blueprint $table) {
            $table->dropColumn('exclude_from_dashboard_profit');
        });
    }
};
