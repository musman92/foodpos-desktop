<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_records')) {
            return;
        }

        if (! Schema::hasColumn('attendance_records', 'break_started_at')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dateTime('break_started_at')->nullable()->after('break_minutes');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_records')) {
            return;
        }

        if (Schema::hasColumn('attendance_records', 'break_started_at')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropColumn('break_started_at');
            });
        }
    }
};
