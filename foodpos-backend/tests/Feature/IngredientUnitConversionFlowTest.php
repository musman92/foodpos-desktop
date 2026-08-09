<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\IngredientQuantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * End-to-end conversion checks using real-style packaging units (C01–C15).
 * Each scenario: ingredient units → purchase → recipe menu item → POS sale → stock before/after.
 */
class IngredientUnitConversionFlowTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private MoneySource $cashSource;

    private const SALE_QTY = 2;

    private const MENU_ITEM_PRICE = 350.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();

        $this->cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 100000,
            'active' => true,
        ]);
        $this->cashSource->branches()->attach($this->tenantBranch->id);

        Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, array{
     *     purchaseUnit: array{code: string, name: string},
     *     consumptionUnit: array{code: string, name: string},
     *     conversionRate: float,
     *     purchaseQty: float,
     *     purchaseUnitPrice: float,
     *     recipeQty: float,
     *     recipeInPurchaseUnit: bool,
     *     saleQty: int,
     *     expectedStockAfterPurchase: float,
     *     expectedStockAfterSale: float
     * }>
     */
    public static function packagingUnitScenarios(): array
    {
        $cases = [
            'C01 10 Kg pack → grams' => self::weightCase('C01', '10 Kg', 10000, 100),
            'C02 10 Ltr → milliliters' => self::volumeCase('C02', '10 Ltr', 10000, 250),
            'C03 16 Kg Bag → grams' => self::weightCase('C03', '16 Kg Bag', 16000, 80),
            'C04 16 LTR Ten → milliliters' => self::volumeCase('C04', '16 LTR Ten', 16000, 200),
            'C05 2 Kg Pack → grams' => self::weightCase('C05', '2 Kg Pack', 2000, 150),
            'C06 2 Ltr Pack → milliliters' => self::volumeCase('C06', '2 Ltr Pack', 2000, 100),
            'C07 2.5 Kg Pack → grams' => self::weightCase('C07', '2.5 Kg Pack', 2500, 125),
            'C08 200 Gram pack → grams' => self::weightCase('C08', '200 Gram', 200, 20),
            'C09 200 ML pack → milliliters' => self::volumeCase('C09', '200 ML', 200, 30),
            'C10 25 Kg → grams' => self::weightCase('C10', '25 Kg', 25000, 200),
            'C11 3 Ltr Pack → milliliters' => self::volumeCase('C11', '3 Ltr Pack', 3000, 150),
            'C12 3.2 Ltr pack → milliliters' => self::volumeCase('C12', '3.2 Ltr', 3200, 160),
            'C13 3.5 Ltr pack → milliliters' => self::volumeCase('C13', '3.5 Ltr', 3500, 175),
            'C14 5 KG Bag → grams' => self::weightCase('C14', '5 KG Bag', 5000, 100),
            'C15 5 Ltr → milliliters' => self::volumeCase('C15', '5 Ltr', 5000, 125),
        ];

        $cases['C05 recipe in purchase unit (0.5 pack)'] = [
            'purchaseUnit' => ['code' => 'C05', 'name' => '2 Kg Pack'],
            'consumptionUnit' => ['code' => 'G', 'name' => 'Gram'],
            'conversionRate' => 2000,
            'purchaseQty' => 2,
            'purchaseUnitPrice' => 800,
            'recipeQty' => 0.5,
            'recipeInPurchaseUnit' => true,
            'saleQty' => 3,
            'expectedStockAfterPurchase' => 4000,
            'expectedStockAfterSale' => 1000,
        ];

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    private static function weightCase(string $code, string $name, float $conversionRate, float $recipeQty): array
    {
        return [
            'purchaseUnit' => ['code' => $code, 'name' => $name],
            'consumptionUnit' => ['code' => 'G', 'name' => 'Gram'],
            'conversionRate' => $conversionRate,
            'purchaseQty' => 1,
            'purchaseUnitPrice' => 1000,
            'recipeQty' => $recipeQty,
            'recipeInPurchaseUnit' => false,
            'saleQty' => self::SALE_QTY,
            'expectedStockAfterPurchase' => $conversionRate,
            'expectedStockAfterSale' => $conversionRate - ($recipeQty * self::SALE_QTY),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function volumeCase(string $code, string $name, float $conversionRate, float $recipeQty): array
    {
        return [
            'purchaseUnit' => ['code' => $code, 'name' => $name],
            'consumptionUnit' => ['code' => 'ML', 'name' => 'Milliliter'],
            'conversionRate' => $conversionRate,
            'purchaseQty' => 1,
            'purchaseUnitPrice' => 1000,
            'recipeQty' => $recipeQty,
            'recipeInPurchaseUnit' => false,
            'saleQty' => self::SALE_QTY,
            'expectedStockAfterPurchase' => $conversionRate,
            'expectedStockAfterSale' => $conversionRate - ($recipeQty * self::SALE_QTY),
        ];
    }

    #[DataProvider('packagingUnitScenarios')]
    #[TestDox('Purchase → POS sale respects ingredient conversion')]
    public function test_purchase_pos_sale_stock_matches_conversion(
        array $purchaseUnit,
        array $consumptionUnit,
        float $conversionRate,
        float $purchaseQty,
        float $purchaseUnitPrice,
        float $recipeQty,
        bool $recipeInPurchaseUnit,
        int $saleQty,
        float $expectedStockAfterPurchase,
        float $expectedStockAfterSale,
    ): void {
        $label = $purchaseUnit['code'].' '.$purchaseUnit['name'];
        $units = $this->seedUnits($purchaseUnit, $consumptionUnit);
        $ingredient = $this->createIngredient(
            'Item '.$purchaseUnit['code'],
            $purchaseUnit['code'],
            $units['purchase'],
            $units['consumption'],
            $conversionRate,
            $purchaseUnitPrice
        );

        $this->assertSame(0.0, $this->ingredientStock($ingredient), "{$label}: stock should start at zero");

        $supplier = $this->createSupplier($purchaseUnit['code']);
        $this->createPurchase($supplier, $ingredient, $units['purchase'], $purchaseQty, $purchaseUnitPrice);

        $this->assertSame(
            $expectedStockAfterPurchase,
            $this->ingredientStock($ingredient),
            "{$label}: stock after purchase should equal purchase qty × conversion rate"
        );

        $recipeUnitId = $recipeInPurchaseUnit
            ? (string) $units['purchase']->id
            : (string) $units['consumption']->id;

        if ($recipeInPurchaseUnit) {
            $this->assertSame(
                IngredientQuantity::UNIT_PURCHASE,
                IngredientQuantity::matchRecipeUnit($ingredient, $recipeUnitId)
            );
            $this->assertSame(
                $recipeQty * $conversionRate,
                IngredientQuantity::toConsumptionQuantity($ingredient, $recipeQty, $recipeUnitId)
            );
        }

        $menuItem = $this->createRecipeMenuItem($ingredient, $recipeQty, $recipeUnitId, $purchaseUnit['code']);
        $this->completePosCheckout($menuItem, $saleQty);

        $this->assertSame(
            $expectedStockAfterSale,
            $this->ingredientStock($ingredient),
            "{$label}: stock after POS sale should match expected consumption deduction"
        );

        $deduction = $expectedStockAfterPurchase - $expectedStockAfterSale;
        $movement = StockMovement::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->latest('id')
            ->first();

        $this->assertNotNull($movement, "{$label}: sale stock movement should exist");
        $this->assertSame($deduction, (float) $movement->quantity, "{$label}: movement qty should match deducted stock");
    }

    #[TestDox('Stock is unchanged before purchase and after zero-quantity baseline')]
    public function test_stock_only_changes_after_purchase_and_sale(): void
    {
        $purchaseUnit = ['code' => 'C05', 'name' => '2 Kg Pack'];
        $consumptionUnit = ['code' => 'G', 'name' => 'Gram'];
        $units = $this->seedUnits($purchaseUnit, $consumptionUnit);

        $ingredient = $this->createIngredient(
            'Step Check Ingredient',
            'STEP',
            $units['purchase'],
            $units['consumption'],
            2000,
            500
        );

        $this->assertSame(0.0, $this->ingredientStock($ingredient));

        $supplier = $this->createSupplier('STEP');
        $this->createPurchase($supplier, $ingredient, $units['purchase'], 1, 500);
        $this->assertSame(2000.0, $this->ingredientStock($ingredient));

        $menuItem = $this->createRecipeMenuItem($ingredient, 100, (string) $units['consumption']->id, 'STEP');
        $this->completePosCheckout($menuItem, 1);
        $this->assertSame(1900.0, $this->ingredientStock($ingredient));
    }

    /**
     * @param  array{code: string, name: string}  $purchaseUnit
     * @param  array{code: string, name: string}  $consumptionUnit
     * @return array{purchase: IngredientUnit, consumption: IngredientUnit}
     */
    private function seedUnits(array $purchaseUnit, array $consumptionUnit): array
    {
        $consumption = IngredientUnit::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $this->tenantCompany->id,
                'code' => $consumptionUnit['code'],
            ],
            ['name' => $consumptionUnit['name']]
        );

        $purchase = IngredientUnit::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $this->tenantCompany->id,
                'code' => $purchaseUnit['code'],
            ],
            ['name' => $purchaseUnit['name']]
        );

        return ['purchase' => $purchase, 'consumption' => $consumption];
    }

    private function createIngredient(
        string $name,
        string $sku,
        IngredientUnit $purchaseUnit,
        IngredientUnit $consumptionUnit,
        float $conversionRate,
        float $purchasePrice,
    ): Ingredient {
        return Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => $name,
            'sku' => $sku,
            'base_unit_id' => (string) $consumptionUnit->id,
            'consumption_unit_id' => $consumptionUnit->id,
            'purchase_unit_id' => $purchaseUnit->id,
            'conversion_rate' => $conversionRate,
            'purchase_price' => $purchasePrice,
            'cost_per_unit' => Ingredient::calculateCostPerUnit($purchasePrice, $conversionRate),
            'track_stock' => 'yes',
            'is_active' => true,
        ]);
    }

    private function createSupplier(string $code): Supplier
    {
        return Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Supplier '.$code,
            'code' => 'SUP-'.$code,
            'status' => 'active',
        ]);
    }

    private function createPurchase(
        Supplier $supplier,
        Ingredient $ingredient,
        IngredientUnit $purchaseUnit,
        float $quantity,
        float $unitPrice,
    ): Purchase {
        $total = $quantity * $unitPrice;

        $this->actingAsCompanyAdmin()
            ->post(route('purchases.store'), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => 0,
                'subtotal' => $total,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $total,
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => $quantity,
                    'unit_id' => (string) $purchaseUnit->id,
                    'unit_price' => $unitPrice,
                    'expiry_date' => null,
                ]],
            ])
            ->assertRedirect(route('purchases.index'));

        return Purchase::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function createRecipeMenuItem(
        Ingredient $ingredient,
        float $recipeQty,
        string $recipeUnitId,
        string $code,
    ): MenuItem {
        $this->actingAsCompanyAdmin()
            ->post(route('recipes.store'), [
                'name' => 'Recipe '.$code,
                'code' => 'R-'.$code,
                'is_active' => 1,
                'items' => [[
                    'ingredient_id' => $ingredient->id,
                    'quantity' => $recipeQty,
                    'unit_id' => $recipeUnitId,
                    'waste_percentage' => 0,
                ]],
            ])
            ->assertRedirect(route('recipes.index'));

        $recipe = Recipe::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('code', 'R-'.$code)
            ->firstOrFail();

        $category = Category::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->tenantCompany->id, 'code' => 'CAT-'.$code],
            [
                'name' => 'Category '.$code,
                'slug' => 'cat-'.$code.'-'.uniqid(),
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        return MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'recipe',
            'default_recipe_id' => $recipe->id,
            'name' => 'Menu '.$code,
            'slug' => 'menu-'.$code.'-'.uniqid(),
            'sku' => 'MI-'.$code,
            'price' => self::MENU_ITEM_PRICE,
            'is_available' => true,
            'track_inventory' => true,
        ]);
    }

    private function completePosCheckout(MenuItem $menuItem, int $quantity): Order
    {
        $orderTotal = $quantity * self::MENU_ITEM_PRICE;

        $response = $this->actingAsCompanyAdmin()
            ->postJson(route('pos.store'), [
                'mode' => 'pay',
                'type' => 'takeaway',
                'branch_id' => $this->tenantBranch->id,
                'items' => [[
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'name' => $menuItem->name,
                    'quantity' => $quantity,
                    'unit_price' => self::MENU_ITEM_PRICE,
                    'variants' => null,
                    'addons' => null,
                ]],
                'subtotal' => $orderTotal,
                'tax_amount' => 0,
                'discount_type' => null,
                'discount_value' => null,
                'service_charge' => 0,
                'delivery_fee' => 0,
                'total_amount' => $orderTotal,
                'paid_amount' => $orderTotal,
                'money_source_id' => $this->cashSource->id,
                'payment_status' => 'paid',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        return Order::withoutGlobalScopes()->findOrFail($response->json('order.id'));
    }

    private function ingredientStock(Ingredient $ingredient): float
    {
        return (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->sum('quantity');
    }
}
