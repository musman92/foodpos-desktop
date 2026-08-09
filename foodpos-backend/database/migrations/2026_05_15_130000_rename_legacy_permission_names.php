<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map old Spatie permission names (view/create/delete) to resource-style actions.
     */
    public function up(): void
    {
        $map = [
            'users.view' => 'users.index',
            'users.create' => 'users.store',
            'users.delete' => 'users.destroy',
            'roles.view' => 'roles.index',
            'roles.create' => 'roles.store',
            'roles.delete' => 'roles.destroy',
            'branches.view' => 'branches.index',
            'branches.create' => 'branches.store',
            'branches.delete' => 'branches.destroy',
            'customers.view' => 'customers.index',
            'customers.create' => 'customers.store',
            'customers.delete' => 'customers.destroy',
            'suppliers.view' => 'suppliers.index',
            'suppliers.create' => 'suppliers.store',
            'suppliers.delete' => 'suppliers.destroy',
            'menu-items.view' => 'menu-items.index',
            'menu-items.create' => 'menu-items.store',
            'menu-items.delete' => 'menu-items.destroy',
            'categories.view' => 'categories.index',
            'categories.create' => 'categories.store',
            'categories.delete' => 'categories.destroy',
            'orders.view' => 'orders.index',
            'orders.create' => 'orders.store',
            'orders.delete' => 'orders.destroy',
            'pos.access' => 'pos.index',
            'company-settings.view' => 'company-settings.index',
            'reports.view' => 'reports.index',
        ];

        foreach ($map as $from => $to) {
            if (! DB::table('permissions')->where('name', $from)->exists()) {
                continue;
            }
            if (DB::table('permissions')->where('name', $to)->exists()) {
                continue;
            }
            DB::table('permissions')->where('name', $from)->update(['name' => $to]);
        }
    }

    public function down(): void
    {
        $map = [
            'users.index' => 'users.view',
            'users.store' => 'users.create',
            'users.destroy' => 'users.delete',
            'roles.index' => 'roles.view',
            'roles.store' => 'roles.create',
            'roles.destroy' => 'roles.delete',
            'branches.index' => 'branches.view',
            'branches.store' => 'branches.create',
            'branches.destroy' => 'branches.delete',
            'customers.index' => 'customers.view',
            'customers.store' => 'customers.create',
            'customers.destroy' => 'customers.delete',
            'suppliers.index' => 'suppliers.view',
            'suppliers.store' => 'suppliers.create',
            'suppliers.destroy' => 'suppliers.delete',
            'menu-items.index' => 'menu-items.view',
            'menu-items.store' => 'menu-items.create',
            'menu-items.destroy' => 'menu-items.delete',
            'categories.index' => 'categories.view',
            'categories.store' => 'categories.create',
            'categories.destroy' => 'categories.delete',
            'orders.index' => 'orders.view',
            'orders.store' => 'orders.create',
            'orders.destroy' => 'orders.delete',
            'pos.index' => 'pos.access',
            'company-settings.index' => 'company-settings.view',
            'reports.index' => 'reports.view',
        ];

        foreach ($map as $from => $to) {
            if (! DB::table('permissions')->where('name', $from)->exists()) {
                continue;
            }
            if (DB::table('permissions')->where('name', $to)->exists()) {
                continue;
            }
            DB::table('permissions')->where('name', $from)->update(['name' => $to]);
        }
    }
};
