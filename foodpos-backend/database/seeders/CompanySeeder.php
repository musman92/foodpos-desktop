<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\TenantRoleBootstrapService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@foodpos.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('12345678'),
                'type' => 'super_admin',
                'status' => 'active',
            ]
        );

        // Create Sample Company
        $company = Company::firstOrCreate(
            ['slug' => 'demo-restaurant'],
            [
                'name' => 'Demo Restaurant Chain',
                'email' => 'info@demorestaurant.com',
                'phone' => '+1234567890',
                'address' => '123 Main Street, City, State 12345',
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'status' => 'active',
                'subscription_expires_at' => now()->addYear(),
            ]
        );

        // Create default "Walk In" customer for the company
        Customer::withoutTenantScope()->firstOrCreate(
            ['company_id' => $company->id, 'is_default' => true],
            [
                'name' => 'Walk In',
                'email' => null,
                'phone' => null,
                'is_active' => true,
            ]
        );

        // Create Branch (use withoutTenantScope since we're in seeder)
        $branch = Branch::withoutTenantScope()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DT001'],
            [
                'name' => 'Downtown Branch',
                'email' => 'downtown@demorestaurant.com',
                'phone' => '+1234567891',
                'address' => '456 Downtown Ave, City, State 12345',
                'timezone' => 'America/New_York',
                'status' => 'active',
            ]
        );

        // Create Company Admin
        $companyAdmin = User::firstOrCreate(
            ['email' => 'admin@demorestaurant.com'],
            [
                'company_id' => $company->id,
                'name' => 'Company Admin',
                'password' => Hash::make('password'),
                'type' => 'company_admin',
                'status' => 'active',
            ]
        );

        // Create Branch Manager
        $branchManager = User::firstOrCreate(
            ['email' => 'manager@demorestaurant.com'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Branch Manager',
                'password' => Hash::make('password'),
                'type' => 'branch_manager',
                'status' => 'active',
            ]
        );

        // Create Cashier
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@demorestaurant.com'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Cashier User',
                'password' => Hash::make('password'),
                'type' => 'staff',
                'status' => 'active',
            ]
        );

        $this->command->info('Created Super Admin: admin@foodpos.com / password');
        $this->command->info('Created Company Admin: admin@demorestaurant.com / password');
        $this->command->info('Created Branch Manager: manager@demorestaurant.com / password');
        $this->command->info('Created Cashier: cashier@demorestaurant.com / password');

        app(TenantRoleBootstrapService::class)->bootstrapNewCompany($company, $companyAdmin);

        setPermissionsTeamId($company->id);
        $branchManager->syncRoles(['Manager']);
        $cashier->syncRoles(['Cashier']);
    }
}
