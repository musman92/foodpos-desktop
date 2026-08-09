<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preserve amount received at POS checkout for account statements.
     * Customer payments later increase paid_amount but must not change paid_at_sale.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('paid_at_sale', 10, 2)->default(0)->after('paid_amount');
        });

        DB::table('orders')->update(['paid_at_sale' => DB::raw('paid_amount')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('paid_at_sale');
        });
    }
};
