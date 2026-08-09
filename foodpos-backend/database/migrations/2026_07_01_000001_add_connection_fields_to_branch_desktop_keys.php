<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_desktop_keys', function (Blueprint $table) {
            $table->string('connection_code', 6)->nullable()->after('last_used_at');
            $table->timestamp('last_heartbeat_at')->nullable()->after('connection_code');
            $table->json('system_printers')->nullable()->after('last_heartbeat_at');
            $table->timestamp('system_printers_at')->nullable()->after('system_printers');
        });
    }

    public function down(): void
    {
        Schema::table('branch_desktop_keys', function (Blueprint $table) {
            $table->dropColumn([
                'connection_code',
                'last_heartbeat_at',
                'system_printers',
                'system_printers_at',
            ]);
        });
    }
};
