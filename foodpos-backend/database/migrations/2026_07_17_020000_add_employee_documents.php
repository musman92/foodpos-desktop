<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        Schema::table('employee_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_profiles', 'cnic_attachment_path')) {
                $table->string('cnic_attachment_path')->nullable()->after('national_id');
            }
            if (! Schema::hasColumn('employee_profiles', 'cnic_attachment_name')) {
                $table->string('cnic_attachment_name')->nullable()->after('cnic_attachment_path');
            }
            if (! Schema::hasColumn('employee_profiles', 'other_attachment_path')) {
                $table->string('other_attachment_path')->nullable()->after('cnic_attachment_name');
            }
            if (! Schema::hasColumn('employee_profiles', 'other_attachment_name')) {
                $table->string('other_attachment_name')->nullable()->after('other_attachment_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        Schema::table('employee_profiles', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('employee_profiles', 'cnic_attachment_path') ? 'cnic_attachment_path' : null,
                Schema::hasColumn('employee_profiles', 'cnic_attachment_name') ? 'cnic_attachment_name' : null,
                Schema::hasColumn('employee_profiles', 'other_attachment_path') ? 'other_attachment_path' : null,
                Schema::hasColumn('employee_profiles', 'other_attachment_name') ? 'other_attachment_name' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
