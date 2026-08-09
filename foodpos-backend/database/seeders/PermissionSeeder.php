<?php

namespace Database\Seeders;

use App\Services\TenantRoleBootstrapService;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed canonical permissions (global). See App\Helpers\AppPermissions.
     */
    public function run(): void
    {
        app(TenantRoleBootstrapService::class)->syncGlobalPermissions();
    }
}
