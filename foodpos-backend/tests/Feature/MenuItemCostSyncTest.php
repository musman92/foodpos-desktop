<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuItemStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Support\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class MenuItemCostSyncTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();
    }

    public function test_purchase_updates_single_menu_item_cost_to_weighted_average(): void
    {
        $this->actingAsCompanyAdmin();

        $menuItem = $this->createSingleMenuItem(cost: 95);

        $this->purchaseMenuItem($menuItem, quantity: 20, unitPrice: 100);
        $menuItem->refresh();
        $this->assertSame(100.0, (float) $menuItem->cost);

        $this->purchaseMenuItem($menuItem, quantity: 20, unitPrice: 110);
        $menuItem->refresh();
        $this->assertSame(105.0, (float) $menuItem->cost);
    }

    public function test_sale_updates_menu_item_cost_to_remaining_batch_average(): void
    {
        $this->actingAsCompanyAdmin();

        $menuItem = $this->createSingleMenuItem(cost: 95);

        $this->purchaseMenuItem($menuItem, quantity: 20, unitPrice: 100);
        $this->purchaseMenuItem($menuItem, quantity: 20, unitPrice: 110);

        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'TEST-COKE-001',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 3750,
            'tax_amount' => 0,
            'total_amount' => 3750,
            'paid_amount' => 3750,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'Coke',
            'quantity' => 25,
            'unit_price' => 150,
            'total_price' => 3750,
            'status' => 'pending',
        ]);

        app(InventoryService::class)->finalizeInventoryDeduction($order->fresh(['items.menuItem']));

        $menuItem->refresh();
        $this->assertSame(110.0, (float) $menuItem->cost);

        $remainingQty = (float) MenuItemStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('menu_item_id', $menuItem->id)
            ->sum('quantity');
        $this->assertSame(15.0, $remainingQty);
    }

    public function test_menu_item_low_stock_appears_on_dashboard(): void
    {
        $menuItem = $this->createSingleMenuItem(cost: 80, minStockLevel: 10);

        MenuItemStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 8,
            'unit_price' => 80,
        ]);

        $this->actingAsCompanyAdmin();

        $result = DashboardMetrics::lowStockItems($this->tenantBranch->id);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Coke', $result['rows']->first()['name']);
        $this->assertSame('menu_item', $result['rows']->first()['kind']);
        $this->assertSame(8.0, $result['rows']->first()['current']);
        $this->assertSame(10.0, $result['rows']->first()['min_level']);
    }

    private function createSingleMenuItem(float $cost = 0, float $minStockLevel = 0): MenuItem
    {
        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Drinks',
            'code' => 'DRK',
            'slug' => 'drinks-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Coke',
            'slug' => 'coke-'.uniqid(),
            'sku' => 'MI-COKE-'.uniqid(),
            'type' => 'single',
            'track_inventory' => true,
            'price' => 150,
            'cost' => $cost,
            'min_stock_level' => $minStockLevel,
            'is_available' => true,
        ]);
    }

    private function purchaseMenuItem(MenuItem $menuItem, float $quantity, float $unitPrice): void
    {
        $supplier = Supplier::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->tenantCompany->id, 'code' => 'SUP-COKE'],
            ['name' => 'Beverage Supplier', 'status' => 'active', 'balance' => 0]
        );

        app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => $quantity * $unitPrice,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $quantity * $unitPrice,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [[
                'item_type' => 'menu_item',
                'item_id' => $menuItem->id,
                'quantity' => $quantity,
                'unit_id' => 'pcs',
                'unit_price' => $unitPrice,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );
    }
}
