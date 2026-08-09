<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Listeners\ProcessOrderCreated;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Support\ConsumptionReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class OrderInventoryIdempotencyTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private const RECIPE_QTY_G = 150;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_order_created_listener_is_registered_once(): void
    {
        $listeners = app('events')->getListeners(OrderCreated::class);

        $this->assertCount(1, $listeners);
    }

    public function test_credit_order_deducts_ingredients_once_when_event_handled_twice(): void
    {
        [$ingredient, $menuItem] = $this->createRecipeMenuItemFixture();
        $this->actingAsCompanyAdmin();
        app(InventoryService::class)->adjustIngredientStockManually(
            $this->tenantBranch->id,
            $ingredient->id,
            2000,
            $this->companyAdmin->id,
            'Test seed stock'
        );

        $order = $this->createCreditOrder($menuItem);
        $listener = app(ProcessOrderCreated::class);

        $listener->handle(new OrderCreated($order->fresh([
            'items.menuItem.defaultRecipe.items.ingredient',
        ])));
        $listener->handle(new OrderCreated($order->fresh([
            'items.menuItem.defaultRecipe.items.ingredient',
        ])));

        $movements = StockMovement::withoutGlobalScopes()
            ->where('ingredient_id', $ingredient->id)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->where('branch_id', $this->tenantBranch->id)
            ->get();

        $this->assertCount(1, $movements);
        $this->assertSame((float) self::RECIPE_QTY_G, (float) $movements->first()->quantity);
        $this->assertStringContainsString('Finalized for completed order', (string) $movements->first()->notes);

        $this->assertSame(
            2000.0 - self::RECIPE_QTY_G,
            (float) BranchStock::withoutGlobalScopes()
                ->where('branch_id', $this->tenantBranch->id)
                ->where('ingredient_id', $ingredient->id)
                ->value('quantity')
        );

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $report = ConsumptionReport::build(
            $this->companyAdmin,
            $this->tenantBranch->id,
            $from,
            $to
        );

        $row = $report['rows']->firstWhere('name', $ingredient->name);
        $this->assertNotNull($row);
        $this->assertSame((float) self::RECIPE_QTY_G, $row['quantity']);
    }

    public function test_recipe_sale_skips_consumption_when_track_inventory_is_off(): void
    {
        [$ingredient, $menuItem] = $this->createRecipeMenuItemFixture(trackInventory: false);
        $this->assertFalse($menuItem->track_inventory);

        $this->actingAsCompanyAdmin();
        app(InventoryService::class)->adjustIngredientStockManually(
            $this->tenantBranch->id,
            $ingredient->id,
            2000,
            $this->companyAdmin->id,
            'Test seed stock'
        );

        $order = $this->createCreditOrder($menuItem);
        app(ProcessOrderCreated::class)->handle(new OrderCreated($order->fresh([
            'items.menuItem.defaultRecipe.items.ingredient',
        ])));

        $this->assertSame(
            0,
            StockMovement::withoutGlobalScopes()
                ->where('ingredient_id', $ingredient->id)
                ->where('type', 'sale')
                ->where('movement', 'out')
                ->where('branch_id', $this->tenantBranch->id)
                ->count()
        );
    }

    /**
     * @return array{0: Ingredient, 1: MenuItem}
     */
    private function createRecipeMenuItemFixture(bool $trackInventory = true): array
    {
        $gramUnit = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g-'.uniqid(),
        ]);

        $kgUnit = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Kilogram',
            'code' => 'kg-'.uniqid(),
        ]);

        $ingredient = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Beef Patty',
            'sku' => 'BEEF-'.uniqid(),
            'base_unit_id' => 'g',
            'consumption_unit_id' => $gramUnit->id,
            'purchase_unit_id' => $kgUnit->id,
            'conversion_rate' => 1000,
            'purchase_price' => 2000,
            'cost_per_unit' => 2,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);

        $recipe = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Burger BOM',
            'code' => 'R-'.uniqid(),
            'is_active' => true,
        ]);

        RecipeItem::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => self::RECIPE_QTY_G,
            'unit_id' => 'g',
            'waste_percentage' => 0,
        ]);

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Burgers',
            'code' => 'BRG',
            'slug' => 'burgers-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'recipe',
            'default_recipe_id' => $recipe->id,
            'name' => 'Classic Burger',
            'slug' => 'classic-burger-'.uniqid(),
            'sku' => 'MI-'.uniqid(),
            'price' => 500,
            'is_available' => true,
            'track_inventory' => $trackInventory,
            'sort_order' => 1,
        ]);

        return [$ingredient, $menuItem];
    }

    private function createCreditOrder(MenuItem $menuItem): Order
    {
        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'TEST-CREDIT-'.uniqid(),
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'partial',
            'payment_method' => 'credit',
            'subtotal' => 500,
            'tax_amount' => 0,
            'total_amount' => 500,
            'paid_amount' => 0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'status' => 'pending',
        ]);

        return $order;
    }
}
