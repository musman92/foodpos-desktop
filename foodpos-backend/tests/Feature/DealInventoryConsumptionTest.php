<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Listeners\ProcessOrderCreated;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Deal;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MenuItem;
use App\Models\MenuItemStock;
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

class DealInventoryConsumptionTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private const RECIPE_QTY_G = 150;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_selling_a_deal_deducts_component_stock_and_shows_consumption(): void
    {
        [$ingredient, $recipeItem] = $this->createRecipeMenuItem();
        $drink = $this->createSingleMenuItem('Cola');

        $this->actingAsCompanyAdmin();
        app(InventoryService::class)->adjustIngredientStockManually(
            $this->tenantBranch->id,
            $ingredient->id,
            2000,
            $this->companyAdmin->id,
            'Test seed stock'
        );

        MenuItemStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'menu_item_id' => $drink->id,
            'quantity' => 10,
            'unit_price' => 50,
            'expiry_date' => null,
            'last_restocked_at' => now(),
        ]);

        $deal = Deal::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'title' => 'Burger Combo',
            'price' => 700,
            'is_active' => true,
        ]);
        $deal->menuItems()->attach([
            $recipeItem->id => ['quantity' => 1, 'unit_price' => 500],
            $drink->id => ['quantity' => 2, 'unit_price' => 100],
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'DEAL-'.uniqid(),
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => 700,
            'tax_amount' => 0,
            'total_amount' => 700,
            'paid_amount' => 700,
            'paid_at_sale' => 700,
            'completed_at' => now(),
            'business_date' => local_today($this->tenantBranch->id),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'deal_id' => $deal->id,
            'menu_item_id' => null,
            'item_name' => $deal->title,
            'quantity' => 1,
            'unit_price' => 700,
            'total_price' => 700,
            'status' => 'pending',
        ]);

        app(ProcessOrderCreated::class)->handle(new OrderCreated($order->fresh([
            'items.deal.menuItems.defaultRecipe.items.ingredient',
        ])));

        $this->assertSame(
            2000.0 - self::RECIPE_QTY_G,
            (float) BranchStock::withoutGlobalScopes()
                ->where('branch_id', $this->tenantBranch->id)
                ->where('ingredient_id', $ingredient->id)
                ->value('quantity')
        );

        $this->assertSame(
            8.0,
            (float) MenuItemStock::withoutGlobalScopes()
                ->where('branch_id', $this->tenantBranch->id)
                ->where('menu_item_id', $drink->id)
                ->sum('quantity')
        );

        $ingredientMovements = StockMovement::withoutGlobalScopes()
            ->where('ingredient_id', $ingredient->id)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->where('branch_id', $this->tenantBranch->id)
            ->get();
        $this->assertGreaterThan(0, $ingredientMovements->count());
        $this->assertEquals(self::RECIPE_QTY_G, (float) $ingredientMovements->sum('quantity'));

        $drinkMovements = StockMovement::withoutGlobalScopes()
            ->where('menu_item_id', $drink->id)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->where('branch_id', $this->tenantBranch->id)
            ->get();
        $this->assertGreaterThan(0, $drinkMovements->count());
        $this->assertEquals(2.0, (float) $drinkMovements->sum('quantity'));

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

    public function test_deal_availability_blocks_when_component_stock_is_short(): void
    {
        $this->actingAsCompanyAdmin();

        [, $recipeItem] = $this->createRecipeMenuItem();
        $deal = Deal::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'title' => 'Short Stock Combo',
            'price' => 500,
            'is_active' => true,
        ]);
        $deal->menuItems()->attach([
            $recipeItem->id => ['quantity' => 1, 'unit_price' => 500],
        ]);

        $deal = Deal::withoutGlobalScopes()
            ->with([
                'menuItems.defaultRecipe.items.ingredient',
                'menuItems.variantRecipes.recipe.items.ingredient',
                'menuItems.legacyRecipeLines.ingredient',
            ])
            ->findOrFail($deal->id);

        // No ingredient stock seeded.
        $availability = app(InventoryService::class)->checkDealAvailability(
            $deal,
            1,
            (int) $this->tenantBranch->id
        );

        $this->assertFalse($availability['can_sell']);
        $this->assertStringContainsString('Deal', (string) $availability['error_message']);
    }

    public function test_refund_restocks_deal_single_component(): void
    {
        $drink = $this->createSingleMenuItem('Water');
        MenuItemStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'menu_item_id' => $drink->id,
            'quantity' => 5,
            'unit_price' => 40,
            'expiry_date' => null,
            'last_restocked_at' => now(),
        ]);

        $deal = Deal::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'title' => 'Drink Deal',
            'price' => 80,
            'is_active' => true,
        ]);
        $deal->menuItems()->attach([
            $drink->id => ['quantity' => 2, 'unit_price' => 40],
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'DEAL-R-'.uniqid(),
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => 80,
            'total_amount' => 80,
            'paid_amount' => 80,
            'completed_at' => now(),
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'deal_id' => $deal->id,
            'menu_item_id' => null,
            'item_name' => $deal->title,
            'quantity' => 1,
            'unit_price' => 80,
            'total_price' => 80,
            'status' => 'pending',
        ]);

        $this->actingAsCompanyAdmin();
        app(ProcessOrderCreated::class)->handle(new OrderCreated($order->fresh([
            'items.deal.menuItems',
        ])));

        $this->assertEquals(3.0, (float) MenuItemStock::withoutGlobalScopes()
            ->where('menu_item_id', $drink->id)
            ->sum('quantity'));

        app(InventoryService::class)->restockOrderItemForRefund(
            $orderItem->fresh('deal.menuItems'),
            1,
            (int) $this->tenantBranch->id,
            (int) $this->companyAdmin->id,
            1,
            (string) $order->order_number
        );

        $this->assertEquals(5.0, (float) MenuItemStock::withoutGlobalScopes()
            ->where('menu_item_id', $drink->id)
            ->sum('quantity'));
    }

    /**
     * @return array{0: Ingredient, 1: MenuItem}
     */
    private function createRecipeMenuItem(): array
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
            'track_inventory' => true,
            'sort_order' => 1,
        ]);

        return [$ingredient, $menuItem];
    }

    private function createSingleMenuItem(string $name): MenuItem
    {
        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Drinks',
            'code' => 'DRK',
            'slug' => 'drinks-'.uniqid(),
            'sort_order' => 2,
            'is_active' => true,
        ]);

        return MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'single',
            'name' => $name,
            'slug' => strtolower($name).'-'.uniqid(),
            'sku' => 'DRK-'.uniqid(),
            'price' => 100,
            'cost' => 40,
            'is_available' => true,
            'track_inventory' => true,
            'sort_order' => 1,
        ]);
    }
}
