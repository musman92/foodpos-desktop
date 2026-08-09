<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $types = [
        'super_admin',
        'company_admin',
        'branch_manager',
        'staff',
        'waiter',
        'rider',
        'waiter_rider',
    ];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN type ENUM(
                'super_admin',
                'company_admin',
                'branch_manager',
                'staff',
                'waiter',
                'rider',
                'waiter_rider'
            ) NOT NULL DEFAULT 'staff'");

            return;
        }

        if ($driver === 'sqlite') {
            // SQLite ENUM is a CHECK constraint; rebuild column so new values are allowed.
            Schema::table('users', function (Blueprint $table) {
                $table->string('type_new', 32)->default('staff');
            });

            DB::table('users')->update([
                'type_new' => DB::raw('type'),
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('type');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('type', $this->types)->default('staff');
            });

            DB::table('users')->update([
                'type' => DB::raw('type_new'),
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('type_new');
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        DB::table('users')
            ->whereIn('type', ['waiter', 'rider', 'waiter_rider'])
            ->update(['type' => 'staff']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN type ENUM(
                'super_admin',
                'company_admin',
                'branch_manager',
                'staff'
            ) NOT NULL DEFAULT 'staff'");

            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('type_new', 32)->default('staff');
            });

            DB::table('users')->update([
                'type_new' => DB::raw('type'),
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('type');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('type', [
                    'super_admin',
                    'company_admin',
                    'branch_manager',
                    'staff',
                ])->default('staff');
            });

            DB::table('users')->update([
                'type' => DB::raw('type_new'),
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('type_new');
            });
        }
    }
};
