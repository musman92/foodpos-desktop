<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('type', ['cashier', 'kitchen_staff'])
            ->update(['type' => 'staff']);
    }

    public function down(): void
    {
        // Cannot reliably restore previous invalid values.
    }
};
