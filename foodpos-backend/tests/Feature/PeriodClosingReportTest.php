<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\UnitOfMeasure;
use App\Support\PeriodClosingReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class PeriodClosingReportTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_weekly_closing_shows_available_stock_instead_of_purchases(): void
    {
        $kg = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Kilogram',
            'abbreviation' => 'kg',
            'type' => 'weight',
            'is_base_unit' => false,
        ]);
        $gram = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'abbreviation' => 'g',
            'type' => 'weight',
            'is_base_unit' => true,
        ]);
        $gramUom = UnitOfMeasure::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'abbreviation' => 'g',
            'type' => 'weight',
            'is_base_unit' => true,
        ]);

        $ingredient = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Flour',
            'sku' => 'FLR01',
            'base_unit_id' => (string) $gram->id,
            'purchase_unit_id' => $kg->id,
            'consumption_unit_id' => $gram->id,
            'conversion_rate' => 1000,
            'purchase_price' => 50,
            'cost_per_unit' => 0.05,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);

        BranchStock::withoutGlobalScopes()->create([
            'branch_id' => $this->tenantBranch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 2500,
            'reserved_quantity' => 500,
            'unit_id' => $gramUom->id,
            'average_cost' => 0.05,
        ]);

        $weekOf = PeriodClosingReport::defaultWeekOf((int) $this->tenantBranch->id, $this->companyAdmin);
        $periods = PeriodClosingReport::resolveWeeklyPeriods($weekOf, 1, (int) $this->tenantBranch->id, $this->companyAdmin);
        $report = PeriodClosingReport::build($this->companyAdmin, (int) $this->tenantBranch->id, $periods);

        $section = $report['periods'][0];
        $this->assertArrayHasKey('stock', $section);
        $this->assertArrayNotHasKey('purchases', $section);

        $this->assertCount(1, $section['stock']);
        $this->assertTrue($section['show_stock']);
        $this->assertNotNull($section['closing']['stock_in_hand']);
        $line = $section['stock'][0];
        $this->assertSame('Flour', $line['product']);
        // Available = 2500 - 500 = 2000 g → 2 kg purchase qty; amount = 2000 * 0.05
        $this->assertEquals(2.0, $line['qty']);
        $this->assertEquals(100.0, $line['amount']);
        $this->assertEquals(50.0, $line['rate']);
        $this->assertEquals(100.0, $section['stock_total']);
        $this->assertEquals($section['stock_total'], $section['closing']['stock_in_hand']);

        $response = $this->actingAsCompanyAdmin()
            ->getJson(route('reports.panel', [
                'report' => 'weekly-closing',
                'branch_id' => $this->tenantBranch->id,
                'week_of' => $weekOf,
                'week_count' => 1,
            ]));

        $response->assertOk()->assertJsonPath('key', 'weekly-closing');
        $html = $response->json('html');
        $this->assertStringContainsString('Available stock', $html);
        $this->assertStringContainsString('Flour', $html);
        $this->assertStringNotContainsString('No purchases in this period.', $html);
    }

    public function test_multi_week_closing_shows_available_stock_only_once(): void
    {
        $weekOf = PeriodClosingReport::defaultWeekOf((int) $this->tenantBranch->id, $this->companyAdmin);
        $periods = PeriodClosingReport::resolveWeeklyPeriods($weekOf, 2, (int) $this->tenantBranch->id, $this->companyAdmin);
        $report = PeriodClosingReport::build($this->companyAdmin, (int) $this->tenantBranch->id, $periods);

        $this->assertCount(2, $report['periods']);
        $this->assertTrue($report['periods'][0]['show_stock']);
        $this->assertFalse($report['periods'][1]['show_stock']);
        $this->assertSame([], $report['periods'][1]['stock']);
        $this->assertNull($report['periods'][0]['closing']['stock_in_hand']);
        $this->assertNotNull($report['periods'][1]['closing']['stock_in_hand']);

        $html = $this->actingAsCompanyAdmin()
            ->getJson(route('reports.panel', [
                'report' => 'weekly-closing',
                'branch_id' => $this->tenantBranch->id,
                'week_of' => $weekOf,
                'week_count' => 2,
            ]))
            ->assertOk()
            ->json('html');

        $this->assertSame(1, substr_count($html, '>Available stock<'));
    }

    public function test_closing_cogs_uses_sold_item_cost_not_purchases(): void
    {
        $category = \App\Models\Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Mains',
            'code' => 'MAIN',
            'slug' => 'mains-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $menuItem = \App\Models\MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Burger',
            'slug' => 'burger-'.uniqid(),
            'sku' => 'BRG-'.uniqid(),
            'type' => 'single',
            'price' => 500,
            'cost' => 120,
            'is_available' => true,
            'track_inventory' => false,
        ]);

        $supplier = \App\Models\Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Supplier',
            'code' => 'SUP-COGS',
            'status' => 'active',
        ]);

        // Large purchase in period — must NOT drive closing COGS.
        \App\Models\Purchase::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->companyAdmin->id,
            'purchase_number' => 'PO-COGS-1',
            'purchase_date' => local_today($this->tenantBranch->id),
            'business_date' => local_today($this->tenantBranch->id),
            'subtotal' => 50000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 50000,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
        ]);

        $order = \App\Models\Order::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'ORD-COGS-1',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'paid_amount' => 1000,
            'completed_at' => now(),
            'business_date' => local_today($this->tenantBranch->id),
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'quantity' => 2,
            'unit_price' => 500,
            'total_price' => 1000,
            'status' => 'served',
        ]);

        $weekOf = PeriodClosingReport::defaultWeekOf((int) $this->tenantBranch->id, $this->companyAdmin);
        $periods = PeriodClosingReport::resolveWeeklyPeriods($weekOf, 1, (int) $this->tenantBranch->id, $this->companyAdmin);
        $this->actingAsCompanyAdmin();
        $report = PeriodClosingReport::build($this->companyAdmin, (int) $this->tenantBranch->id, $periods);

        $closing = $report['periods'][0]['closing'];
        // 2 × 120 cost = 240 COGS (not the 50,000 purchase)
        $this->assertEquals(240.0, $closing['cogs_total']);
        $this->assertEquals(240.0, $closing['purchase_total']);
        $this->assertEquals(1000.0, $closing['total_sale']);
        $this->assertEquals(760.0, $closing['pnl']);
    }

    public function test_daily_sales_excludes_flagged_money_source_and_shows_cash_receivable(): void
    {
        $cashSource = \App\Models\MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Till Cash',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $cashSource->branches()->attach($this->tenantBranch->id);

        $ownerSource = \App\Models\MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Owner Bucket',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => true,
        ]);
        $ownerSource->branches()->attach($this->tenantBranch->id);

        $today = local_today($this->tenantBranch->id);

        \App\Models\Order::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'ORD-DS-CASH',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'money_source_id' => $cashSource->id,
            'subtotal' => 500,
            'total_amount' => 500,
            'paid_amount' => 500,
            'paid_at_sale' => 500,
            'completed_at' => now(),
            'business_date' => $today,
        ]);

        // Flagged source sale — in total sale, not in payment columns / cash in hand.
        \App\Models\Order::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'ORD-DS-OWNER',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'money_source_id' => $ownerSource->id,
            'subtotal' => 300,
            'total_amount' => 300,
            'paid_amount' => 300,
            'paid_at_sale' => 300,
            'completed_at' => now(),
            'business_date' => $today,
        ]);

        // Credit sale with partial cash received at sale.
        \App\Models\Order::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'ORD-DS-CREDIT',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'partial',
            'payment_method' => 'credit',
            'subtotal' => 1000,
            'total_amount' => 1000,
            'paid_amount' => 200,
            'paid_at_sale' => 200,
            'completed_at' => now(),
            'business_date' => $today,
        ]);

        $weekOf = PeriodClosingReport::defaultWeekOf((int) $this->tenantBranch->id, $this->companyAdmin);
        $periods = PeriodClosingReport::resolveWeeklyPeriods($weekOf, 1, (int) $this->tenantBranch->id, $this->companyAdmin);
        $this->actingAsCompanyAdmin();
        $report = PeriodClosingReport::build($this->companyAdmin, (int) $this->tenantBranch->id, $periods);

        $columns = $report['payment_columns'];
        $this->assertFalse(collect($columns)->contains(fn ($c) => $c['key'] === 'ms_'.$ownerSource->id));
        $this->assertTrue(collect($columns)->contains(fn ($c) => $c['key'] === 'ms_'.$cashSource->id));

        $day = collect($report['periods'][0]['daily_sales'])->firstWhere('date', $today);
        $this->assertNotNull($day);
        $this->assertEquals(1800.0, $day['total_sale']);
        // Credit Sale = full credit order (1000); Cash receivable includes at-sale 200.
        // Cash in hand = 1800 − 1000 − 300 (excluded) + 200 − 0 = 700.
        $this->assertEquals(700.0, $day['cash_in_hand']);
        $this->assertEquals(200.0, $day['cash_receivable']);
        $this->assertEquals(200.0, $day['total_receivable']);

        $credit = collect($day['payments'])->firstWhere('key', 'credit');
        $this->assertNotNull($credit);
        $this->assertEquals(1000.0, $credit['amount']);

        $response = $this->actingAsCompanyAdmin()
            ->getJson(route('reports.panel', [
                'report' => 'weekly-closing',
                'branch_id' => $this->tenantBranch->id,
                'week_of' => $weekOf,
                'week_count' => 1,
            ]));

        $html = $response->assertOk()->json('html');
        $this->assertStringContainsString('Cash receivable', $html);
        $this->assertStringContainsString('Total receivable', $html);
        $this->assertStringNotContainsString('Owner Bucket', $html);
    }

    public function test_daily_sales_includes_customer_payment_collections_on_receipt_day(): void
    {
        $this->actingAsCompanyAdmin();

        $cashSource = \App\Models\MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Till Cash Collections',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $cashSource->branches()->attach($this->tenantBranch->id);

        $jazzSource = \App\Models\MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'JazzCash Test',
            'type' => 'APP',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $jazzSource->branches()->attach($this->tenantBranch->id);

        $bankSource = \App\Models\MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Faisal Bank Test',
            'type' => 'BANK',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $bankSource->branches()->attach($this->tenantBranch->id);

        $ownerSource = \App\Models\MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Owner Collections',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => true,
        ]);
        $ownerSource->branches()->attach($this->tenantBranch->id);

        $today = local_today($this->tenantBranch->id);
        $salesAccount = \App\Models\Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);

        foreach ([
            [$cashSource->id, 'cash', 500, 149001],
            [$jazzSource->id, 'other', 500, 149002],
            [$bankSource->id, 'bank', 1000, 149003],
            [$ownerSource->id, 'cash', 17757, 149004],
        ] as [$sourceId, $method, $amount, $refId]) {
            \App\Models\Transaction::withoutGlobalScopes()->create([
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'account_id' => $salesAccount->id,
                'amount' => $amount,
                'type' => 'in',
                'payment_method' => $method,
                'money_source_id' => $sourceId,
                'reference_type' => 'customer_payment',
                'date' => $today,
                'ref_id' => $refId,
                'created_by' => $this->companyAdmin->id,
                'notes' => 'Customer payment #TEST-'.$amount,
            ]);
        }

        $weekOf = PeriodClosingReport::defaultWeekOf((int) $this->tenantBranch->id, $this->companyAdmin);
        $periods = PeriodClosingReport::resolveWeeklyPeriods($weekOf, 1, (int) $this->tenantBranch->id, $this->companyAdmin);
        $report = PeriodClosingReport::build($this->companyAdmin, (int) $this->tenantBranch->id, $periods);

        $day = collect($report['periods'][0]['daily_sales'])->firstWhere('date', $today);
        $this->assertNotNull($day);
        $this->assertEquals(0.0, $day['total_sale']);
        // Cash receivable = cash collections only; Total receivable = all non-excluded.
        $this->assertEquals(500.0, $day['cash_receivable']);
        $this->assertEquals(2000.0, $day['total_receivable']);
        // Collections are not payment lines; cash in hand = 0 − 0 + 500 − 0.
        $this->assertEquals(500.0, $day['cash_in_hand']);
        $this->assertNull(collect($day['payments'])->firstWhere('key', 'ms_'.$bankSource->id));
        $this->assertNull(collect($day['payments'])->firstWhere('key', 'ms_'.$jazzSource->id));

        $html = $this->getJson(route('reports.panel', [
            'report' => 'weekly-closing',
            'branch_id' => $this->tenantBranch->id,
            'week_of' => $weekOf,
            'week_count' => 1,
        ]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Cash receivable', $html);
        $this->assertStringContainsString('Total receivable', $html);
    }

    public function test_daily_sales_allocates_split_payments_and_shows_foc(): void
    {
        $cashSource = \App\Models\MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Till Cash',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $cashSource->branches()->attach($this->tenantBranch->id);

        $jazzSource = \App\Models\MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'JazzCash',
            'type' => 'APP',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $jazzSource->branches()->attach($this->tenantBranch->id);

        $today = local_today($this->tenantBranch->id);

        $splitOrder = \App\Models\Order::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'ORD-SPLIT-1',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'split',
            'money_source_id' => null,
            'subtotal' => 630,
            'total_amount' => 630,
            'paid_amount' => 630,
            'paid_at_sale' => 630,
            'completed_at' => now(),
            'business_date' => $today,
        ]);

        \App\Models\OrderPayment::create([
            'order_id' => $splitOrder->id,
            'money_source_id' => $cashSource->id,
            'amount' => 500,
            'payment_method' => 'cash',
            'sort_order' => 0,
        ]);
        \App\Models\OrderPayment::create([
            'order_id' => $splitOrder->id,
            'money_source_id' => $jazzSource->id,
            'amount' => 130,
            'payment_method' => 'digital_wallet',
            'sort_order' => 1,
        ]);

        \App\Models\Order::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'ORD-FOC-1',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'foc',
            'money_source_id' => null,
            'subtotal' => 400,
            'total_amount' => 400,
            'paid_amount' => 0,
            'paid_at_sale' => 0,
            'completed_at' => now(),
            'business_date' => $today,
        ]);

        $weekOf = PeriodClosingReport::defaultWeekOf((int) $this->tenantBranch->id, $this->companyAdmin);
        $periods = PeriodClosingReport::resolveWeeklyPeriods($weekOf, 1, (int) $this->tenantBranch->id, $this->companyAdmin);
        $this->actingAsCompanyAdmin();
        $report = PeriodClosingReport::build($this->companyAdmin, (int) $this->tenantBranch->id, $periods);

        $day = collect($report['periods'][0]['daily_sales'])->firstWhere('date', $today);
        $this->assertNotNull($day);
        $this->assertEquals(1030.0, $day['total_sale']);
        // Cash in hand = 1030 − 130 (Jazz) − 400 (FOC) + 0 − 0 = 500.
        $this->assertEquals(500.0, $day['cash_in_hand']);

        $payments = collect($day['payments']);
        $this->assertNull($payments->firstWhere('key', 'other'));
        $this->assertNull($payments->firstWhere('key', 'split_payment'));

        $jazz = $payments->firstWhere('key', 'ms_'.$jazzSource->id);
        $this->assertNotNull($jazz);
        $this->assertEquals(130.0, $jazz['amount']);

        $foc = $payments->firstWhere('key', 'foc');
        $this->assertNotNull($foc);
        $this->assertEquals(400.0, $foc['amount']);
        $this->assertSame('FOC', $foc['label']);

        $html = $this->actingAsCompanyAdmin()
            ->getJson(route('reports.panel', [
                'report' => 'weekly-closing',
                'branch_id' => $this->tenantBranch->id,
                'week_of' => $weekOf,
                'week_count' => 1,
            ]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('FOC', $html);
        $this->assertStringContainsString('JazzCash', $html);
    }

    public function test_daily_sales_includes_expense_total(): void
    {
        $this->actingAsCompanyAdmin();
        $today = local_today($this->tenantBranch->id);

        $tillCash = \App\Models\MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Till Cash Exp',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $tillCash->branches()->attach($this->tenantBranch->id);

        $ownerSource = \App\Models\MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Owner Exp Bucket',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => true,
        ]);
        $ownerSource->branches()->attach($this->tenantBranch->id);

        $expenseAccount = \App\Models\Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Petty cash expense',
            'type' => 'expense',
            'is_active' => true,
        ]);

        \App\Models\Expense::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'created_by' => $this->companyAdmin->id,
            'category' => 'Utilities',
            'description' => 'Gas bill',
            'amount' => 250,
            'expense_date' => $today,
        ]);

        // Operating expense from a normal money source — included.
        \App\Models\Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $expenseAccount->id,
            'amount' => 100,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $tillCash->id,
            'reference_type' => 'expense',
            'date' => $today,
            'ref_id' => 999010,
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Cleaning supplies',
        ]);

        // Flagged money source expense — excluded from expense_total.
        \App\Models\Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $expenseAccount->id,
            'amount' => 5000,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $ownerSource->id,
            'reference_type' => 'expense',
            'date' => $today,
            'ref_id' => 999011,
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Owner personal bill',
        ]);

        // Internal transfer — excluded.
        \App\Models\Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $expenseAccount->id,
            'amount' => 800,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $tillCash->id,
            'reference_type' => 'transfer',
            'date' => $today,
            'ref_id' => 999012,
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Till to safe',
        ]);

        $focAccount = \App\Models\Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'FOC',
            'type' => 'expense',
            'is_active' => true,
        ]);

        \App\Models\Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $focAccount->id,
            'amount' => 400,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => null,
            'reference_type' => 'expense',
            'date' => $today,
            'ref_id' => 999001,
            'created_by' => $this->companyAdmin->id,
            'notes' => 'FOC Order #ORD-FOC-TEST',
        ]);

        $weekOf = PeriodClosingReport::defaultWeekOf((int) $this->tenantBranch->id, $this->companyAdmin);
        $periods = PeriodClosingReport::resolveWeeklyPeriods($weekOf, 1, (int) $this->tenantBranch->id, $this->companyAdmin);
        $report = PeriodClosingReport::build($this->companyAdmin, (int) $this->tenantBranch->id, $periods);

        $day = collect($report['periods'][0]['daily_sales'])->firstWhere('date', $today);
        $this->assertNotNull($day);
        // Daily card: cash-source transaction expenses only (100). Expense table has no money source.
        $this->assertEquals(100.0, $day['expense_total']);
        $this->assertCount(1, $day['expense_lines']);
        $this->assertSame('Petty cash expense', $day['expense_lines'][0]['label']);
        $this->assertEquals(100.0, $day['expense_lines'][0]['amount']);
        // Closing PnL still includes all operating expenses (Expense row + cash tx).
        $this->assertEquals(350.0, $report['periods'][0]['closing']['expense_total']);

        $html = $this->getJson(route('reports.panel', [
            'report' => 'weekly-closing',
            'branch_id' => $this->tenantBranch->id,
            'week_of' => $weekOf,
            'week_count' => 1,
        ]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Expenses', $html);
        $this->assertStringContainsString('fa-info-circle', $html);
        $this->assertStringContainsString('Cleaning supplies', $html);
        $this->assertStringNotContainsString('Gas bill', $html);
        $this->assertStringNotContainsString('Owner personal bill', $html);
        $this->assertStringNotContainsString('Till to safe', $html);
    }
}
