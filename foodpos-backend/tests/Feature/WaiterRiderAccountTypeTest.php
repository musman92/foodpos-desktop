<?php

namespace Tests\Feature;

use App\Helpers\TenantDefaultRoles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class WaiterRiderAccountTypeTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_company_admin_can_create_login_waiter_with_role(): void
    {
        $response = $this->actingAsCompanyAdmin()->post(route('users.store'), [
            'name' => 'Floor Waiter',
            'email' => 'waiter-'.uniqid().'@example.com',
            'phone' => null,
            'can_login' => true,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'type' => 'waiter',
            'status' => 'active',
            'role' => TenantDefaultRoles::ORDER_TAKER,
            'branches' => [$this->tenantBranch->id],
            'primary_branch_id' => $this->tenantBranch->id,
        ]);

        $response->assertRedirect(route('users.index'));

        $waiter = User::query()->where('name', 'Floor Waiter')->first();
        $this->assertNotNull($waiter);
        $this->assertSame('waiter', $waiter->type);
        $this->assertTrue((bool) $waiter->can_login);
        $this->assertTrue($waiter->canServeAsWaiter());
        $this->assertFalse($waiter->canServeAsRider());
    }

    public function test_company_admin_can_create_waiter_rider_login_account(): void
    {
        $response = $this->actingAsCompanyAdmin()->post(route('users.store'), [
            'name' => 'Mixed Floor',
            'email' => 'both-'.uniqid().'@example.com',
            'can_login' => true,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'type' => 'waiter_rider',
            'status' => 'active',
            'role' => TenantDefaultRoles::ORDER_TAKER,
            'branches' => [$this->tenantBranch->id],
            'primary_branch_id' => $this->tenantBranch->id,
        ]);

        $response->assertRedirect(route('users.index'));

        $user = User::query()->where('name', 'Mixed Floor')->first();
        $this->assertNotNull($user);
        $this->assertSame('waiter_rider', $user->type);
        $this->assertTrue((bool) $user->can_login);
        $this->assertTrue($user->canServeAsWaiter());
        $this->assertTrue($user->canServeAsRider());
    }

    public function test_pos_branch_staff_includes_account_type_for_filtering(): void
    {
        User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'waiter',
            'status' => 'active',
            'can_login' => false,
            'name' => 'POS Waiter',
        ])->branches()->attach($this->tenantBranch->id, ['is_primary' => true]);

        User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'rider',
            'status' => 'active',
            'can_login' => false,
            'name' => 'POS Rider',
        ])->branches()->attach($this->tenantBranch->id, ['is_primary' => true]);

        $this->openTenantShift();

        $response = $this->actingAsCompanyAdmin()->get(route('pos.index'));
        $response->assertOk();
        $response->assertViewHas('branchStaffJson', function ($staff): bool {
            $types = collect($staff)->pluck('type', 'name');

            return ($types['POS Waiter'] ?? null) === 'waiter'
                && ($types['POS Rider'] ?? null) === 'rider';
        });
    }
}
