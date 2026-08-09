<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Services\OrderRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class OrderRefundReversalTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private MoneySource $cashSource;

    private MoneySource $cardSource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();
        $this->actingAsCompanyAdmin();

        $this->cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 50000,
            'active' => true,
        ]);
        $this->cashSource->branches()->attach($this->tenantBranch->id);

        $this->cardSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Card',
            'type' => 'BANK',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $this->cardSource->branches()->attach($this->tenantBranch->id);

        Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);
    }

    public function test_full_refund_restocks_recipe_ingredients_and_reverses_cash(): void
    {
        $fixture = $this->createSaleFixture();
        $order = $this->completePosCheckout($fixture['menuItem'], qty: 2, unitPrice: 500);
        $stockAfterSale = $this->ingredientStock($fixture['ingredient']);
        $cashAfterSale = $this->cashSource->getCurrentBalance($this->tenantBranch->id);

        $this->assertTrue(
            Transaction::query()->where('reference_type', 'sale')->where('ref_id', $order->id)->exists()
        );

        $item = $order->items->first();
        app(OrderRefundService::class)->processRefund(
            $order,
            [[
                'order_item_id' => $item->id,
                'quantity' => 2,
            ]],
            'Full refund — cancelled before pickup',
            $this->companyAdmin->id
        );

        $order->refresh();
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame(0.0, (float) $order->total_amount);
        $this->assertSame(0.0, (float) $order->paid_amount);

        $this->assertGreaterThan($stockAfterSale, $this->ingredientStock($fixture['ingredient']));
        $this->assertSame(
            round($cashAfterSale - 1000, 2),
            $this->cashSource->getCurrentBalance($this->tenantBranch->id)
        );
        $this->assertSame(1, Transaction::query()
            ->where('reference_type', 'refund')
            ->where('ref_id', $order->id)
            ->where('type', 'out')
            ->count());
    }

    public function test_partial_refund_restocks_proportional_qty_and_cash(): void
    {
        $fixture = $this->createSaleFixture();
        $order = $this->completePosCheckout($fixture['menuItem'], qty: 2, unitPrice: 500);
        $stockAfterSale = $this->ingredientStock($fixture['ingredient']);
        $cashAfterSale = $this->cashSource->getCurrentBalance($this->tenantBranch->id);

        $item = $order->items->first();
        app(OrderRefundService::class)->processRefund(
            $order,
            [[
                'order_item_id' => $item->id,
                'quantity' => 1,
            ]],
            'Partial refund one item',
            $this->companyAdmin->id
        );

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(500.0, (float) $order->total_amount);
        $this->assertSame(500.0, (float) $order->paid_amount);

        $restocked = $this->ingredientStock($fixture['ingredient']) - $stockAfterSale;
        $this->assertEqualsWithDelta(100.0, $restocked, 0.01); // recipe uses 100g per item
        $this->assertSame(
            round($cashAfterSale - 500, 2),
            $this->cashSource->getCurrentBalance($this->tenantBranch->id)
        );
    }

    public function test_credit_sale_refund_reverses_customer_balance(): void
    {
        $this->tenantCompany->update([
            'settings' => array_merge($this->tenantCompany->settings ?? [], [
                'allow_pos_credit_sales' => true,
            ]),
        ]);

        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Credit Customer',
            'code' => 'CU-RF',
            'balance' => 0,
            'is_active' => true,
        ]);

        $fixture = $this->createSaleFixture();
        $order = $this->completePosCheckout(
            $fixture['menuItem'],
            qty: 1,
            unitPrice: 500,
            paidAmount: 0,
            paymentMethod: 'credit',
            customerId: $customer->id,
        );

        $customer->refresh();
        $this->assertSame(500.0, (float) $customer->balance);

        $item = $order->items->first();
        app(OrderRefundService::class)->processRefund(
            $order,
            [[
                'order_item_id' => $item->id,
                'quantity' => 1,
            ]],
            'Credit sale cancelled',
            $this->companyAdmin->id
        );

        $customer->refresh();
        $order->refresh();
        $this->assertSame(0.0, (float) $customer->balance);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame(0, Transaction::query()
            ->where('reference_type', 'refund')
            ->where('ref_id', $order->id)
            ->count());
    }

    public function test_split_payment_refund_reverses_each_money_source(): void
    {
        $fixture = $this->createSaleFixture();
        $order = $this->completePosCheckout($fixture['menuItem'], qty: 2, unitPrice: 500);

        // Rewrite to split payment transactions (as checkout would create)
        Transaction::query()
            ->where('reference_type', 'sale')
            ->where('ref_id', $order->id)
            ->delete();

        $order->update([
            'payment_method' => 'split',
            'money_source_id' => null,
            'paid_amount' => 1000,
        ]);

        OrderPayment::withoutGlobalScopes()->create([
            'order_id' => $order->id,
            'money_source_id' => $this->cashSource->id,
            'amount' => 400,
            'payment_method' => 'cash',
            'sort_order' => 1,
        ]);
        OrderPayment::withoutGlobalScopes()->create([
            'order_id' => $order->id,
            'money_source_id' => $this->cardSource->id,
            'amount' => 600,
            'payment_method' => 'card',
            'sort_order' => 2,
        ]);

        $accountId = Account::query()->where('company_id', $this->tenantCompany->id)->where('name', 'Sales')->value('id');
        Transaction::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $accountId,
            'amount' => 400,
            'type' => 'in',
            'payment_method' => 'cash',
            'money_source_id' => $this->cashSource->id,
            'reference_type' => 'sale',
            'date' => now()->toDateString(),
            'ref_id' => $order->id,
            'created_by' => $this->companyAdmin->id,
            'shift_id' => $order->shift_id,
            'notes' => 'Split cash',
        ]);
        Transaction::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $accountId,
            'amount' => 600,
            'type' => 'in',
            'payment_method' => 'card',
            'money_source_id' => $this->cardSource->id,
            'reference_type' => 'sale',
            'date' => now()->toDateString(),
            'ref_id' => $order->id,
            'created_by' => $this->companyAdmin->id,
            'shift_id' => $order->shift_id,
            'notes' => 'Split card',
        ]);

        $cashBefore = $this->cashSource->getCurrentBalance($this->tenantBranch->id);
        $cardBefore = $this->cardSource->getCurrentBalance($this->tenantBranch->id);

        $item = $order->fresh('items')->items->first();
        app(OrderRefundService::class)->processRefund(
            $order->fresh(),
            [[
                'order_item_id' => $item->id,
                'quantity' => 2,
            ]],
            'Split refund',
            $this->companyAdmin->id
        );

        $this->assertSame(
            round($cashBefore - 400, 2),
            $this->cashSource->getCurrentBalance($this->tenantBranch->id)
        );
        $this->assertSame(
            round($cardBefore - 600, 2),
            $this->cardSource->getCurrentBalance($this->tenantBranch->id)
        );
        $this->assertSame(2, Transaction::query()
            ->where('reference_type', 'refund')
            ->where('ref_id', $order->id)
            ->count());
    }

    /**
     * @return array{ingredient: Ingredient, menuItem: MenuItem}
     */
    private function createSaleFixture(): array
    {
        $ingredient = $this->createIngredient('Patty', 'PATTY-'.uniqid(), 2000);
        $this->purchaseStock($ingredient);

        $recipe = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'code' => 'R-'.uniqid(),
            'name' => 'Burger BOM',
            'is_active' => true,
        ]);
        $recipe->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
            'unit_id' => 'g',
            'waste_percentage' => 0,
        ]);

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food',
            'code' => 'FD',
            'slug' => 'food-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Burger',
            'slug' => 'burger-'.uniqid(),
            'sku' => 'MI-RF-'.uniqid(),
            'type' => 'recipe',
            'default_recipe_id' => $recipe->id,
            'price' => 500,
            'cost' => 0,
            'is_available' => true,
            'track_inventory' => true,
            'sort_order' => 1,
        ]);

        return ['ingredient' => $ingredient, 'menuItem' => $menuItem->fresh()];
    }

    private function completePosCheckout(
        MenuItem $menuItem,
        int $qty = 2,
        float $unitPrice = 500,
        ?float $paidAmount = null,
        string $paymentMethod = 'cash',
        ?int $customerId = null,
    ): Order {
        $total = $qty * $unitPrice;
        $paidAmount ??= $total;

        $payload = [
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
            'discount_type' => null,
            'discount_value' => null,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => $total,
            'paid_amount' => $paidAmount,
            'payment_status' => $paidAmount + 0.01 >= $total ? 'paid' : 'partial',
            'notes' => 'Refund test sale',
        ];

        if ($paymentMethod === 'credit') {
            $payload['payment_method'] = 'credit';
            $payload['customer_id'] = $customerId;
            $payload['money_source_id'] = null;
        } else {
            $payload['money_source_id'] = $this->cashSource->id;
        }

        $response = $this->postJson(route('pos.store'), $payload);
        $response->assertOk();

        return Order::withoutGlobalScopes()->with('items')->findOrFail($response->json('order.id'));
    }

    private function purchaseStock(Ingredient $ingredient): void
    {
        $supplier = Supplier::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Supplier',
            'code' => 'SU-'.uniqid(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $this->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'branch_id' => $this->tenantBranch->id,
            'purchase_date' => now()->toDateString(),
            'payment_selection' => 'credit',
            'paid_amount' => 0,
            'subtotal' => 4000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 4000,
            'notes' => 'Stock for refund test',
            'items' => [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 2,
                'unit_id' => 'kg',
                'unit_price' => 2000,
                'expiry_date' => null,
                'notes' => null,
            ]],
        ])->assertRedirect(route('purchases.index'));

        Purchase::withoutGlobalScopes()->latest('id')->firstOrFail();
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
            'minimum_stock_level' => 0,
        ]);
    }
}
