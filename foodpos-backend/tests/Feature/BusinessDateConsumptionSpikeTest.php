<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\StockMovement;
use App\Support\ConsumptionReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BusinessDateSpikeHelpers;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Overnight consumption: calendar created_at misses early-morning sales;
 * business_date filtering (now wired into ConsumptionReport) includes them.
 */
class BusinessDateConsumptionSpikeTest extends TestCase
{
    use BusinessDateSpikeHelpers;
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_overnight_sale_is_missed_by_calendar_filter_but_included_by_business_date(): void
    {
        $this->actingAsCompanyAdmin();

        $ingredient = $this->createIngredient('Burger Bund', 'BB-SPIKE');
        $shift = $this->createOvernightShift();

        $evening = $this->createSaleMovement($ingredient, quantity: 2.0, at: $this->eveningAt());
        $overnight = $this->createSaleMovement($ingredient, quantity: 5.0, at: $this->overnightAt());

        $this->assertNull($evening->business_date);
        $this->assertNull($overnight->business_date);

        $calendarBefore = ConsumptionReport::build(
            $this->companyAdmin,
            $this->tenantBranch->id,
            self::BUSINESS_DAY,
            self::BUSINESS_DAY
        );

        $this->assertSame(
            2.0,
            $this->qtyForIngredient($calendarBefore, $ingredient->id),
            'Without business_date, hybrid filter falls back to created_at and misses overnight.'
        );

        $this->stampBusinessDate($evening);
        $this->stampBusinessDate($overnight);

        $this->assertSame(self::BUSINESS_DAY, $evening->fresh()->business_date?->format('Y-m-d'));
        $this->assertSame(self::BUSINESS_DAY, $overnight->fresh()->business_date?->format('Y-m-d'));
        $this->assertSame(self::BUSINESS_DAY, $shift->shift_date->format('Y-m-d'));

        $afterStamp = ConsumptionReport::build(
            $this->companyAdmin,
            $this->tenantBranch->id,
            self::BUSINESS_DAY,
            self::BUSINESS_DAY
        );

        $this->assertSame(
            7.0,
            $this->qtyForIngredient($afterStamp, $ingredient->id),
            'With business_date stamped, ConsumptionReport includes evening + overnight.'
        );
    }

    private function createSaleMovement(Ingredient $ingredient, float $quantity, $at): StockMovement
    {
        $movement = StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'sale',
            'movement' => 'out',
            'quantity' => $quantity,
            'unit_id' => 'g',
            'unit_cost' => 39,
            'notes' => 'Spike overnight consumption',
            'created_by' => $this->companyAdmin->id,
        ]);

        return $this->pinCreatedAt($movement, $at);
    }

    private function createIngredient(string $name, string $sku): Ingredient
    {
        $gramUnit = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g',
        ]);

        $kgUnit = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Kilogram',
            'code' => 'kg',
        ]);

        return Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => $name,
            'sku' => $sku,
            'base_unit_id' => 'g',
            'consumption_unit_id' => $gramUnit->id,
            'purchase_unit_id' => $kgUnit->id,
            'conversion_rate' => 1000,
            'purchase_price' => 39,
            'cost_per_unit' => 0.039,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{summary: array<string, mixed>, rows: \Illuminate\Support\Collection}  $report
     */
    private function qtyForIngredient(array $report, int $ingredientId): float
    {
        $row = $report['rows']->first(
            fn (array $row) => $row['item_type'] === 'ingredient' && $row['item_id'] === $ingredientId
        );

        return $row ? (float) $row['quantity'] : 0.0;
    }
}
