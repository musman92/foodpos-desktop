<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('customers', ['company_id', 'phone'], 'unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unique(['company_id', 'phone']);
            });
        }

        if (! Schema::hasIndex('customers', ['company_id', 'code'], 'unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unique(['company_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('customers', ['company_id', 'phone'], 'unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'phone']);
            });
        }

        if (Schema::hasIndex('customers', ['company_id', 'code'], 'unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'code']);
            });
        }
    }
};
