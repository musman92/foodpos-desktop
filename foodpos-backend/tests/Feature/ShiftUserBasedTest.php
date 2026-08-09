<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use App\Services\TenantRoleBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class ShiftUserBasedTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_two_users_can_have_active_shifts_on_same_branch(): void
    {
        $cashierB = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => true,
        ]);

        app(TenantRoleBootstrapService::class)->bootstrapNewCompany($this->tenantCompany, $cashierB);

        $service = app(ShiftService::class);
        $date = now()->toDateString();

        $shiftA = $service->startShift($this->tenantBranch->id, $this->companyAdmin->id, $date, []);
        $shiftB = $service->startShift($this->tenantBranch->id, $cashierB->id, $date, []);

        $this->assertNotSame($shiftA->id, $shiftB->id);
        $this->assertSame('active', $shiftA->fresh()->status);
        $this->assertSame('active', $shiftB->fresh()->status);
    }

    public function test_user_cannot_start_second_active_shift_on_same_branch(): void
    {
        $service = app(ShiftService::class);
        $service->startShift($this->tenantBranch->id, $this->companyAdmin->id, now()->toDateString(), []);

        try {
            $service->startShift($this->tenantBranch->id, $this->companyAdmin->id, now()->toDateString(), []);
            $this->fail('Expected an exception when starting a duplicate shift.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('already have an active shift', $e->getMessage());
        }
    }

    public function test_pos_order_is_stamped_with_cashiers_shift_via_http(): void
    {
        $shift = app(ShiftService::class)->startShift(
            $this->tenantBranch->id,
            $this->companyAdmin->id,
            now()->toDateString(),
            []
        );

        $category = \App\Models\Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Snacks',
            'code' => 'SNK',
            'slug' => 'snacks-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $menuItem = \App\Models\MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'single',
            'name' => 'Chips',
            'slug' => 'chips-'.uniqid(),
            'sku' => 'CHIPS-'.uniqid(),
            'price' => 50,
            'cost' => 10,
            'is_available' => true,
            'track_inventory' => false,
            'sort_order' => 1,
        ]);

        $cashSource = \App\Models\MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $cashSource->branches()->attach($this->tenantBranch->id);

        \App\Models\Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);

        $response = $this->actingAsCompanyAdmin()
            ->postJson(route('pos.store'), [
                'mode' => 'pay',
                'type' => 'takeaway',
                'branch_id' => $this->tenantBranch->id,
                'items' => [[
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'name' => $menuItem->name,
                    'quantity' => 1,
                    'unit_price' => 50,
                    'variants' => null,
                    'addons' => null,
                    'special_instructions' => '',
                ]],
                'subtotal' => 50,
                'tax_amount' => 0,
                'discount_type' => null,
                'discount_value' => null,
                'service_charge' => 0,
                'delivery_fee' => 0,
                'total_amount' => 50,
                'paid_amount' => 50,
                'money_source_id' => $cashSource->id,
                'payment_status' => 'paid',
                'notes' => null,
            ]);

        $response->assertOk();

        $order = Order::withoutGlobalScopes()->findOrFail($response->json('order.id'));
        $this->assertSame($shift->id, $order->shift_id);
        $this->assertSame($this->companyAdmin->id, $shift->opened_by);
    }

    public function test_middleware_requires_users_own_shift_not_branch_shift(): void
    {
        $otherCashier = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => true,
        ]);

        app(ShiftService::class)->startShift(
            $this->tenantBranch->id,
            $otherCashier->id,
            now()->toDateString(),
            []
        );

        $this->actingAs($this->companyAdmin)
            ->withSession(['current_branch_id' => $this->tenantBranch->id])
            ->get(route('pos.index'))
            ->assertRedirect(route('shifts.create', ['branch_id' => $this->tenantBranch->id]));
    }

    public function test_only_shift_owner_can_close_shift(): void
    {
        $shift = $this->openTenantShift();

        $otherCashier = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => true,
        ]);

        $this->actingAs($otherCashier)
            ->withSession(['current_branch_id' => $this->tenantBranch->id])
            ->get(route('shifts.edit', $shift))
            ->assertForbidden();
    }
}
