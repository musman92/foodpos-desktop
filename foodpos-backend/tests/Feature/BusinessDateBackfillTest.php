<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\Order;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Services\BusinessDateBackfillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BusinessDateSpikeHelpers;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class BusinessDateBackfillTest extends TestCase
{
    use BusinessDateSpikeHelpers;
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->actingAsCompanyAdmin();
    }

    public function test_new_order_with_shift_stamps_business_date_from_shift_date(): void
    {
        $shift = $this->createOvernightShift();

        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'shift_id' => $shift->id,
            'order_number' => 'BD-STAMP-001',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 100,
        ]);

        $this->assertSame(self::BUSINESS_DAY, $order->fresh()->business_date?->format('Y-m-d'));
    }

    public function test_backfill_sets_business_date_from_shift_id(): void
    {
        $shift = Shift::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'opened_by' => $this->companyAdmin->id,
            'shift_date' => self::BUSINESS_DAY,
            'opened_at' => Carbon::parse(self::BUSINESS_DAY.' 16:00:00', 'Asia/Karachi')->utc(),
            'status' => 'active',
            'expected_cash' => 0,
            'cash_difference' => 0,
        ]);

        $orderId = Order::withoutGlobalScopes()->insertGetId([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'shift_id' => $shift->id,
            'order_number' => 'BD-BACKFILL-001',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => 50,
            'paid_amount' => 50,
            'business_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(Order::withoutGlobalScopes()->find($orderId)?->business_date);

        $drySummary = app(BusinessDateBackfillService::class)->backfill($this->tenantCompany->id, dryRun: true);
        $this->assertGreaterThanOrEqual(1, $drySummary['orders']['shift']);
        $this->assertNull(
            Order::withoutGlobalScopes()->find($orderId)?->business_date,
            'Dry run must not persist business_date'
        );

        $summary = app(BusinessDateBackfillService::class)->backfill($this->tenantCompany->id);

        $this->assertSame(self::BUSINESS_DAY, Order::withoutGlobalScopes()->find($orderId)?->business_date?->format('Y-m-d'));
        $this->assertGreaterThanOrEqual(1, $summary['orders']['shift']);
        $this->assertSame(0, $summary['orders']['remaining']);
    }

    public function test_backfill_stock_movement_from_shift_window(): void
    {
        $this->createOvernightShift();
        $ingredient = $this->createIngredient();
        $at = $this->overnightAt();

        $movementId = StockMovement::withoutGlobalScopes()->insertGetId([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => 'adjustment',
            'movement' => 'out',
            'quantity' => 3,
            'unit_id' => 'g',
            'unit_cost' => 1,
            'created_by' => $this->companyAdmin->id,
            'business_date' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        app(BusinessDateBackfillService::class)->backfill($this->tenantCompany->id);

        $this->assertSame(
            self::BUSINESS_DAY,
            StockMovement::withoutGlobalScopes()->find($movementId)?->business_date?->format('Y-m-d')
        );
    }

    private function createIngredient(): Ingredient
    {
        $gramUnit = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'g-bf',
        ]);

        $kgUnit = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Kilogram',
            'code' => 'kg-bf',
        ]);

        return Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Backfill Flour',
            'sku' => 'BF-FL',
            'base_unit_id' => 'g',
            'consumption_unit_id' => $gramUnit->id,
            'purchase_unit_id' => $kgUnit->id,
            'conversion_rate' => 1000,
            'purchase_price' => 10,
            'cost_per_unit' => 0.01,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);
    }
}
