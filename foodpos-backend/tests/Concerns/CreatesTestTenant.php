<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Shift;
use App\Models\User;
use App\Services\TenantRoleBootstrapService;
use Illuminate\Support\Str;

trait CreatesTestTenant
{
    protected Company $tenantCompany;

    protected Branch $tenantBranch;

    protected User $companyAdmin;

    protected function setUpTestTenant(): void
    {
        $this->tenantCompany = Company::create([
            'name' => 'Test Cafe',
            'slug' => 'test-cafe-'.Str::random(8),
            'email' => 'cafe-'.Str::random(8).'@example.com',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $this->tenantBranch = Branch::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Main Branch',
            'code' => 'B01',
            'timezone' => 'Asia/Karachi',
            'status' => 'active',
        ]);

        $this->companyAdmin = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'company_admin',
            'status' => 'active',
            'can_login' => true,
        ]);

        app(TenantRoleBootstrapService::class)->bootstrapNewCompany(
            $this->tenantCompany,
            $this->companyAdmin
        );
    }

    protected function openTenantShift(?User $user = null): Shift
    {
        $user ??= $this->companyAdmin;

        return Shift::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'opened_by' => $user->id,
            'shift_date' => now()->toDateString(),
            'opened_at' => now(),
            'status' => 'active',
            'expected_cash' => 0,
            'cash_difference' => 0,
        ]);
    }

    protected function actingAsCompanyAdmin(): static
    {
        return $this->actingAs($this->companyAdmin)
            ->withSession(['current_branch_id' => $this->tenantBranch->id]);
    }
}
