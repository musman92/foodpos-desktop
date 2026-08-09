<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MoneySource;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Support\PurchaseModificationImpact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class PurchaseModificationWithPaymentsTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();
    }

    public function test_validate_delete_reports_supplier_payment_reversal(): void
    {
        [$purchase] = $this->createPaidCreditPurchaseFixture();

        $report = app(PurchaseModificationImpact::class)->analyzeDelete($purchase->fresh());

        $this->assertTrue($report['can_proceed']);
        $this->assertFalse($report['blocked']);
        $this->assertCount(1, $report['supplier_payments']);
    }

    public function test_purchase_with_supplier_payment_can_be_deleted_when_stock_available(): void
    {
        [$purchase, $supplier, $ingredient] = $this->createPaidCreditPurchaseFixture();

        $this->actingAsCompanyAdmin()
            ->delete(route('purchases.destroy', $purchase))
            ->assertRedirect(route('purchases.index'));

        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
        $this->assertSame(0, SupplierPayment::withoutGlobalScopes()->count());
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('reference_type', 'purchase')->count());
        $this->assertNull(BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->first());
        $this->assertSame(0.0, (float) $supplier->fresh()->balance);
    }

    public function test_validate_update_endpoint_returns_json(): void
    {
        [$purchase] = $this->createPaidCreditPurchaseFixture();

        $response = $this->actingAsCompanyAdmin()
            ->postJson(route('purchases.validate-update', $purchase), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $purchase->supplier_id,
                'purchase_date' => $purchase->purchase_date->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => 0,
                'subtotal' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 100,
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $purchase->items->first()->item_id,
                    'quantity' => 1,
                    'unit_id' => 'kg',
                    'unit_price' => 100,
                    'expiry_date' => null,
                ]],
            ]);

        $response->assertOk();
        $response->assertJsonPath('action', 'update');
        $response->assertJsonPath('can_proceed', true);
    }

    public function test_validate_update_keeps_supplier_payments_and_reports_balance_adjustment(): void
    {
        [$purchase] = $this->createPaidCreditPurchaseFixture();

        $report = app(PurchaseModificationImpact::class)->analyzeUpdate(
            $purchase->fresh(),
            [[
                'item_type' => 'ingredient',
                'item_id' => $purchase->items->first()->item_id,
                'quantity' => 2,
                'unit_id' => 'kg',
                'unit_price' => 100,
                'expiry_date' => null,
            ]],
            200.0
        );

        $this->assertTrue($report['can_proceed']);
        $this->assertTrue($report['supplier_payments'][0]['kept'] ?? false);

        $messageText = collect($report['messages'])->pluck('text')->implode(' ');
        $this->assertStringContainsString('will be kept', $messageText);
        $this->assertStringContainsString('added to supplier balance', $messageText);
    }

    public function test_paid_purchase_increment_keeps_supplier_payment_and_adds_balance_delta(): void
    {
        [$purchase, $supplier, $ingredient] = $this->createPaidCreditPurchaseFixture();

        $this->actingAsCompanyAdmin()
            ->put(route('purchases.update', $purchase), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => $purchase->purchase_date->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => 0,
                'subtotal' => 200,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 200,
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => 2,
                    'unit_id' => 'kg',
                    'unit_price' => 100,
                    'expiry_date' => null,
                ]],
            ])
            ->assertRedirect(route('purchases.show', $purchase));

        $purchase->refresh();
        $this->assertSame(1, SupplierPayment::withoutGlobalScopes()->count());
        $this->assertEquals(200.0, (float) $purchase->total_amount);
        $this->assertEquals(100.0, (float) $purchase->paid_amount);
        $this->assertSame('partial', $purchase->payment_status);
        $this->assertSame(100.0, (float) $supplier->fresh()->balance);
    }

    public function test_paid_purchase_decrease_credits_supplier_balance_when_stock_available(): void
    {
        [$purchase, $supplier, $ingredient] = $this->createPaidCreditPurchaseFixture();

        $this->actingAsCompanyAdmin()
            ->put(route('purchases.update', $purchase), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => $purchase->purchase_date->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => 0,
                'subtotal' => 50,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 50,
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => 0.5,
                    'unit_id' => 'kg',
                    'unit_price' => 100,
                    'expiry_date' => null,
                ]],
            ])
            ->assertRedirect(route('purchases.show', $purchase));

        $purchase->refresh();
        $this->assertSame(1, SupplierPayment::withoutGlobalScopes()->count());
        $this->assertEquals(50.0, (float) $purchase->total_amount);
        $this->assertEquals(50.0, (float) $purchase->paid_amount);
        $this->assertSame('paid', $purchase->payment_status);
        $this->assertSame(-50.0, (float) $supplier->fresh()->balance);
    }

    /**
     * @return array{0: Purchase, 1: Supplier, 2: Ingredient}
     */
    private function createPaidCreditPurchaseFixture(): array
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'General Market',
            'code' => 'GM-'.uniqid(),
            'status' => 'active',
            'balance' => 0,
        ]);

        $ingredient = $this->createIngredient('Flour', 'FL-'.uniqid(), 100);

        Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Purchase',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $moneySource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 10000,
            'active' => true,
        ]);
        $moneySource->branches()->attach($this->tenantBranch->id);

        $this->actingAsCompanyAdmin()
            ->post(route('purchases.store'), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'payment_selection' => 'credit',
                'paid_amount' => 0,
                'subtotal' => 100,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 100,
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => 1,
                    'unit_id' => 'kg',
                    'unit_price' => 100,
                    'expiry_date' => null,
                ]],
            ])
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotNull($purchase);

        $this->actingAsCompanyAdmin()
            ->post(route('supplier-payments.store'), [
                'supplier_id' => $supplier->id,
                'branch_id' => $this->tenantBranch->id,
                'account_id' => Account::withoutGlobalScopes()->where('name', 'Purchase')->value('id'),
                'money_source_id' => $moneySource->id,
                'payment_date' => now()->toDateString(),
                'total_amount' => 100,
            ])
            ->assertRedirect(route('supplier-payments.index'));

        $purchase->refresh();
        $this->assertTrue($purchase->hasAdditionalSupplierPayments());

        return [$purchase, $supplier, $ingredient];
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
