<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MoneySource;
use App\Models\PartyBalanceAdjustment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\PurchaseReturnService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class PurchaseReturnTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();
    }

    public function test_return_uses_purchase_line_unit_price_not_catalog_price(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Price Check Supplier',
            'code' => 'PRICE01',
            'status' => 'active',
            'balance' => 0,
        ]);

        // Catalog / master price is 500; user enters 750 on the purchase.
        $ingredient = $this->createIngredient('Beef', 'BEEF01', 500);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 1500,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 1500,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 2,
                'unit_id' => 'kg',
                'unit_price' => 750,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $item = $purchase->items()->first();
        $this->assertEquals(750.0, (float) $item->unit_price);

        $return = app(PurchaseReturnService::class)->createReturn(
            $purchase,
            [[
                'purchase_item_id' => $item->id,
                'quantity' => 1,
            ]],
            $this->companyAdmin->id,
        );

        $returnLine = $return->items()->first();
        $this->assertEquals(750.0, (float) $returnLine->unit_price);
        $this->assertEquals(750.0, (float) $return->total_amount);
        $this->assertNotEquals(500.0, (float) $returnLine->unit_price);

        $html = $this->actingAsCompanyAdmin()
            ->get(route('purchase-returns.show', $return))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Purchase price', $html);
    }

    public function test_purchase_return_reduces_stock_and_supplier_balance(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Meat Supplier',
            'code' => 'MEAT01',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Chicken', 'CHK01', 500);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 5000,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 5000,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 10,
                'unit_id' => 'kg',
                'unit_price' => 500,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $supplier->refresh();
        $this->assertEquals(5000.0, (float) $supplier->balance);
        $this->assertEquals(10000.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));

        $item = $purchase->items()->first();
        $return = app(PurchaseReturnService::class)->createReturn(
            $purchase,
            [[
                'purchase_item_id' => $item->id,
                'quantity' => 2,
                'notes' => 'Bad condition',
            ]],
            $this->companyAdmin->id,
            '2 kg chicken not good',
        );

        $this->assertInstanceOf(PurchaseReturn::class, $return);
        $this->assertEquals(1000.0, (float) $return->total_amount);
        $this->assertEquals(1000.0, (float) $return->payable_reduction_amount);
        $this->assertEquals(0.0, (float) $return->credit_amount);

        $purchase->refresh();
        $item->refresh();
        $supplier->refresh();

        $this->assertEquals(1000.0, (float) $purchase->returned_amount);
        $this->assertEquals(2.0, (float) $item->quantity_returned);
        $this->assertEquals('pending', $purchase->payment_status);
        $this->assertEquals(4000.0, (float) $supplier->balance);
        $this->assertEquals(8000.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));

        $this->assertDatabaseHas('party_balance_adjustments', [
            'party_type' => PartyBalanceAdjustment::PARTY_SUPPLIER,
            'party_id' => $supplier->id,
            'previous_balance' => 5000,
            'new_balance' => 4000,
        ]);
    }

    public function test_paid_purchase_return_creates_supplier_credit_on_balance(): void
    {
        $cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 50000,
            'active' => true,
        ]);
        $cashSource->branches()->attach($this->tenantBranch->id);

        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Paid Supplier',
            'code' => 'PAID01',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Oil', 'OIL01', 200);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 2000,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 2000,
                'paid_amount' => 2000,
                'payment_method' => 'cash',
                'money_source_id' => $cashSource->id,
                'payment_status' => 'paid',
                'notes' => null,
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 10,
                'unit_id' => 'kg',
                'unit_price' => 200,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $supplier->refresh();
        $this->assertEquals(0.0, (float) $supplier->balance);

        $item = $purchase->items()->first();
        $return = app(PurchaseReturnService::class)->createReturn(
            $purchase,
            [[
                'purchase_item_id' => $item->id,
                'quantity' => 2,
            ]],
            $this->companyAdmin->id,
            'Paid goods returned',
        );

        $this->assertEquals(400.0, (float) $return->total_amount);
        $this->assertEquals(0.0, (float) $return->payable_reduction_amount);
        $this->assertEquals(400.0, (float) $return->credit_amount);
        $this->assertEquals('supplier_credit', $return->settlement_type);

        $supplier->refresh();
        $this->assertEquals(-400.0, (float) $supplier->balance);

        $this->assertDatabaseHas('party_balance_adjustments', [
            'party_type' => PartyBalanceAdjustment::PARTY_SUPPLIER,
            'party_id' => $supplier->id,
            'previous_balance' => 0,
            'new_balance' => -400,
        ]);
    }

    public function test_purchase_return_http_flow_creates_history_entry(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Veg Supplier',
            'code' => 'VEG01',
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Tomato', 'TOM01', 100);

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => 200,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 200,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => 2,
                'unit_id' => 'kg',
                'unit_price' => 100,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $item = $purchase->items()->first();

        $response = $this->actingAsCompanyAdmin()->post(route('purchase-returns.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => now()->toDateString(),
            'notes' => 'Damaged',
            'items' => [
                [
                    'purchase_item_id' => $item->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $return = PurchaseReturn::query()->first();
        $this->assertNotNull($return);
        $response->assertRedirect(route('purchase-returns.show', $return));

        $this->actingAsCompanyAdmin()
            ->get(route('purchase-returns.index'))
            ->assertOk()
            ->assertSee($return->return_number);
    }

    public function test_purchase_return_can_be_updated(): void
    {
        [$supplier, $ingredient, $purchase, $item, $return] = $this->createReturnScenario(
            purchaseQty: 10,
            unitPrice: 500,
            returnQty: 2,
        );

        $updated = app(PurchaseReturnService::class)->updateReturn(
            $return,
            [[
                'purchase_item_id' => $item->id,
                'quantity' => 3,
                'notes' => 'Adjusted',
            ]],
            $this->companyAdmin->id,
            'Updated notes',
        );

        $this->assertEquals(1500.0, (float) $updated->total_amount);

        $purchase->refresh();
        $item->refresh();
        $supplier->refresh();

        $this->assertEquals(1500.0, (float) $purchase->returned_amount);
        $this->assertEquals(3.0, (float) $item->quantity_returned);
        $this->assertEquals(3500.0, (float) $supplier->balance);
        $this->assertEquals(7000.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));
    }

    public function test_purchase_return_can_be_deleted(): void
    {
        [$supplier, $ingredient, $purchase, $item, $return] = $this->createReturnScenario(
            purchaseQty: 10,
            unitPrice: 500,
            returnQty: 2,
        );

        $this->actingAsCompanyAdmin()
            ->delete(route('purchase-returns.destroy', $return))
            ->assertRedirect(route('purchase-returns.index'));

        $this->assertDatabaseMissing('purchase_returns', ['id' => $return->id]);

        $purchase->refresh();
        $item->refresh();
        $supplier->refresh();

        $this->assertEquals(0.0, (float) $purchase->returned_amount);
        $this->assertEquals(0.0, (float) $item->quantity_returned);
        $this->assertEquals(5000.0, (float) $supplier->balance);
        $this->assertEquals(10000.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));
    }

    public function test_purchase_return_edit_page_loads(): void
    {
        [, , , , $return] = $this->createReturnScenario(
            purchaseQty: 5,
            unitPrice: 100,
            returnQty: 1,
        );

        $this->actingAsCompanyAdmin()
            ->get(route('purchase-returns.edit', $return))
            ->assertOk()
            ->assertSee($return->return_number);
    }

    /**
     * @return array{0: Supplier, 1: Ingredient, 2: Purchase, 3: \App\Models\PurchaseItem, 4: PurchaseReturn}
     */
    private function createReturnScenario(float $purchaseQty, float $unitPrice, float $returnQty): array
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Return Supplier',
            'code' => 'RET'.uniqid(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Return Item', 'RI'.uniqid(), $unitPrice);
        $total = $purchaseQty * $unitPrice;

        $purchase = app(PurchaseService::class)->createPurchase(
            [
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'subtotal' => $total,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $total,
                'paid_amount' => 0,
                'payment_method' => 'credit',
                'money_source_id' => null,
                'payment_status' => 'pending',
                'notes' => null,
            ],
            [[
                'item_type' => 'ingredient',
                'item_id' => $ingredient->id,
                'quantity' => $purchaseQty,
                'unit_id' => 'kg',
                'unit_price' => $unitPrice,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $item = $purchase->items()->first();
        $return = app(PurchaseReturnService::class)->createReturn(
            $purchase,
            [[
                'purchase_item_id' => $item->id,
                'quantity' => $returnQty,
            ]],
            $this->companyAdmin->id,
            'Initial return',
        );

        $supplier->refresh();

        return [$supplier, $ingredient, $purchase->fresh(), $item->fresh(), $return];
    }

    private function createIngredient(string $name, string $sku, float $purchasePricePerKg): Ingredient
    {
        $kgUnit = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Kilogram',
            'code' => 'kg-'.uniqid(),
        ]);
        $gramUnit = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g-'.uniqid(),
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
