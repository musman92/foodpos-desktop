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
use App\Models\SupplierPayment;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Full business path regression broken into readable steps:
 * supplier → purchase → balance → recipe → menu item → POS → stock → supplier payment.
 */
class PosPurchaseSaleStockFlowTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private MoneySource $cashSource;

    private Account $purchaseAccount;

    private const PURCHASE_QTY_KG = 2;

    private const PURCHASE_UNIT_PRICE = 2000;

    private const RECIPE_QTY_G = 150;

    private const SALE_QTY = 2;

    private const MENU_ITEM_PRICE = 500.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();

        $this->cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 50000,
            'active' => true,
        ]);
        $this->cashSource->branches()->attach($this->tenantBranch->id);

        $this->purchaseAccount = Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Purchase',
            'type' => 'expense',
            'is_active' => true,
        ]);

        Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);
    }

    #[TestDox('Supplier added')]
    public function test_supplier_added(): void
    {
        $supplier = $this->createSupplierViaHttp();

        $this->assertSame('Fresh Farms', $supplier->name);
        $this->assertSame('SUP-FF', $supplier->code);
        $this->assertSame('active', $supplier->status);
        $this->assertSame(0.0, (float) $supplier->balance);
    }

    #[TestDox('Purchase completed')]
    public function test_purchase_completed(): void
    {
        $supplier = $this->createSupplierViaHttp();
        $ingredient = $this->createIngredient('Beef Patty', 'BEEF01', self::PURCHASE_UNIT_PRICE);
        $purchase = $this->createCreditPurchaseViaHttp($supplier, $ingredient);

        $this->assertSame('pending', $purchase->payment_status);
        $this->assertSame(0.0, (float) $purchase->paid_amount);
        $this->assertSame((float) $this->purchaseTotal(), (float) $purchase->total_amount);
        $this->assertSame(2000.0, $this->ingredientStock($ingredient));
    }

    #[TestDox('Supplier balance check')]
    public function test_supplier_balance_after_purchase(): void
    {
        $supplier = $this->createSupplierViaHttp();
        $ingredient = $this->createIngredient('Beef Patty', 'BEEF01', self::PURCHASE_UNIT_PRICE);
        $this->createCreditPurchaseViaHttp($supplier, $ingredient);

        $supplier->refresh();
        $this->assertSame((float) $this->purchaseTotal(), (float) $supplier->balance);
    }

    #[TestDox('Recipe created')]
    public function test_recipe_created(): void
    {
        $ingredient = $this->createIngredient('Beef Patty', 'BEEF01', self::PURCHASE_UNIT_PRICE);
        $recipe = $this->createRecipeViaHttp($ingredient);

        $this->assertSame('R-BURGER', $recipe->code);
        $this->assertSame('Burger Patty BOM', $recipe->name);
        $this->assertCount(1, $recipe->items);
        $this->assertSame((float) self::RECIPE_QTY_G, (float) $recipe->items->first()->quantity);
        $this->assertSame((int) $ingredient->id, (int) $recipe->items->first()->ingredient_id);
    }

    #[TestDox('Menu item created')]
    public function test_menu_item_created(): void
    {
        $ingredient = $this->createIngredient('Beef Patty', 'BEEF01', self::PURCHASE_UNIT_PRICE);
        $recipe = $this->createRecipeViaHttp($ingredient);
        $menuItem = $this->createMenuItemLinkedToRecipe($recipe);

        $this->assertSame('recipe', $menuItem->type);
        $this->assertSame((int) $recipe->id, (int) $menuItem->default_recipe_id);
        $this->assertSame('Classic Burger', $menuItem->name);
        $this->assertSame(self::MENU_ITEM_PRICE, (float) $menuItem->price);
        $this->assertTrue((bool) $menuItem->is_available);
    }

    #[TestDox('POS checkout')]
    public function test_pos_checkout(): void
    {
        $fixture = $this->readyForPosSale();

        $order = $this->completePosCheckout($fixture['menuItem']);

        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertCount(1, $order->items);
        $this->assertSame($this->orderTotal(), (float) $order->total_amount);

        $saleTxn = Transaction::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('type', 'in')
            ->where('money_source_id', $this->cashSource->id)
            ->where('ref_id', $order->id)
            ->first();
        $this->assertNotNull($saleTxn, 'POS sale should post cash-in transaction');
        $this->assertSame($this->orderTotal(), (float) $saleTxn->amount);
    }

    #[TestDox('Stock matched')]
    public function test_stock_matched_after_pos_sale(): void
    {
        $fixture = $this->readyForPosSale();
        $this->completePosCheckout($fixture['menuItem']);

        $expectedStock = 2000.0 - (self::RECIPE_QTY_G * self::SALE_QTY);
        $this->assertSame($expectedStock, $this->ingredientStock($fixture['ingredient']));

        $saleMovement = StockMovement::withoutGlobalScopes()
            ->where('ingredient_id', $fixture['ingredient']->id)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->where('branch_id', $this->tenantBranch->id)
            ->first();
        $this->assertNotNull($saleMovement);
        $this->assertSame((float) (self::RECIPE_QTY_G * self::SALE_QTY), (float) $saleMovement->quantity);
    }

    #[TestDox('Supplier payment processed')]
    public function test_supplier_payment_processed(): void
    {
        $fixture = $this->readyForPosSale();
        $this->completePosCheckout($fixture['menuItem']);

        $this->actingAsCompanyAdmin()
            ->post(route('supplier-payments.store'), [
                'supplier_id' => $fixture['supplier']->id,
                'branch_id' => $this->tenantBranch->id,
                'account_id' => $this->purchaseAccount->id,
                'money_source_id' => $this->cashSource->id,
                'payment_date' => now()->toDateString(),
                'total_amount' => $this->purchaseTotal(),
                'notes' => 'E2E supplier payment',
            ])
            ->assertRedirect(route('supplier-payments.index'));

        $fixture['purchase']->refresh();
        $this->assertSame('paid', $fixture['purchase']->payment_status);
        $this->assertSame((float) $this->purchaseTotal(), (float) $fixture['purchase']->paid_amount);

        $fixture['supplier']->refresh();
        $this->assertSame(0.0, (float) $fixture['supplier']->balance);

        $payment = SupplierPayment::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('supplier_id', $fixture['supplier']->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($payment);
        $this->assertSame((float) $this->purchaseTotal(), (float) $payment->total_amount);

        $paymentTxn = Transaction::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('type', 'out')
            ->where('money_source_id', $this->cashSource->id)
            ->where('ref_id', $payment->id)
            ->first();
        $this->assertNotNull($paymentTxn, 'Supplier payment should post cash-out transaction');
        $this->assertSame((float) $this->purchaseTotal(), (float) $paymentTxn->amount);

        $this->assertSame(
            2000.0 - (self::RECIPE_QTY_G * self::SALE_QTY),
            $this->ingredientStock($fixture['ingredient']),
            'Supplier payment must not change stock'
        );
    }

    private function purchaseTotal(): int
    {
        return self::PURCHASE_QTY_KG * self::PURCHASE_UNIT_PRICE;
    }

    private function orderTotal(): float
    {
        return self::SALE_QTY * self::MENU_ITEM_PRICE;
    }

    private function createSupplierViaHttp(): Supplier
    {
        $this->actingAsCompanyAdmin()
            ->post(route('suppliers.store'), [
                'name' => 'Fresh Farms',
                'code' => 'SUP-FF',
                'status' => 'active',
                'phone' => '03001234567',
            ])
            ->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('code', 'SUP-FF')
            ->first();

        $this->assertNotNull($supplier);

        return $supplier;
    }

    private function createCreditPurchaseViaHttp(Supplier $supplier, Ingredient $ingredient): Purchase
    {
        $total = $this->purchaseTotal();

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
                'notes' => 'E2E purchase',
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => self::PURCHASE_QTY_KG,
                    'unit_id' => 'kg',
                    'unit_price' => self::PURCHASE_UNIT_PRICE,
                    'expiry_date' => null,
                    'notes' => null,
                ]],
            ])
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('supplier_id', $supplier->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($purchase);

        return $purchase;
    }

    private function createRecipeViaHttp(Ingredient $ingredient): Recipe
    {
        $this->actingAsCompanyAdmin()
            ->post(route('recipes.store'), [
                'name' => 'Burger Patty BOM',
                'code' => 'R-BURGER',
                'is_active' => 1,
                'items' => [[
                    'ingredient_id' => $ingredient->id,
                    'quantity' => self::RECIPE_QTY_G,
                    'unit_id' => 'g',
                    'waste_percentage' => 0,
                    'notes' => null,
                ]],
            ])
            ->assertRedirect(route('recipes.index'));

        $recipe = Recipe::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('code', 'R-BURGER')
            ->with('items')
            ->first();

        $this->assertNotNull($recipe);

        return $recipe;
    }

    private function createMenuItemLinkedToRecipe(Recipe $recipe): MenuItem
    {
        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Burgers',
            'code' => 'BRG',
            'slug' => 'burgers-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'recipe',
            'default_recipe_id' => $recipe->id,
            'name' => 'Classic Burger',
            'slug' => 'classic-burger-'.uniqid(),
            'sku' => 'MI-BURGER-'.uniqid(),
            'price' => self::MENU_ITEM_PRICE,
            'is_available' => true,
            'track_inventory' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * @return array{supplier: Supplier, ingredient: Ingredient, purchase: Purchase, recipe: Recipe, menuItem: MenuItem}
     */
    private function readyForPosSale(): array
    {
        $supplier = $this->createSupplierViaHttp();
        $ingredient = $this->createIngredient('Beef Patty', 'BEEF01', self::PURCHASE_UNIT_PRICE);
        $purchase = $this->createCreditPurchaseViaHttp($supplier, $ingredient);
        $recipe = $this->createRecipeViaHttp($ingredient);
        $menuItem = $this->createMenuItemLinkedToRecipe($recipe);

        return compact('supplier', 'ingredient', 'purchase', 'recipe', 'menuItem');
    }

    private function completePosCheckout(MenuItem $menuItem): Order
    {
        $orderTotal = $this->orderTotal();

        $posResponse = $this->actingAsCompanyAdmin()
            ->postJson(route('pos.store'), [
                'mode' => 'pay',
                'type' => 'takeaway',
                'branch_id' => $this->tenantBranch->id,
                'items' => [[
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'name' => $menuItem->name,
                    'quantity' => self::SALE_QTY,
                    'unit_price' => self::MENU_ITEM_PRICE,
                    'variants' => null,
                    'addons' => null,
                    'special_instructions' => '',
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
                'notes' => 'E2E sale',
            ]);

        $posResponse->assertOk();
        $posResponse->assertJsonPath('success', true);

        return Order::withoutGlobalScopes()->findOrFail($posResponse->json('order.id'));
    }

    private function ingredientStock(Ingredient $ingredient): float
    {
        return (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity');
    }

    private function createIngredient(string $name, string $sku, float $purchasePricePerKg): Ingredient
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

        return Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => $name,
            'sku' => $sku,
            'base_unit_id' => 'g',
            'consumption_unit_id' => $gramUnit->id,
            'purchase_unit_id' => $kgUnit->id,
            'conversion_rate' => 1000,
            'purchase_price' => $purchasePricePerKg,
            'cost_per_unit' => $purchasePricePerKg / 1000,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);
    }
}
