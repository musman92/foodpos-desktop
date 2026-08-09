<?php

namespace Tests\Feature;

use App\Helpers\TenantDefaultRoles;
use App\Models\Account;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MenuItem;
use App\Models\MenuItemStock;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\UnitOfMeasure;
use App\Support\FocReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class PosFocCheckoutTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();

        Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
            'is_deletable' => false,
        ]);
    }

    public function test_foc_checkout_posts_to_foc_expense_account_not_sales(): void
    {
        $menuItem = $this->makeSimpleMenuItem(250);

        $response = $this->postFocSale($menuItem, qty: 2, unitPrice: 250);

        $response->assertOk()->assertJsonPath('success', true);

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('foc', $order->payment_method);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(0.0, (float) $order->paid_amount);
        $this->assertNull($order->money_source_id);

        $focAccount = Account::withoutTenantScope()
            ->where('company_id', $this->tenantCompany->id)
            ->where('name', 'FOC')
            ->where('type', 'expense')
            ->first();
        $this->assertNotNull($focAccount);
        $this->assertFalse((bool) $focAccount->is_deletable);

        $focTxn = Transaction::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('reference_type', 'expense')
            ->where('ref_id', $order->id)
            ->where('type', 'out')
            ->first();
        $this->assertNotNull($focTxn);
        $this->assertSame((int) $focAccount->id, (int) $focTxn->account_id);
        $this->assertSame(500.0, (float) $focTxn->amount);
        $this->assertNull($focTxn->money_source_id);
        $this->assertStringStartsWith('FOC Order #', (string) $focTxn->notes);

        $this->assertFalse(
            Transaction::withoutGlobalScopes()
                ->where('reference_type', 'sale')
                ->where('ref_id', $order->id)
                ->exists()
        );
    }

    public function test_foc_sale_consumes_recipe_ingredient_stock(): void
    {
        $recipeQtyGrams = 100.0;
        $saleQty = 2;
        $openingStock = 2000.0;

        $fixture = $this->makeRecipeSaleFixture($recipeQtyGrams, openingStock: $openingStock, unitPrice: 500);

        $this->assertSame($openingStock, $this->ingredientStock($fixture['ingredient']));

        $response = $this->postFocSale($fixture['menuItem'], qty: $saleQty, unitPrice: 500);
        $response->assertOk()->assertJsonPath('success', true);

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('foc', $order->payment_method);

        $expectedStock = $openingStock - ($recipeQtyGrams * $saleQty);
        $this->assertSame($expectedStock, $this->ingredientStock($fixture['ingredient']));

        $saleMovement = StockMovement::withoutGlobalScopes()
            ->where('ingredient_id', $fixture['ingredient']->id)
            ->where('branch_id', $this->tenantBranch->id)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->first();
        $this->assertNotNull($saleMovement, 'FOC order should create a sale stock movement');
        $this->assertSame($recipeQtyGrams * $saleQty, (float) $saleMovement->quantity);

        $this->assertTrue(
            Transaction::withoutGlobalScopes()
                ->where('reference_type', 'expense')
                ->where('ref_id', $order->id)
                ->where('notes', 'like', 'FOC Order #%')
                ->exists()
        );
        $this->assertFalse(
            Transaction::withoutGlobalScopes()
                ->where('reference_type', 'sale')
                ->where('ref_id', $order->id)
                ->exists()
        );
    }

    public function test_foc_sale_consumes_tracked_menu_item_stock(): void
    {
        $openingPcs = 10.0;
        $saleQty = 3;
        $menuItem = $this->makeTrackedSingleMenuItem(120, $openingPcs);

        $this->assertSame($openingPcs, $menuItem->totalStockAtBranch((int) $this->tenantBranch->id));

        $response = $this->postFocSale($menuItem, qty: $saleQty, unitPrice: 120);
        $response->assertOk()->assertJsonPath('success', true);

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('foc', $order->payment_method);

        $this->assertSame(
            $openingPcs - $saleQty,
            $menuItem->fresh()->totalStockAtBranch((int) $this->tenantBranch->id)
        );

        $movement = StockMovement::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('branch_id', $this->tenantBranch->id)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->first();
        $this->assertNotNull($movement, 'FOC order should create menu-item sale stock movement');
        $this->assertSame((float) $saleQty, (float) $movement->quantity);
    }

    public function test_foc_stock_and_expense_appear_on_foc_report(): void
    {
        $fixture = $this->makeRecipeSaleFixture(50, openingStock: 500, unitPrice: 200);

        $this->postFocSale($fixture['menuItem'], qty: 1, unitPrice: 200)
            ->assertOk();

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);

        $from = local_now((int) $this->tenantBranch->id)->copy()->subDay()->toDateString();
        $to = local_today((int) $this->tenantBranch->id);
        $report = FocReport::build($this->companyAdmin, (int) $this->tenantBranch->id, $from, $to);

        $this->assertSame(1, $report['summary']['order_count']);
        $this->assertSame(200.0, $report['summary']['total_value']);
        $this->assertSame($order->order_number, $report['rows'][0]['order_number']);
        $this->assertSame(450.0, $this->ingredientStock($fixture['ingredient']));
    }

    public function test_foc_cannot_mix_with_money_source(): void
    {
        $menuItem = $this->makeSimpleMenuItem(100);
        $cash = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $cash->branches()->attach($this->tenantBranch->id);

        $response = $this->actingAsCompanyAdmin()->postJson(route('pos.store'), [
            'mode' => 'pay',
            'type' => 'takeaway',
            'branch_id' => $this->tenantBranch->id,
            'items' => [[
                'menu_item_id' => $menuItem->id,
                'item_name' => $menuItem->name,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
            'payment_method' => 'foc',
            'money_source_id' => $cash->id,
            'paid_amount' => 0,
            'payment_status' => 'paid',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_foc_requires_permission(): void
    {
        setPermissionsTeamId($this->tenantCompany->id);
        $adminRole = Role::query()
            ->where('company_id', $this->tenantCompany->id)
            ->where('name', TenantDefaultRoles::ADMINISTRATOR)
            ->firstOrFail();
        $adminRole->revokePermissionTo('pos.foc');
        $this->companyAdmin->forgetCachedPermissions();

        $menuItem = $this->makeSimpleMenuItem(80);

        $response = $this->actingAsCompanyAdmin()->postJson(route('pos.store'), [
            'mode' => 'pay',
            'type' => 'takeaway',
            'branch_id' => $this->tenantBranch->id,
            'items' => [[
                'menu_item_id' => $menuItem->id,
                'item_name' => $menuItem->name,
                'quantity' => 1,
                'unit_price' => 80,
            ]],
            'subtotal' => 80,
            'tax_amount' => 0,
            'total_amount' => 80,
            'payment_method' => 'foc',
            'paid_amount' => 0,
            'payment_status' => 'paid',
        ]);

        $response->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_migration_helper_keeps_foc_account_undeletable(): void
    {
        $account = Account::ensureSystemAccount((int) $this->tenantCompany->id, 'FOC', 'expense');
        $this->assertFalse($account->canBeDeleted());
        $this->assertSame('expense', $account->type);
    }

    /**
     * @return \Illuminate\Testing\TestResponse
     */
    private function postFocSale(MenuItem $menuItem, int $qty, float $unitPrice)
    {
        $total = $qty * $unitPrice;

        return $this->actingAsCompanyAdmin()->postJson(route('pos.store'), [
            'mode' => 'pay',
            'type' => 'takeaway',
            'branch_id' => $this->tenantBranch->id,
            'items' => [[
                'menu_item_id' => $menuItem->id,
                'item_name' => $menuItem->name,
                'name' => $menuItem->name,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'variants' => null,
                'addons' => null,
                'special_instructions' => '',
            ]],
            'subtotal' => $total,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => $total,
            'payment_method' => 'foc',
            'paid_amount' => 0,
            'payment_status' => 'paid',
            'notes' => 'FOC test sale',
        ]);
    }

    private function makeSimpleMenuItem(float $price): MenuItem
    {
        $category = $this->makeCategory();

        return MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'FOC Item',
            'slug' => 'foc-item-'.Str::random(6),
            'sku' => 'FOC-'.Str::random(6),
            'price' => $price,
            'type' => 'single',
            'track_inventory' => false,
            'is_available' => true,
        ]);
    }

    private function makeTrackedSingleMenuItem(float $price, float $openingStock): MenuItem
    {
        $category = $this->makeCategory();

        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Bottled Water',
            'slug' => 'water-'.Str::random(6),
            'sku' => 'WTR-'.Str::random(6),
            'price' => $price,
            'cost' => 20,
            'type' => 'single',
            'track_inventory' => true,
            'is_available' => true,
        ]);

        MenuItemStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => $openingStock,
            'unit_price' => 20,
        ]);

        return $menuItem->fresh();
    }

    /**
     * @return array{ingredient: Ingredient, menuItem: MenuItem, recipe: Recipe}
     */
    private function makeRecipeSaleFixture(float $recipeQtyGrams, float $openingStock, float $unitPrice): array
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
            'name' => 'Patty',
            'sku' => 'PATTY-'.uniqid(),
            'base_unit_id' => 'g',
            'consumption_unit_id' => $gramUnit->id,
            'purchase_unit_id' => $kgUnit->id,
            'conversion_rate' => 1000,
            'purchase_price' => 2000,
            'cost_per_unit' => 2,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => $openingStock,
            'unit_id' => $this->ensureUomId($gramUnit),
            'average_cost' => 2,
        ]);

        $recipe = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'code' => 'R-'.uniqid(),
            'name' => 'Burger BOM',
            'is_active' => true,
        ]);
        $recipe->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => $recipeQtyGrams,
            'unit_id' => 'g',
            'waste_percentage' => 0,
        ]);

        $category = $this->makeCategory();
        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'FOC Burger',
            'slug' => 'foc-burger-'.uniqid(),
            'sku' => 'MI-FOC-'.uniqid(),
            'type' => 'recipe',
            'default_recipe_id' => $recipe->id,
            'price' => $unitPrice,
            'cost' => 0,
            'is_available' => true,
            'track_inventory' => true,
            'sort_order' => 1,
        ]);

        return [
            'ingredient' => $ingredient,
            'menuItem' => $menuItem->fresh(),
            'recipe' => $recipe,
        ];
    }

    private function makeCategory(): Category
    {
        return Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Test Cat',
            'slug' => 'test-cat-'.Str::random(6),
            'code' => 'TC',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function ingredientStock(Ingredient $ingredient): float
    {
        return (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->sum('quantity');
    }

    private function ensureUomId(IngredientUnit $unit): int
    {
        $uom = UnitOfMeasure::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $this->tenantCompany->id,
                'abbreviation' => $unit->code,
            ],
            [
                'name' => $unit->name,
                'type' => 'weight',
                'is_base_unit' => true,
            ]
        );

        return (int) $uom->id;
    }
}
