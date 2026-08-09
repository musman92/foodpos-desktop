<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('billing_currency', 3)->nullable()->after('currency');
            $table->decimal('billing_amount', 12, 2)->nullable()->after('billing_currency');
            $table->string('billing_interval', 20)->nullable()->after('billing_amount');
            $table->boolean('billing_enabled')->default(false)->after('billing_interval');
            $table->text('billing_notes')->nullable()->after('billing_enabled');
        });

        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('total_amount');
            $table->string('billing_interval', 20)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->dropColumn(['currency', 'billing_interval']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'billing_currency',
                'billing_amount',
                'billing_interval',
                'billing_enabled',
                'billing_notes',
            ]);
        });
    }
};
