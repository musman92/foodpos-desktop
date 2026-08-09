<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class PosIndexTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestTenant();
    }

    public function test_company_admin_can_load_pos_with_session_branch(): void
    {
        $this->openTenantShift();

        $response = $this->actingAsCompanyAdmin()->get(route('pos.index'));

        $response->assertOk();
        $response->assertViewHas('branchTimezonesJson', function (array $map): bool {
            return isset($map[$this->tenantBranch->id]) && $map[$this->tenantBranch->id] === 'Asia/Karachi';
        });
        $response->assertViewHas('companyTimezone', 'Asia/Karachi');
    }

    public function test_staff_with_allocated_branch_can_load_pos(): void
    {
        $staff = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => true,
        ]);
        $staff->branches()->attach($this->tenantBranch->id, ['is_primary' => true]);

        $this->openTenantShift($staff);

        $response = $this->actingAs($staff)->get(route('pos.index'));

        $response->assertOk();
        $response->assertViewHas('branches', function ($branches): bool {
            return $branches->count() === 1
                && (int) $branches->first()->id === $this->tenantBranch->id;
        });
    }

    public function test_super_admin_can_load_pos_for_selected_branch(): void
    {
        $superAdmin = User::factory()->create([
            'company_id' => null,
            'branch_id' => null,
            'type' => 'super_admin',
            'status' => 'active',
            'can_login' => true,
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('pos.index', ['branch_id' => $this->tenantBranch->id]));

        $response->assertOk();
        $response->assertViewHas('selectedBranchId', $this->tenantBranch->id);
        $response->assertViewHas('branchTimezonesJson');
    }

    public function test_pos_redirects_when_no_active_shift(): void
    {
        $response = $this->actingAsCompanyAdmin()->get(route('pos.index'));

        $response->assertRedirect(route('shifts.create', ['branch_id' => $this->tenantBranch->id]));
    }
}
