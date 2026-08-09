<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class DashboardRevenueCompletedOrdersTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->actingAsCompanyAdmin();
    }

    public function test_dashboard_revenue_excludes_open_unpaid_tabs(): void
    {
        $branchId = (int) $this->tenantBranch->id;
        $today = local_today($branchId);

        $this->createOrder([
            'status' => 'open',
            'payment_status' => 'unpaid',
            'total_amount' => 400,
            'paid_amount' => 0,
            'completed_at' => null,
        ]);

        $this->createOrder([
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 250,
            'paid_amount' => 250,
            'completed_at' => local_now($branchId),
        ]);

        $summary = DashboardMetrics::summaryForRange(
            $this->companyAdmin,
            $branchId,
            $today,
            $today
        );

        $this->assertSame(250.0, $summary['revenue']);
        $this->assertSame(1, $summary['transactions']);
        $this->assertSame(0.0, $summary['cost_of_goods']);

        $series = DashboardMetrics::dailyRevenueSeries(
            $this->companyAdmin,
            $branchId,
            $today,
            $today
        );

        $this->assertSame([250.0], $series['revenue']);
    }

    public function test_dashboard_cost_of_goods_uses_completed_order_item_costs(): void
    {
        $branchId = (int) $this->tenantBranch->id;
        $today = local_today($branchId);

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Drinks',
            'slug' => 'drinks-'.Str::random(5),
            'is_active' => true,
        ]);

        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Cola',
            'slug' => 'cola-'.Str::random(5),
            'sku' => 'COLA-'.Str::random(5),
            'type' => 'single',
            'price' => 100,
            'cost' => 40,
            'track_inventory' => false,
            'is_available' => true,
        ]);

        $completed = $this->createOrder([
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 200,
            'paid_amount' => 200,
            'subtotal' => 200,
            'completed_at' => local_now($branchId),
        ]);

        OrderItem::withoutGlobalScopes()->create([
            'order_id' => $completed->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'quantity' => 2,
            'unit_price' => 100,
            'total_price' => 200,
            'status' => 'served',
        ]);

        $open = $this->createOrder([
            'status' => 'open',
            'payment_status' => 'unpaid',
            'total_amount' => 100,
            'paid_amount' => 0,
            'subtotal' => 100,
            'completed_at' => null,
        ]);

        OrderItem::withoutGlobalScopes()->create([
            'order_id' => $open->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'quantity' => 1,
            'unit_price' => 100,
            'total_price' => 100,
            'status' => 'pending',
        ]);

        $summary = DashboardMetrics::summaryForRange(
            $this->companyAdmin,
            $branchId,
            $today,
            $today
        );

        $this->assertSame(200.0, $summary['revenue']);
        $this->assertSame(80.0, $summary['cost_of_goods']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOrder(array $overrides): Order
    {
        return Order::withoutGlobalScopes()->create(array_merge([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'D-'.Str::upper(Str::random(6)),
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => $overrides['total_amount'] ?? 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => 100,
            'paid_amount' => 100,
        ], $overrides));
    }
}
