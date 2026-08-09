<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\User;
use App\Services\TenantRoleBootstrapService;
use App\Services\TenantTransactionalResetService;
use App\Support\TenantTransactionalResetOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class TenantTransactionalResetTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();

        $this->superAdmin = User::factory()->create([
            'company_id' => null,
            'type' => 'super_admin',
            'status' => 'active',
            'can_login' => true,
        ]);
    }

    public function test_super_admin_can_reset_orders_and_customers_while_keeping_catalog(): void
    {
        $ingredient = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Flour',
            'sku' => 'FL01',
            'base_unit_id' => 'g',
            'is_active' => true,
        ]);

        $recipe = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Dough',
            'code' => 'R01',
            'is_active' => true,
        ]);

        $category = \App\Models\Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Mains',
            'code' => 'MAIN',
            'slug' => 'mains',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'recipe',
            'name' => 'Pizza',
            'slug' => 'pizza',
            'price' => 10,
            'cost' => 0,
            'default_recipe_id' => $recipe->id,
            'is_available' => true,
        ]);

        Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'ORD-1',
            'status' => 'completed',
            'order_type' => 'dine_in',
            'subtotal' => 10,
            'total_amount' => 10,
            'payment_status' => 'paid',
        ]);

        Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Test Customer',
            'is_active' => true,
        ]);

        $summary = app(TenantTransactionalResetService::class)->reset(
            $this->tenantCompany,
            [
                TenantTransactionalResetOptions::ORDERS,
                TenantTransactionalResetOptions::CUSTOMERS,
            ]
        );

        $this->assertSame(1, $summary['orders']);
        $this->assertSame(2, $summary['customers']);

        $this->assertSame(0, Order::withoutGlobalScopes()->where('company_id', $this->tenantCompany->id)->count());
        $this->assertSame(1, Ingredient::withoutGlobalScopes()->where('company_id', $this->tenantCompany->id)->count());
        $this->assertSame(1, Recipe::withoutGlobalScopes()->where('company_id', $this->tenantCompany->id)->count());
        $this->assertSame(1, MenuItem::withoutGlobalScopes()->where('company_id', $this->tenantCompany->id)->count());

        $this->assertTrue(
            Customer::withoutTenantScope()
                ->where('company_id', $this->tenantCompany->id)
                ->where('is_default', true)
                ->exists()
        );
    }

    public function test_reset_endpoint_requires_reset_confirmation(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('companies.reset-transactional-data', $this->tenantCompany), [
                'options' => [TenantTransactionalResetOptions::ORDERS],
                'confirm_reset' => 'NOPE',
            ])
            ->assertSessionHasErrors('confirm_reset');
    }

    public function test_reset_can_zero_money_source_opening_balances(): void
    {
        $cash = \App\Models\MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash Drawer',
            'type' => 'CASH',
            'opening_balance' => 25000,
            'active' => true,
        ]);
        $cash->branches()->attach($this->tenantBranch->id);

        $summary = app(TenantTransactionalResetService::class)->reset(
            $this->tenantCompany,
            [TenantTransactionalResetOptions::MONEY_SOURCES]
        );

        $this->assertSame(1, $summary['money_sources_reset']);
        $this->assertSame(0.0, (float) $cash->fresh()->opening_balance);
    }

    public function test_reset_does_not_affect_other_tenant(): void
    {
        $otherCompany = Company::create([
            'name' => 'Other Cafe',
            'slug' => 'other-'.Str::random(8),
            'email' => 'other-'.Str::random(8).'@example.com',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $otherBranch = \App\Models\Branch::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Branch',
            'code' => 'OB01',
            'timezone' => 'Asia/Karachi',
            'status' => 'active',
        ]);

        $otherAdmin = User::factory()->create([
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'type' => 'company_admin',
            'status' => 'active',
            'can_login' => true,
        ]);

        app(TenantRoleBootstrapService::class)->bootstrapNewCompany($otherCompany, $otherAdmin);

        Order::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'cashier_id' => $otherAdmin->id,
            'order_number' => 'ORD-OTHER',
            'status' => 'completed',
            'order_type' => 'dine_in',
            'subtotal' => 5,
            'total_amount' => 5,
            'payment_status' => 'paid',
        ]);

        app(TenantTransactionalResetService::class)->reset(
            $this->tenantCompany,
            [TenantTransactionalResetOptions::ORDERS]
        );

        $this->assertSame(1, Order::withoutGlobalScopes()->where('company_id', $otherCompany->id)->count());
    }
}
