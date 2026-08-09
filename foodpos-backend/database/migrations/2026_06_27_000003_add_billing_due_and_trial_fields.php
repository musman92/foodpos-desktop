<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->date('billing_due_date')->nullable()->after('billing_notes');
            $table->timestamp('trial_ends_at')->nullable()->after('billing_due_date');
            $table->date('billing_starts_at')->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['billing_due_date', 'trial_ends_at', 'billing_starts_at']);
        });
    }
};
