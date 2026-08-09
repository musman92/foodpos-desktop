<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->onDelete('set null');
            $table->string('phone')->nullable()->after('password');
            $table->enum('type', ['super_admin', 'company_admin', 'branch_manager', 'staff'])->default('staff')->after('phone');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('type');
            $table->softDeletes()->after('updated_at');

            $table->index('company_id');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['company_id']);
            $table->dropIndex(['branch_id']);
            $table->dropColumn(['company_id', 'branch_id', 'phone', 'type', 'status', 'deleted_at']);
        });
    }
};

