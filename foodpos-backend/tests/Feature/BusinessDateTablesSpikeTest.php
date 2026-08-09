<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MoneySource;
use App\Models\MoneySourceFundMovement;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BusinessDateSpikeHelpers;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Spike coverage for every table that should eventually carry business_date.
 * Columns are added only in the test DB — no production migrations yet.
 */
class BusinessDateTablesSpikeTest extends TestCase
{
    use BusinessDateSpikeHelpers;
    use CreatesTestTenant;
    use RefreshDatabase;

    private Account $salesAccount;

    private MoneySource $cashSource;

    private MoneySource $bankSource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->actingAsCompanyAdmin();

        $this->salesAccount = Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);

        $this->cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash Drawer',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $this->cashSource->branches()->attach($this->tenantBranch->id);

        $this->bankSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Bank',
            'type' => 'BANK',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $this->bankSource->branches()->attach($this->tenantBranch->id);
    }

    public function test_orders_overnight_row_needs_business_date_filter(): void
    {
        $shift = $this->createOvernightShift();

        $evening = $this->createOrder(amount: 100.0, shiftId: $shift->id, at: $this->eveningAt(), suffix: 'EVE');
        $overnight = $this->createOrder(amount: 250.0, shiftId: $shift->id, at: $this->overnightAt(), suffix: 'ONT');

        $this->assertCalendarMissesOvernightButBusinessDateIncludesBoth(
            'orders',
            'total_amount',
            100.0,
            250.0,
            $evening,
            $overnight
        );
    }

    public function test_transactions_overnight_row_needs_business_date_filter(): void
    {
        $shift = $this->createOvernightShift();

        // Intentionally stamp legacy `date` as calendar day of the event (wrong for overnight),
        // so we prove business_date is the column reports should prefer.
        $evening = $this->createTransaction(amount: 100.0, shiftId: $shift->id, at: $this->eveningAt(), calendarDate: self::BUSINESS_DAY);
        $overnight = $this->createTransaction(amount: 250.0, shiftId: $shift->id, at: $this->overnightAt(), calendarDate: self::NEXT_CALENDAR_DAY);

        $this->assertSame(
            100.0,
            (float) Transaction::query()
                ->where('branch_id', $this->tenantBranch->id)
                ->whereDate('date', self::BUSINESS_DAY)
                ->sum('amount'),
            'Existing transactions.date set from wall-clock also misses overnight when stamped as next calendar day.'
        );

        $this->assertCalendarMissesOvernightButBusinessDateIncludesBoth(
            'transactions',
            'amount',
            100.0,
            250.0,
            $evening,
            $overnight
        );
    }

    public function test_purchases_overnight_row_needs_business_date_filter(): void
    {
        $shift = $this->createOvernightShift();
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Spike Supplier',
            'code' => 'SUP-SPIKE',
            'status' => 'active',
        ]);

        $evening = $this->createPurchase(
            amount: 400.0,
            shiftId: $shift->id,
            supplierId: $supplier->id,
            at: $this->eveningAt(),
            purchaseDate: self::BUSINESS_DAY,
            number: 'PO-EVE-001'
        );
        $overnight = $this->createPurchase(
            amount: 600.0,
            shiftId: $shift->id,
            supplierId: $supplier->id,
            at: $this->overnightAt(),
            purchaseDate: self::NEXT_CALENDAR_DAY,
            number: 'PO-ONT-001'
        );

        $this->assertSame(
            400.0,
            (float) Purchase::query()
                ->where('branch_id', $this->tenantBranch->id)
                ->whereDate('purchase_date', self::BUSINESS_DAY)
                ->sum('total_amount'),
            'purchase_date from wall-clock calendar misses overnight purchase.'
        );

        $this->assertCalendarMissesOvernightButBusinessDateIncludesBoth(
            'purchases',
            'total_amount',
            400.0,
            600.0,
            $evening,
            $overnight
        );
    }

    public function test_money_source_fund_movements_overnight_row_needs_business_date_filter(): void
    {
        $shift = $this->createOvernightShift();

        $evening = $this->createFundMovement(amount: 50.0, shiftId: $shift->id, at: $this->eveningAt(), movementDate: self::BUSINESS_DAY);
        $overnight = $this->createFundMovement(amount: 75.0, shiftId: $shift->id, at: $this->overnightAt(), movementDate: self::NEXT_CALENDAR_DAY);

        $this->assertSame(
            50.0,
            (float) MoneySourceFundMovement::query()
                ->where('branch_id', $this->tenantBranch->id)
                ->whereDate('movement_date', self::BUSINESS_DAY)
                ->sum('amount'),
            'movement_date from wall-clock calendar misses overnight fund movement.'
        );

        $this->assertCalendarMissesOvernightButBusinessDateIncludesBoth(
            'money_source_fund_movements',
            'amount',
            50.0,
            75.0,
            $evening,
            $overnight
        );
    }

    public function test_stock_movements_sale_overnight_row_needs_business_date_filter(): void
    {
        $this->createOvernightShift();
        $ingredient = $this->createIngredient('Spike Bun', 'BB-TBL');

        $evening = $this->createStockOut($ingredient, quantity: 2.0, type: 'sale', at: $this->eveningAt());
        $overnight = $this->createStockOut($ingredient, quantity: 5.0, type: 'sale', at: $this->overnightAt());

        $this->assertCalendarMissesOvernightButBusinessDateIncludesBoth(
            'stock_movements',
            'quantity',
            2.0,
            5.0,
            $evening,
            $overnight
        );
    }

    public function test_stock_movements_adjustment_overnight_row_needs_business_date_filter(): void
    {
        $this->createOvernightShift();
        $ingredient = $this->createIngredient('Spike Flour', 'FL-TBL');

        $evening = $this->createStockOut($ingredient, quantity: 1.0, type: 'adjustment', at: $this->eveningAt());
        $overnight = $this->createStockOut($ingredient, quantity: 3.0, type: 'adjustment', at: $this->overnightAt());

        $this->assertCalendarMissesOvernightButBusinessDateIncludesBoth(
            'stock_movements',
            'quantity',
            1.0,
            3.0,
            $evening,
            $overnight
        );
    }

    private function createOrder(float $amount, int $shiftId, $at, string $suffix): Order
    {
        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'shift_id' => $shiftId,
            'order_number' => 'BD-ORD-'.$suffix,
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => $amount,
            'total_amount' => $amount,
            'paid_amount' => $amount,
        ]);

        return $this->pinCreatedAt($order, $at);
    }

    private function createTransaction(float $amount, int $shiftId, $at, string $calendarDate): Transaction
    {
        $txn = Transaction::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $this->salesAccount->id,
            'amount' => $amount,
            'type' => 'in',
            'payment_method' => 'cash',
            'money_source_id' => $this->cashSource->id,
            'reference_type' => 'sale',
            'date' => $calendarDate,
            'created_by' => $this->companyAdmin->id,
            'shift_id' => $shiftId,
        ]);

        return $this->pinCreatedAt($txn, $at);
    }

    private function createPurchase(
        float $amount,
        int $shiftId,
        int $supplierId,
        $at,
        string $purchaseDate,
        string $number
    ): Purchase {
        $purchase = Purchase::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplierId,
            'created_by' => $this->companyAdmin->id,
            'shift_id' => $shiftId,
            'purchase_number' => $number,
            'purchase_date' => $purchaseDate,
            'subtotal' => $amount,
            'total_amount' => $amount,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        return $this->pinCreatedAt($purchase, $at);
    }

    private function createFundMovement(float $amount, int $shiftId, $at, string $movementDate): MoneySourceFundMovement
    {
        $movement = MoneySourceFundMovement::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'from_money_source_id' => $this->cashSource->id,
            'to_money_source_id' => $this->bankSource->id,
            'movement_type' => 'owner_withdrawal',
            'amount' => $amount,
            'movement_date' => $movementDate,
            'created_by' => $this->companyAdmin->id,
            'shift_id' => $shiftId,
            'notes' => 'Spike fund movement',
        ]);

        return $this->pinCreatedAt($movement, $at);
    }

    private function createStockOut(Ingredient $ingredient, float $quantity, string $type, $at): StockMovement
    {
        $movement = StockMovement::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'type' => $type,
            'movement' => 'out',
            'quantity' => $quantity,
            'unit_id' => 'g',
            'unit_cost' => 10,
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Spike stock movement',
        ]);

        return $this->pinCreatedAt($movement, $at);
    }

    private function createIngredient(string $name, string $sku): Ingredient
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
            'purchase_price' => 100,
            'cost_per_unit' => 0.1,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);
    }
}
