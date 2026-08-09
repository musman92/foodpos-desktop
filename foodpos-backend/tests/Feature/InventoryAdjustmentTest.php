<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_manual_ingredient_adjustment_uses_units_of_measure_id_for_branch_stock(): void
    {
        $gramUnit = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g-'.uniqid(),
        ]);

        $ingredient = $this->createTrackedIngredient('Black Paper', 'BP01', $gramUnit);

        $this->actingAsCompanyAdmin();

        app(InventoryService::class)->adjustIngredientStockManually(
            (int) $this->tenantBranch->id,
            (int) $ingredient->id,
            10,
            (int) $this->companyAdmin->id,
            'Opening stock'
        );

        $branchStock = BranchStock::query()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->first();

        $this->assertNotNull($branchStock);
        $this->assertIsInt((int) $branchStock->unit_id);
        $this->assertSame(10.0, (float) $branchStock->quantity);
        $this->assertDatabaseHas('units_of_measure', [
            'id' => $branchStock->unit_id,
            'abbreviation' => $gramUnit->code,
        ]);

        $this->assertSame(1, StockMovement::query()
            ->where('ingredient_id', $ingredient->id)
            ->where('type', 'adjustment')
            ->count());
    }

    public function test_stock_endpoint_returns_current_quantity_in_both_units(): void
    {
        [$gram, $kg] = $this->createGramAndKgUnits();
        $ingredient = $this->createTrackedIngredient('Flour', 'FL01', $gram, $kg, 1000);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 2500,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 0.01,
        ]);

        $response = $this->actingAsCompanyAdmin()->getJson(route('inventory.adjustment.stock', [
            'branch_id' => $this->tenantBranch->id,
            'adjustable' => 'ingredient',
            'ingredient_id' => $ingredient->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('quantity_consumption', 2500);
        $response->assertJsonPath('quantity_purchase', 2.5);
        $response->assertJsonPath('has_dual_units', true);
        $response->assertJsonPath('consumption_unit_name', 'Gram');
        $response->assertJsonPath('purchase_unit_name', 'Kilogram');
    }

    public function test_change_mode_in_purchase_unit_converts_to_consumption(): void
    {
        [$gram, $kg] = $this->createGramAndKgUnits();
        $ingredient = $this->createTrackedIngredient('Sugar', 'SG01', $gram, $kg, 1000);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 1000,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 0.01,
        ]);

        $response = $this->actingAsCompanyAdmin()->post(route('inventory.adjustment.store'), [
            'branch_id' => $this->tenantBranch->id,
            'adjustable' => 'ingredient',
            'ingredient_id' => $ingredient->id,
            'mode' => 'change',
            'unit' => 'purchase',
            'quantity' => -0.5,
            'notes' => 'Spoilage half kg',
        ]);

        $response->assertRedirect();
        $this->assertSame(500.0, (float) BranchStock::query()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));
    }

    public function test_exact_mode_sets_absolute_stock(): void
    {
        $gram = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g-'.uniqid(),
        ]);
        $ingredient = $this->createTrackedIngredient('Salt', 'ST01', $gram);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 80,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 0.01,
        ]);

        $response = $this->actingAsCompanyAdmin()->post(route('inventory.adjustment.store'), [
            'branch_id' => $this->tenantBranch->id,
            'adjustable' => 'ingredient',
            'ingredient_id' => $ingredient->id,
            'mode' => 'exact',
            'unit' => 'consumption',
            'quantity' => 50,
            'notes' => 'Stock count',
        ]);

        $response->assertRedirect();
        $this->assertSame(50.0, (float) BranchStock::query()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->sum('quantity'));
    }

    public function test_exact_mode_deducts_across_ingredient_batches_fifo(): void
    {
        [$gram, $kg] = $this->createGramAndKgUnits();
        $ingredient = $this->createTrackedIngredient('Chicken', 'CHK1', $gram, $kg, 1000);

        $oldest = now()->subDays(3);
        $middle = now()->subDays(2);
        $newest = now()->subDay();

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 5000,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 1.00,
            'last_restocked_at' => $oldest,
            'created_at' => $oldest,
            'updated_at' => $oldest,
        ]);
        $middleBatch = BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 7000,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 2.00,
            'last_restocked_at' => $middle,
            'created_at' => $middle,
            'updated_at' => $middle,
        ]);
        $newestBatch = BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 4000,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 3.00,
            'last_restocked_at' => $newest,
            'created_at' => $newest,
            'updated_at' => $newest,
        ]);

        $response = $this->actingAsCompanyAdmin()->post(route('inventory.adjustment.store'), [
            'branch_id' => $this->tenantBranch->id,
            'adjustable' => 'ingredient',
            'ingredient_id' => $ingredient->id,
            'mode' => 'exact',
            'unit' => 'purchase',
            'quantity' => 6.3,
            'notes' => 'Physical count',
        ]);

        $response->assertRedirect();

        $this->assertNull(BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->where('average_cost', 1.00)
            ->first());
        $this->assertEqualsWithDelta(2300.0, (float) $middleBatch->fresh()->quantity, 0.01);
        $this->assertEqualsWithDelta(4000.0, (float) $newestBatch->fresh()->quantity, 0.01);
        $this->assertEqualsWithDelta(
            6300.0,
            (float) BranchStock::withoutGlobalScopes()
                ->where('branch_id', $this->tenantBranch->id)
                ->where('ingredient_id', $ingredient->id)
                ->sum('quantity'),
            0.01
        );
    }

    public function test_increase_adjustment_adds_to_latest_ingredient_batch(): void
    {
        [$gram, $kg] = $this->createGramAndKgUnits();
        $ingredient = $this->createTrackedIngredient('Beef', 'BF01', $gram, $kg, 1000);

        $oldest = now()->subDays(2);
        $newest = now()->subDay();

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 3000,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 1.00,
            'last_restocked_at' => $oldest,
            'created_at' => $oldest,
        ]);
        $newestBatch = BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 2000,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 2.00,
            'last_restocked_at' => $newest,
            'created_at' => $newest,
        ]);

        $this->actingAsCompanyAdmin()->post(route('inventory.adjustment.store'), [
            'branch_id' => $this->tenantBranch->id,
            'adjustable' => 'ingredient',
            'ingredient_id' => $ingredient->id,
            'mode' => 'change',
            'unit' => 'purchase',
            'quantity' => 1,
            'notes' => 'Found extra stock',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(3000.0, (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->where('average_cost', 1.00)
            ->value('quantity'), 0.01);
        $this->assertEqualsWithDelta(3000.0, (float) $newestBatch->fresh()->quantity, 0.01);
    }

    public function test_adjustment_index_lists_latest_first(): void
    {
        $gram = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g-'.uniqid(),
        ]);
        $ingredient = $this->createTrackedIngredient('Cumin', 'CM01', $gram);
        $unitId = (string) $this->ensureUomId($gram);

        $oldest = now()->subDays(3);
        $middle = now()->subDays(2);
        $newest = now()->subDay();

        $oldestMovement = StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'adjustment',
            'movement' => 'in',
            'quantity' => 1,
            'unit_id' => $unitId,
            'notes' => 'Oldest',
            'created_by' => $this->companyAdmin->id,
            'created_at' => $oldest,
            'updated_at' => $oldest,
        ]);
        $middleMovement = StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'adjustment',
            'movement' => 'in',
            'quantity' => 2,
            'unit_id' => $unitId,
            'notes' => 'Middle',
            'created_by' => $this->companyAdmin->id,
            'created_at' => $middle,
            'updated_at' => $middle,
        ]);
        $newestMovement = StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'adjustment',
            'movement' => 'in',
            'quantity' => 3,
            'unit_id' => $unitId,
            'notes' => 'Newest',
            'created_by' => $this->companyAdmin->id,
            'created_at' => $newest,
            'updated_at' => $newest,
        ]);

        $response = $this->actingAsCompanyAdmin()->get(route('inventory.adjustment.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Newest', 'Middle', 'Oldest']);
    }

    public function test_edit_adjustment_replaces_stock_effect(): void
    {
        $gram = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g-'.uniqid(),
        ]);
        $ingredient = $this->createTrackedIngredient('Oil', 'OL01', $gram);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 0.01,
        ]);

        $this->actingAsCompanyAdmin()->post(route('inventory.adjustment.store'), [
            'branch_id' => $this->tenantBranch->id,
            'adjustable' => 'ingredient',
            'ingredient_id' => $ingredient->id,
            'mode' => 'change',
            'unit' => 'consumption',
            'quantity' => 20,
            'notes' => 'First count',
        ])->assertRedirect();

        $movement = StockMovement::query()
            ->where('ingredient_id', $ingredient->id)
            ->where('type', 'adjustment')
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(120.0, (float) BranchStock::query()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));

        $this->actingAsCompanyAdmin()->put(route('inventory.adjustment.update', $movement), [
            'mode' => 'change',
            'unit' => 'consumption',
            'quantity' => 5,
            'notes' => 'Corrected count',
        ])->assertRedirect();

        $this->assertDatabaseMissing('stock_movements', ['id' => $movement->id]);
        $this->assertSame(1, StockMovement::query()
            ->where('ingredient_id', $ingredient->id)
            ->where('type', 'adjustment')
            ->count());
        $this->assertSame(105.0, (float) BranchStock::query()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'ingredient_id' => $ingredient->id,
            'type' => 'adjustment',
            'notes' => 'Corrected count',
        ]);
    }

    public function test_delete_adjustment_reverses_stock(): void
    {
        $gram = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g-'.uniqid(),
        ]);
        $ingredient = $this->createTrackedIngredient('Pepper', 'PP01', $gram);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 40,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 0.01,
        ]);

        $this->actingAsCompanyAdmin();

        app(InventoryService::class)->adjustIngredientStockManually(
            (int) $this->tenantBranch->id,
            (int) $ingredient->id,
            -15,
            (int) $this->companyAdmin->id,
            'Spoilage'
        );

        $movement = StockMovement::query()
            ->where('ingredient_id', $ingredient->id)
            ->where('type', 'adjustment')
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(25.0, (float) BranchStock::query()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));

        $this->delete(route('inventory.adjustment.destroy', $movement))
            ->assertRedirect();

        $this->assertDatabaseMissing('stock_movements', ['id' => $movement->id]);
        $this->assertSame(40.0, (float) BranchStock::query()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('ingredient_id', $ingredient->id)
            ->value('quantity'));
    }

    public function test_stock_endpoint_excludes_movement_being_edited(): void
    {
        $gram = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g-'.uniqid(),
        ]);
        $ingredient = $this->createTrackedIngredient('Rice', 'RC01', $gram);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 200,
            'unit_id' => $this->ensureUomId($gram),
            'average_cost' => 0.01,
        ]);

        $movement = StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'adjustment',
            'movement' => 'in',
            'quantity' => 50,
            'unit_id' => (string) $this->ensureUomId($gram),
            'notes' => 'Opening',
            'created_by' => $this->companyAdmin->id,
        ]);

        $response = $this->actingAsCompanyAdmin()->getJson(route('inventory.adjustment.stock', [
            'branch_id' => $this->tenantBranch->id,
            'adjustable' => 'ingredient',
            'ingredient_id' => $ingredient->id,
            'exclude_movement_id' => $movement->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('quantity_consumption', 150);
    }

    /**
     * @return array{0: IngredientUnit, 1: IngredientUnit}
     */
    private function createGramAndKgUnits(): array
    {
        $gram = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g-'.uniqid(),
        ]);
        $kg = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Kilogram',
            'code' => 'kg-'.uniqid(),
        ]);

        return [$gram, $kg];
    }

    private function createTrackedIngredient(
        string $name,
        string $sku,
        IngredientUnit $consumptionUnit,
        ?IngredientUnit $purchaseUnit = null,
        float $conversionRate = 1
    ): Ingredient {
        $purchaseUnit ??= $consumptionUnit;

        return Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => $name,
            'sku' => $sku,
            'base_unit_id' => (string) $consumptionUnit->id,
            'consumption_unit_id' => $consumptionUnit->id,
            'purchase_unit_id' => $purchaseUnit->id,
            'conversion_rate' => $conversionRate,
            'purchase_price' => 100,
            'cost_per_unit' => 0.01,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);
    }

    private function ensureUomId(IngredientUnit $unit): int
    {
        $uom = \App\Models\UnitOfMeasure::withoutGlobalScopes()->firstOrCreate(
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
