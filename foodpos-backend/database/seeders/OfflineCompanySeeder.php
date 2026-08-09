<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\TenantRoleBootstrapService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the single local company + hidden default branch for offline edition.
 */
class OfflineCompanySeeder extends Seeder
{
    public function run(): void
    {
        $name = (string) config('offline.company_name');
        $slug = Str::slug($name) ?: 'local-restaurant';

        $company = Company::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'email' => config('offline.admin_email'),
                'currency' => 'PKR',
                'timezone' => 'Asia/Karachi',
                'status' => 'active',
                'subscription_expires_at' => now()->addYears(50),
            ]
        );

        $branch = Branch::withoutTenantScope()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'MAIN'],
            [
                'name' => config('offline.branch_name'),
                'timezone' => 'Asia/Karachi',
                'status' => 'active',
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => config('offline.admin_email')],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Administrator',
                'password' => Hash::make((string) config('offline.admin_password')),
                'type' => 'company_admin',
                'status' => 'active',
                'can_login' => true,
            ]
        );

        if (! $admin->branch_id) {
            $admin->forceFill([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
            ])->save();
        }

        $admin->branches()->syncWithoutDetaching([$branch->id]);

        app(TenantRoleBootstrapService::class)->bootstrapNewCompany($company, $admin);

        $this->command?->info("Offline company: {$company->name} (branch: {$branch->name})");
        $this->command?->info('Admin login: '.config('offline.admin_email').' / '.config('offline.admin_password'));
    }
}
