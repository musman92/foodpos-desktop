<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\KitchenKot;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class OrderDeleteTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private MoneySource $cashSource;

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

        Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);
    }

    public function test_completed_order_can_be_deleted_and_reverses_stock_and_transactions(): void
    {
        $fixture = $this->createSaleFixture();
        $order = $this->completePosCheckout($fixture['menuItem']);
        $originalNumber = $order->order_number;
        $stockBeforeDelete = $this->ingredientStock($fixture['ingredient']);

        $this->assertTrue(
            Transaction::query()->where('reference_type', 'sale')->where('ref_id', $order->id)->exists()
        );

        $response = $this->actingAsCompanyAdmin()
            ->delete(route('order-management.destroy', $order));

        $response->assertRedirect(route('order-management.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertFalse(Order::orderNumberExists($originalNumber));
        $this->assertTrue(
            Order::withoutGlobalScopes()->withTrashed()->where('id', $order->id)->where('order_number', $originalNumber.'-d01')->exists()
        );
        $this->assertFalse(
            Transaction::query()->where('ref_id', $order->id)->exists()
        );
        $this->assertGreaterThan($stockBeforeDelete, $this->ingredientStock($fixture['ingredient']));
    }

    public function test_open_tab_order_can_be_deleted_without_stock_finalized(): void
    {
        $fixture = $this->createSaleFixture();
        $order = $this->createOpenTab($fixture['menuItem']);

        $this->actingAsCompanyAdmin()
            ->postJson(route('pos.orders.send-to-kitchen', $order), [
                'subtotal' => 500,
                'tax_amount' => 0,
                'total_amount' => 500,
                'items' => [[
                    'menu_item_id' => $fixture['menuItem']->id,
                    'item_name' => $fixture['menuItem']->name,
                    'name' => $fixture['menuItem']->name,
                    'quantity' => 1,
                    'unit_price' => 500,
                    'variants' => null,
                    'addons' => null,
                    'special_instructions' => '',
                ]],
            ])
            ->assertOk();

        $this->assertGreaterThan(0, KitchenKot::where('order_id', $order->id)->count());

        $response = $this->actingAsCompanyAdmin()
            ->delete(route('order-management.destroy', $order));

        $response->assertRedirect(route('order-management.index'));
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertSame(0, KitchenKot::where('order_id', $order->id)->count());
        $this->assertFalse(
            Transaction::query()->where('reference_type', 'sale')->where('ref_id', $order->id)->exists()
        );
    }

    public function test_pos_cancel_removes_unpaid_open_order_from_queue(): void
    {
        $fixture = $this->createSaleFixture();
        $order = $this->createOpenTab($fixture['menuItem']);

        $response = $this->actingAsCompanyAdmin()
            ->postJson(route('pos.orders.cancel', $order));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_pos_cancel_queues_kitchen_void_slip_when_kot_was_sent(): void
    {
        $fixture = $this->createSaleFixture();
        $order = $this->createOpenTab($fixture['menuItem']);

        $this->actingAsCompanyAdmin()
            ->postJson(route('pos.orders.send-to-kitchen', $order), [
                'subtotal' => 500,
                'tax_amount' => 0,
                'total_amount' => 500,
                'items' => [[
                    'menu_item_id' => $fixture['menuItem']->id,
                    'item_name' => $fixture['menuItem']->name,
                    'name' => $fixture['menuItem']->name,
                    'quantity' => 1,
                    'unit_price' => 500,
                    'variants' => null,
                    'addons' => null,
                    'special_instructions' => '',
                ]],
            ])
            ->assertOk();

        $this->assertGreaterThan(0, KitchenKot::where('order_id', $order->id)->count());

        $response = $this->actingAsCompanyAdmin()
            ->postJson(route('pos.orders.cancel', $order));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $cancelKotId = $response->json('cancel_kot_id');
        $this->assertNotNull($cancelKotId);

        $cancelKot = KitchenKot::withoutGlobalScopes()->find($cancelKotId);
        $this->assertNotNull($cancelKot);
        $this->assertSame('void', $cancelKot->type);
        $this->assertSame((int) $order->id, (int) $cancelKot->order_id);
        $this->assertNotEmpty($cancelKot->lines);

        // Original KOTs removed; cancel VOID slip kept for print
        $this->assertSame(
            1,
            KitchenKot::withoutGlobalScopes()->where('order_id', $order->id)->count()
        );
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_pos_cancel_can_remove_completed_history_order(): void
    {
        $fixture = $this->createSaleFixture();
        $order = $this->completePosCheckout($fixture['menuItem']);

        $this->assertSame('completed', $order->fresh()->status);

        $this->actingAsCompanyAdmin()
            ->postJson(route('pos.orders.cancel', $order))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_destroy_is_blocked_without_permission(): void
    {
        $fixture = $this->createSaleFixture();
        $order = $this->completePosCheckout($fixture['menuItem']);

        $staff = \App\Models\User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => true,
        ]);

        $this->actingAs($staff)
            ->withSession(['current_branch_id' => $this->tenantBranch->id])
            ->delete(route('order-management.destroy', $order))
            ->assertForbidden();

        $this->assertNull(Order::withTrashed()->find($order->id)?->deleted_at);
    }

    /**
     * @return array{supplier: Supplier, ingredient: Ingredient, menuItem: MenuItem}
     */
    private function createSaleFixture(): array
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Fresh Farms',
            'code' => 'SUP-DEL',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Beef Patty', 'BEEF-DEL', 2000);
        $this->createCreditPurchase($supplier, $ingredient);

        $recipe = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Burger BOM',
            'code' => 'R-DEL',
            'is_active' => true,
        ]);
        $recipe->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 150,
            'unit_id' => 'g',
            'waste_percentage' => 0,
        ]);

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Burgers',
            'code' => 'BRG',
            'slug' => 'burgers-del-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'recipe',
            'default_recipe_id' => $recipe->id,
            'name' => 'Classic Burger',
            'slug' => 'classic-burger-del-'.uniqid(),
            'sku' => 'MI-DEL-'.uniqid(),
            'price' => 500,
            'is_available' => true,
            'track_inventory' => true,
            'sort_order' => 1,
        ]);

        return compact('supplier', 'ingredient', 'menuItem');
    }

    private function createCreditPurchase(Supplier $supplier, Ingredient $ingredient): Purchase
    {
        $this->actingAsCompanyAdmin()
            ->post(route('purchases.store'), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => 0,
                'subtotal' => 4000,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 4000,
                'notes' => 'Stock for delete test',
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => 2,
                    'unit_id' => 'kg',
                    'unit_price' => 2000,
                    'expiry_date' => null,
                    'notes' => null,
                ]],
            ])
            ->assertRedirect(route('purchases.index'));

        return Purchase::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    private function completePosCheckout(MenuItem $menuItem): Order
    {
        $response = $this->actingAsCompanyAdmin()
            ->postJson(route('pos.store'), [
                'mode' => 'pay',
                'type' => 'takeaway',
                'branch_id' => $this->tenantBranch->id,
                'items' => [[
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'name' => $menuItem->name,
                    'quantity' => 2,
                    'unit_price' => 500,
                    'variants' => null,
                    'addons' => null,
                    'special_instructions' => '',
                ]],
                'subtotal' => 1000,
                'tax_amount' => 0,
                'discount_type' => null,
                'discount_value' => null,
                'service_charge' => 0,
                'delivery_fee' => 0,
                'total_amount' => 1000,
                'paid_amount' => 1000,
                'money_source_id' => $this->cashSource->id,
                'payment_status' => 'paid',
                'notes' => 'Delete test sale',
            ]);

        $response->assertOk();

        return Order::withoutGlobalScopes()->findOrFail($response->json('order.id'));
    }

    private function createOpenTab(MenuItem $menuItem): Order
    {
        $response = $this->actingAsCompanyAdmin()
            ->postJson(route('pos.store'), [
                'mode' => 'tab',
                'type' => 'takeaway',
                'branch_id' => $this->tenantBranch->id,
                'items' => [[
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'name' => $menuItem->name,
                    'quantity' => 1,
                    'unit_price' => 500,
                    'variants' => null,
                    'addons' => null,
                    'special_instructions' => '',
                ]],
                'subtotal' => 500,
                'tax_amount' => 0,
                'discount_type' => null,
                'discount_value' => null,
                'service_charge' => 0,
                'delivery_fee' => 0,
                'total_amount' => 500,
                'notes' => 'Open tab',
            ]);

        $response->assertOk();

        return Order::withoutGlobalScopes()->findOrFail($response->json('order.id'));
    }

    private function ingredientStock(Ingredient $ingredient): float
    {
        return (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->sum('quantity');
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
