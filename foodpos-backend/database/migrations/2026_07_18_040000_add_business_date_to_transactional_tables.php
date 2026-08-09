<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'orders',
        'transactions',
        'purchases',
        'money_source_fund_movements',
        'stock_movements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'business_date')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->date('business_date')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'business_date')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['business_date']);
                $blueprint->dropColumn('business_date');
            });
        }
    }
};
