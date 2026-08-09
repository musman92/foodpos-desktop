<?php

namespace Tests\Feature;

use App\Helpers\TenantDefaultRoles;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\TenantRoleBootstrapService;
use App\Support\IngredientLedgerReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class IngredientLedgerReportTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_ingredient_ledger_requires_consumption_permission(): void
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
            ->get(route('reports.ingredient-ledger'))
            ->assertForbidden();
    }

    public function test_ingredient_ledger_combines_purchases_sales_and_adjustments(): void
    {
        $units = $this->createUnits();
        $gramUom = UnitOfMeasure::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'abbreviation' => 'g',
            'type' => 'weight',
            'is_base_unit' => true,
        ]);
        $ingredient = $this->createIngredient('Ch Bone Less', 'CBL01', $units['purchase'], $units['consumption'], 1000, 2000);

        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Meat Supplier',
            'code' => 'SUP-CBL',
            'status' => 'active',
        ]);

        $t0 = now()->copy()->seconds(0);
        $bizDate = $t0->toDateString();

        $purchase = Purchase::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->companyAdmin->id,
            'purchase_number' => 'PO-CBL-1',
            'purchase_date' => $bizDate,
            'business_date' => $bizDate,
            'subtotal' => 2000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 2000,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'item_type' => 'ingredient',
            'item_id' => $ingredient->id,
            'quantity' => 1,
            'unit_id' => (string) $units['purchase']->id,
            'unit_price' => 2000,
            'total_price' => 2000,
        ]);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 700,
            'reserved_quantity' => 0,
            'unit_id' => $gramUom->id,
            'average_cost' => 2,
            'last_restocked_at' => $t0,
        ]);

        $sale = StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'sale',
            'movement' => 'out',
            'quantity' => 250,
            'unit_id' => 'g',
            'unit_cost' => 2,
            'notes' => 'Finalized for completed order #ORD-1',
            'created_by' => $this->companyAdmin->id,
            'business_date' => $bizDate,
        ]);

        $adjustment = StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'adjustment',
            'movement' => 'out',
            'quantity' => 50,
            'unit_id' => 'g',
            'unit_cost' => 2,
            'notes' => 'Waste',
            'created_by' => $this->companyAdmin->id,
            'business_date' => $bizDate,
        ]);

        DB::table('purchases')->where('id', $purchase->id)->update([
            'created_at' => $t0,
            'updated_at' => $t0,
        ]);
        DB::table('stock_movements')->where('id', $sale->id)->update([
            'created_at' => $t0->copy()->addMinutes(1),
            'updated_at' => $t0->copy()->addMinutes(1),
        ]);
        DB::table('stock_movements')->where('id', $adjustment->id)->update([
            'created_at' => $t0->copy()->addMinutes(2),
            'updated_at' => $t0->copy()->addMinutes(2),
        ]);

        $from = $t0->copy()->startOfMonth()->toDateString();
        $to = $bizDate;

        $ledger = IngredientLedgerReport::build(
            $this->companyAdmin,
            (int) $ingredient->id,
            (int) $this->tenantBranch->id,
            $from,
            $to
        );

        $this->assertNotNull($ledger);
        $this->assertSame('Ch Bone Less', $ledger['ingredient']['name']);
        $this->assertSame(1000.0, $ledger['summary']['purchased_qty']);
        $this->assertSame(250.0, $ledger['summary']['sold_qty']);
        $this->assertSame(50.0, $ledger['summary']['adjusted_out_qty']);
        $this->assertSame(700.0, $ledger['summary']['current_qty']);
        $this->assertSame(1.0, $ledger['summary']['purchased_purchase_qty']);
        $this->assertSame(0.7, $ledger['summary']['current_purchase_qty']);

        $kinds = $ledger['rows']->pluck('kind')->all();
        $this->assertSame(['purchase', 'sale', 'adjustment_out'], $kinds);

        $this->assertSame(1000.0, $ledger['rows'][0]['signed_qty']);
        $this->assertSame(-250.0, $ledger['rows'][1]['signed_qty']);
        $this->assertSame(-50.0, $ledger['rows'][2]['signed_qty']);
        $this->assertSame(700.0, $ledger['rows']->last()['balance_qty']);

        $panel = $this->actingAsCompanyAdmin()
            ->getJson(route('reports.panel', [
                'report' => 'ingredient-ledger',
                'ingredient_id' => $ingredient->id,
                'branch_id' => $this->tenantBranch->id,
                'from' => $from,
                'to' => $to,
            ]));

        $panel->assertOk();
        $html = $panel->json('html');
        $this->assertStringContainsString('Ch Bone Less', $html);
        $this->assertStringContainsString('Purchase', $html);
        $this->assertStringContainsString('Sale', $html);
        $this->assertStringContainsString('Adjustment out', $html);
        $this->assertStringContainsString('PO-CBL-1', $html);
    }

    public function test_ingredient_ledger_includes_purchase_returns(): void
    {
        $this->openTenantShift();
        $units = $this->createUnits();
        $ingredient = $this->createIngredient('Chicken', 'CHK-RET', $units['purchase'], $units['consumption'], 1000, 500);

        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Poultry Co',
            'code' => 'SUP-CHK',
            'status' => 'active',
            'balance' => 0,
        ]);

        $purchase = app(\App\Services\PurchaseService::class)->createPurchase(
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
                'unit_id' => (string) $units['purchase']->id,
                'unit_price' => 500,
                'expiry_date' => null,
                'notes' => null,
            ]],
            $this->companyAdmin->id,
        );

        $item = $purchase->items()->first();
        $return = app(\App\Services\PurchaseReturnService::class)->createReturn(
            $purchase,
            [[
                'purchase_item_id' => $item->id,
                'quantity' => 2,
            ]],
            $this->companyAdmin->id,
            'Bad condition',
        );

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $ledger = IngredientLedgerReport::build(
            $this->companyAdmin,
            (int) $ingredient->id,
            (int) $this->tenantBranch->id,
            $from,
            $to
        );

        $this->assertNotNull($ledger);
        $this->assertSame(10000.0, $ledger['summary']['purchased_qty']);
        $this->assertSame(2000.0, $ledger['summary']['returned_qty']);
        $this->assertContains('purchase_return', $ledger['rows']->pluck('kind')->all());
        $this->assertSame($return->return_number, $ledger['rows']->firstWhere('kind', 'purchase_return')['reference_label']);
    }

    /**
     * @return array{purchase: IngredientUnit, consumption: IngredientUnit}
     */
    private function createUnits(): array
    {
        $purchase = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Kilogram',
            'code' => 'kg',
        ]);
        $consumption = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g',
        ]);

        return ['purchase' => $purchase, 'consumption' => $consumption];
    }

    private function createIngredient(
        string $name,
        string $sku,
        IngredientUnit $purchaseUnit,
        IngredientUnit $consumptionUnit,
        float $conversionRate,
        float $purchasePrice
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
}
