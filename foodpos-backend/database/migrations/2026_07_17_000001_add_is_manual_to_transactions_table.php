<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('shift_id')->index();
        });

        // Existing unreferenced entries were created directly from the Transactions screen.
        DB::table('transactions')
            ->whereNull('reference_type')
            ->update(['is_manual' => true]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['is_manual']);
            $table->dropColumn('is_manual');
        });
    }
};
