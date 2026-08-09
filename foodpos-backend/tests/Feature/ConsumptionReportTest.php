<?php

namespace Tests\Feature;

use App\Helpers\TenantDefaultRoles;
use App\Models\Category;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MenuItem;
use App\Models\MenuItemStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\TenantRoleBootstrapService;
use App\Support\ConsumptionReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class ConsumptionReportTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_consumption_report_requires_permission(): void
    {
        $cashier = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => true,
        ]);
        $cashier->branches()->attach($this->tenantBranch->id, ['is_primary' => true]);

        app(TenantRoleBootstrapService::class)->syncDefaultRolesForCompany($this->tenantCompany);
        setPermissionsTeamId($this->tenantCompany->id);
        $cashier->assignRole(TenantDefaultRoles::CASHIER);

        $this->actingAs($cashier)
            ->withSession(['current_branch_id' => $this->tenantBranch->id])
            ->get(route('reports.consumption'))
            ->assertForbidden();
    }

    public function test_consumption_report_includes_ingredients_menu_items_and_cost(): void
    {
        $ingredient = $this->createIngredient('Chicken', 'CHK01', 500);

        StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'sale',
            'movement' => 'out',
            'quantity' => 500,
            'unit_id' => 'g',
            'unit_cost' => 0.5,
            'notes' => 'Finalized for completed order #TEST-001',
            'created_at' => now(),
        ]);

        StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'adjustment',
            'movement' => 'out',
            'quantity' => 100,
            'unit_id' => 'g',
            'unit_cost' => 0.5,
            'notes' => 'Spoilage',
            'created_at' => now(),
        ]);

        $gramUom = UnitOfMeasure::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'abbreviation' => 'g',
            'type' => 'weight',
            'is_base_unit' => true,
        ]);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 2500,
            'unit_id' => $gramUom->id,
        ]);

        $category = $this->createCategory();

        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Coke',
            'slug' => 'coke',
            'sku' => 'MI01',
            'type' => 'single',
            'track_inventory' => true,
            'price' => 150,
            'cost' => 80,
            'is_active' => true,
        ]);

        MenuItemStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 42,
            'unit_price' => 80,
        ]);

        StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'menu_item_id' => $menuItem->id,
            'type' => 'sale',
            'movement' => 'out',
            'quantity' => 3,
            'unit_id' => 'pcs',
            'unit_cost' => 80,
            'notes' => 'Finalized for completed order #TEST-002',
            'created_at' => now(),
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $report = ConsumptionReport::build(
            $this->companyAdmin,
            $this->tenantBranch->id,
            $from,
            $to
        );

        $this->assertSame(540.0, $report['summary']['total_cost']);
        $this->assertSame(490.0, $report['summary']['sales_cost']);
        $this->assertSame(50.0, $report['summary']['adjustment_cost']);
        $this->assertSame(2, $report['summary']['item_count']);

        $chicken = $report['rows']->first(
            fn (array $row) => $row['item_type'] === 'ingredient' && $row['item_id'] === $ingredient->id
        );
        $coke = $report['rows']->first(
            fn (array $row) => $row['item_type'] === 'menu_item' && $row['item_id'] === $menuItem->id
        );
        $this->assertNotNull($chicken);
        $this->assertNotNull($coke);
        $this->assertSame(2.5, $chicken['remaining_stock']);
        $this->assertSame('Gram', $chicken['quantity_unit']);
        $this->assertSame('Kilogram', $chicken['remaining_stock_unit']);
        $this->assertSame(42.0, $coke['remaining_stock']);
        $this->assertSame('pcs', $coke['quantity_unit']);
        $this->assertSame('pcs', $coke['remaining_stock_unit']);

        $response = $this->actingAsCompanyAdmin()
            ->getJson(route('reports.panel', [
                'report' => 'consumption',
                'branch_id' => $this->tenantBranch->id,
                'from' => $from,
                'to' => $to,
            ]));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Remaining stock', $html);
        $this->assertStringNotContainsString('>Unit</th>', $html);
        $this->assertStringContainsString('Chicken', $html);
        $this->assertStringContainsString('Coke', $html);
        $this->assertStringContainsString('540.00', $html);
        $this->assertStringContainsString('Gram', $html);
        $this->assertStringContainsString('Kilogram', $html);
        $this->assertStringContainsString('2.50', $html);
        $this->assertStringContainsString('42.00', $html);

        $this->actingAsCompanyAdmin()
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Download PDF')
            ->assertSee('Export Excel')
            ->assertSee('Print');

        $pdf = $this->actingAsCompanyAdmin()
            ->get(route('reports.consumption.pdf', [
                'branch_id' => $this->tenantBranch->id,
                'from' => $from,
                'to' => $to,
            ]));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');

        $excel = $this->actingAsCompanyAdmin()
            ->get(route('reports.consumption.excel', [
                'branch_id' => $this->tenantBranch->id,
                'from' => $from,
                'to' => $to,
            ]));
        $excel->assertOk();
        $excel->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_consumption_filter_options_returns_categories_and_menu_items(): void
    {
        $category = $this->createCategory();
        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Biryani',
            'slug' => 'biryani',
            'sku' => 'BRY01',
            'type' => 'recipe',
            'track_inventory' => true,
            'price' => 500,
            'cost' => 200,
            'is_active' => true,
        ]);

        $response = $this->actingAsCompanyAdmin()
            ->getJson(route('reports.consumption.filter-options'));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $category->id]);
        $response->assertJsonFragment([
            'id' => $menuItem->id,
            'name' => 'Biryani',
            'category_id' => $category->id,
        ]);
    }

    public function test_consumption_report_filters_ingredients_by_menu_item_sales(): void
    {
        $category = $this->createCategory();
        $chicken = $this->createIngredient('Chicken', 'CHK-F1', 500);
        $rice = $this->createIngredient('Rice', 'RIC-F1', 200);

        $biryani = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Chicken Biryani',
            'slug' => 'chicken-biryani',
            'sku' => 'CB01',
            'type' => 'recipe',
            'track_inventory' => true,
            'price' => 500,
            'cost' => 200,
            'is_active' => true,
        ]);

        $otherItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Plain Rice',
            'slug' => 'plain-rice',
            'sku' => 'PR01',
            'type' => 'recipe',
            'track_inventory' => true,
            'price' => 200,
            'cost' => 50,
            'is_active' => true,
        ]);

        $biryaniOrder = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'CONS-BIR-001',
            'type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 500,
            'tax_amount' => 0,
            'total_amount' => 500,
            'paid_amount' => 500,
        ]);

        $biryaniLine = OrderItem::create([
            'order_id' => $biryaniOrder->id,
            'menu_item_id' => $biryani->id,
            'item_name' => 'Chicken Biryani',
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
        ]);

        $riceOrder = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'CONS-RIC-001',
            'type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 200,
            'tax_amount' => 0,
            'total_amount' => 200,
            'paid_amount' => 200,
        ]);

        $riceLine = OrderItem::create([
            'order_id' => $riceOrder->id,
            'menu_item_id' => $otherItem->id,
            'item_name' => 'Plain Rice',
            'quantity' => 1,
            'unit_price' => 200,
            'total_price' => 200,
        ]);

        StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $chicken->id,
            'type' => 'sale',
            'movement' => 'out',
            'quantity' => 250,
            'unit_id' => 'g',
            'unit_cost' => 0.5,
            'reference_type' => OrderItem::class,
            'reference_id' => $biryaniLine->id,
            'notes' => 'Finalized for completed order #CONS-BIR-001',
            'created_at' => now(),
        ]);

        StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $rice->id,
            'type' => 'sale',
            'movement' => 'out',
            'quantity' => 400,
            'unit_id' => 'g',
            'unit_cost' => 0.2,
            'reference_type' => OrderItem::class,
            'reference_id' => $riceLine->id,
            'notes' => 'Finalized for completed order #CONS-RIC-001',
            'created_at' => now(),
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $filtered = ConsumptionReport::build(
            $this->companyAdmin,
            $this->tenantBranch->id,
            $from,
            $to,
            '',
            null,
            (int) $biryani->id
        );

        $this->assertSame(1, $filtered['summary']['item_count']);
        $this->assertSame($chicken->id, $filtered['rows'][0]['item_id']);
        $this->assertSame('ingredient', $filtered['rows'][0]['item_type']);

        $byCategory = ConsumptionReport::build(
            $this->companyAdmin,
            $this->tenantBranch->id,
            $from,
            $to,
            '',
            (int) $category->id,
            null
        );

        $this->assertSame(2, $byCategory['summary']['item_count']);

        $filtered = $this->actingAsCompanyAdmin()
            ->getJson(route('reports.panel', [
                'report' => 'consumption',
                'branch_id' => $this->tenantBranch->id,
                'from' => $from,
                'to' => $to,
                'menu_item_id' => $biryani->id,
            ]));

        $filtered->assertOk();
        $html = $filtered->json('html');
        $this->assertStringContainsString('Chicken', $html);
        $this->assertStringNotContainsString('>Rice</', $html);
    }

    public function test_consumption_detail_lists_orders_and_adjustments_for_ingredient(): void
    {
        $ingredient = $this->createIngredient('Burger Bund', 'BB01', 39);
        $category = $this->createCategory();
        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Classic Burger',
            'slug' => 'classic-burger',
            'sku' => 'MI-BURGER',
            'type' => 'recipe',
            'track_inventory' => false,
            'price' => 500,
            'cost' => 100,
            'is_active' => true,
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'ORD-BUND-001',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 500,
            'total_amount' => 500,
            'paid_amount' => 500,
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'Classic Burger',
            'quantity' => 2,
            'unit_price' => 250,
            'total_price' => 500,
            'status' => 'served',
        ]);

        StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'sale',
            'movement' => 'out',
            'quantity' => 2,
            'unit_id' => 'g',
            'unit_cost' => 39,
            'reference_type' => OrderItem::class,
            'reference_id' => $orderItem->id,
            'notes' => 'Finalized for completed order #ORD-BUND-001',
            'created_at' => now(),
        ]);
        StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'adjustment',
            'movement' => 'out',
            'quantity' => 5,
            'unit_id' => 'g',
            'unit_cost' => 39,
            'notes' => 'Damaged pack',
            'created_by' => $this->companyAdmin->id,
            'created_at' => now(),
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $response = $this->actingAsCompanyAdmin()->get(route('reports.consumption.detail', [
            'itemType' => 'ingredient',
            'itemId' => $ingredient->id,
            'branch_id' => $this->tenantBranch->id,
            'from' => $from,
            'to' => $to,
        ]));

        $response->assertOk();
        $response->assertSee('Burger Bund');
        $response->assertSee('ORD-BUND-001');
        $response->assertSee('Classic Burger');
        $response->assertSee('Damaged pack');
        $response->assertSee(format_date($from).' – '.format_date($to));
        $response->assertSee(route('inventory.adjustment.show', StockMovement::withoutGlobalScopes()
            ->where('type', 'adjustment')
            ->where('ingredient_id', $ingredient->id)
            ->value('id')), false);
        $this->assertSame(1, $response->viewData('detail')['summary']['sales_order_count']);
        $this->assertSame(1, $response->viewData('detail')['summary']['adjustment_count']);
        $this->assertSame(2.0, $response->viewData('detail')['summary']['sales_quantity']);
        $this->assertSame(5.0, $response->viewData('detail')['summary']['adjustment_quantity']);
    }

    public function test_single_menu_item_sale_logs_stock_movement_with_cost(): void
    {
        $category = $this->createCategory();

        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Sprite',
            'slug' => 'sprite',
            'sku' => 'MI02',
            'type' => 'single',
            'track_inventory' => true,
            'price' => 150,
            'cost' => 70,
            'is_active' => true,
        ]);

        MenuItemStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 10,
            'unit_price' => 70,
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'TEST-SPRITE-001',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 300,
            'tax_amount' => 0,
            'total_amount' => 300,
            'paid_amount' => 300,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => 'Sprite',
            'quantity' => 2,
            'unit_price' => 150,
            'total_price' => 300,
            'status' => 'pending',
        ]);

        $this->actingAsCompanyAdmin();

        app(InventoryService::class)->finalizeInventoryDeduction($order->fresh(['items.menuItem']));

        $movement = StockMovement::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->first();

        $this->assertNotNull($movement);
        $this->assertSame(2.0, (float) $movement->quantity);
        $this->assertSame(70.0, (float) $movement->unit_cost);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $report = ConsumptionReport::build(
            $this->companyAdmin,
            $this->tenantBranch->id,
            $from,
            $to
        );

        $row = $report['rows']->firstWhere('name', 'Sprite');
        $this->assertNotNull($row);
        $this->assertSame(140.0, $row['total_cost']);
    }

    private function createCategory(): Category
    {
        return Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Drinks',
            'code' => 'DRK',
            'slug' => 'drinks',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createIngredient(string $name, string $sku, float $purchasePrice): Ingredient
    {
        $gramUnit = IngredientUnit::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $this->tenantCompany->id,
                'code' => 'g',
            ],
            ['name' => 'Gram']
        );

        $kgUnit = IngredientUnit::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $this->tenantCompany->id,
                'code' => 'kg',
            ],
            ['name' => 'Kilogram']
        );

        return Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => $name,
            'sku' => $sku,
            'base_unit_id' => 'g',
            'consumption_unit_id' => $gramUnit->id,
            'purchase_unit_id' => $kgUnit->id,
            'conversion_rate' => 1000,
            'purchase_price' => $purchasePrice,
            'cost_per_unit' => $purchasePrice / 1000,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);
    }
}
