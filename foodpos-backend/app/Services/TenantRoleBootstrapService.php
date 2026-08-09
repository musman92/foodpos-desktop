<?php

namespace App\Services;

use App\Helpers\AppPermissions;
use App\Helpers\TenantDefaultRoles;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantRoleBootstrapService
{
    /**
     * Ensure every canonical permission exists globally (Spatie permissions are not team-scoped).
     */
    public function syncGlobalPermissions(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = config('auth.defaults.guard');

        foreach (AppPermissions::all() as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => $guard]
            );
        }
    }

    /**
     * Ensure default tenant roles exist and their permissions match the application catalog.
     * Does not assign roles to any user.
     */
    public function syncDefaultRolesForCompany(Company $company): Role
    {
        $this->syncGlobalPermissions();

        $guard = config('auth.defaults.guard');
        setPermissionsTeamId($company->id);

        $administrator = Role::firstOrCreate(
            [
                'name' => TenantDefaultRoles::ADMINISTRATOR,
                'guard_name' => $guard,
                'company_id' => $company->id,
            ]
        );
        $administrator->syncPermissions(AppPermissions::tenantScoped());

        $manager = Role::firstOrCreate(
            [
                'name' => TenantDefaultRoles::MANAGER,
                'guard_name' => $guard,
                'company_id' => $company->id,
            ]
        );
        $manager->syncPermissions($this->managerPermissions());

        $cashier = Role::firstOrCreate(
            [
                'name' => TenantDefaultRoles::CASHIER,
                'guard_name' => $guard,
                'company_id' => $company->id,
            ]
        );
        $cashier->syncPermissions($this->cashierPermissions());

        $orderTaker = Role::firstOrCreate(
            [
                'name' => TenantDefaultRoles::ORDER_TAKER,
                'guard_name' => $guard,
                'company_id' => $company->id,
            ]
        );
        $orderTaker->syncPermissions($this->orderTakerPermissions());

        return $administrator;
    }

    /**
     * Create default tenant roles, attach permissions, and assign the Administrator role to the company admin.
     */
    public function bootstrapNewCompany(Company $company, User $companyAdmin): void
    {
        DB::transaction(function () use ($company, $companyAdmin) {
            $administrator = $this->syncDefaultRolesForCompany($company);

            setPermissionsTeamId($company->id);
            if (! $companyAdmin->hasRole(TenantDefaultRoles::ADMINISTRATOR)) {
                $companyAdmin->assignRole($administrator);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function managerPermissions(): array
    {
        return array_values(array_filter(
            AppPermissions::tenantScoped(),
            static fn (string $p) => ! in_array($p, ['roles.destroy', 'users.destroy'], true)
        ));
    }

    /**
     * @return list<string>
     */
    private function cashierPermissions(): array
    {
        return array_values(array_unique(array_merge(
            // FOC is assigned to Administrator / Manager by default, not front-of-house cashiers.
            array_values(array_filter(
                AppPermissions::forModule('pos'),
                static fn (string $p) => $p !== 'pos.foc'
            )),
            [
                'dashboard.index',
                'dashboard.today-stats',
                'dashboard.period-stats',
                'dashboard.order-types',
                'dashboard.top-items',
                'orders.index',
                'orders.store',
                'orders.update',
                'customers.index',
                'customers.store',
                'branches.index',
                'menu-items.index',
                'categories.index',
                'shifts.index',
                'shifts.store',
                'shifts.update',
            ]
        )));
    }

    /**
     * Order taker: POS + orders + order-management (no refunds), same shift rules as cashier for gated POS routes.
     *
     * @return list<string>
     */
    private function orderTakerPermissions(): array
    {
        return array_values(array_unique(array_merge(
            array_values(array_filter(
                AppPermissions::forModule('pos'),
                static fn (string $p) => $p !== 'pos.foc'
            )),
            [
                'dashboard.index',
                'dashboard.today-stats',
                'dashboard.order-types',
                'dashboard.top-items',
                'orders.index',
                'orders.store',
                'orders.update',
                'customers.index',
                'customers.store',
                'branches.index',
                'menu-items.index',
                'categories.index',
                'shifts.index',
                'shifts.store',
                'shifts.update',
                'order-management.index',
                'order-management.show',
                'order-management.append-note',
            ]
        )));
    }
}
