<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\EmployeeProfile;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\MoneySource;
use App\Models\PartyBalanceAdjustment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\EmployeePaymentService;
use App\Support\AccountStatementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class AccountStatementSortingTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();
    }

    public function test_supplier_statement_orders_purchase_payment_then_purchase_edit_adjustment(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Imtyaz Mall',
            'code' => 'IM-'.uniqid(),
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

        Carbon::setTestNow('2026-07-10 09:00:00');

        $this->actingAsCompanyAdmin()
            ->post(route('purchases.store'), [
                'branch_id' => $this->tenantBranch->id,
                'supplier_id' => $supplier->id,
                'purchase_date' => '2026-07-10',
                'payment_selection' => 'credit',
                'paid_amount' => 0,
                'subtotal' => 10553,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 10553,
                'items' => [[
                    'item_type' => 'ingredient',
                    'item_id' => $ingredient->id,
                    'quantity' => 1,
                    'unit_id' => 'kg',
                    'unit_price' => 10553,
                    'expiry_date' => null,
                ]],
            ])
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::withoutGlobalScopes()->latest('id')->firstOrFail();

        Carbon::setTestNow('2026-07-11 10:00:00');

        $this->actingAsCompanyAdmin()
            ->post(route('supplier-payments.store'), [
                'supplier_id' => $supplier->id,
                'branch_id' => $this->tenantBranch->id,
                'account_id' => Account::withoutGlobalScopes()->where('name', 'Purchase')->value('id'),
                'money_source_id' => $moneySource->id,
                'payment_date' => '2026-07-11',
                'total_amount' => 10553,
            ])
            ->assertRedirect(route('supplier-payments.index'));

        Carbon::setTestNow('2026-07-13 01:14:00');

        PartyBalanceAdjustment::create([
            'company_id' => $this->tenantCompany->id,
            'party_type' => PartyBalanceAdjustment::PARTY_SUPPLIER,
            'party_id' => $supplier->id,
            'previous_balance' => 0,
            'new_balance' => 1165,
            'reason' => 'Purchase #'.$purchase->purchase_number.' total changed from Rs 10,553 to Rs 11,718',
            'created_by' => $this->companyAdmin->id,
        ]);

        $purchase->update(['total_amount' => 11718]);
        // Keep stored balance aligned with statement lines (purchase − payment + adjustment).
        $supplier->update(['balance' => 2330]);

        $statement = app(AccountStatementService::class)->supplierStatement(
            $supplier->fresh(),
            $this->tenantCompany->id,
            $this->tenantBranch->id,
        );

        $labels = collect($statement['lines'])->pluck('label')->all();

        $this->assertSame(['Purchase', 'Payment at purchase', 'Balance adjustment'], $labels);

        Carbon::setTestNow();
    }

    public function test_employee_statement_lists_ledger_entries_with_running_balance(): void
    {
        $this->actingAsCompanyAdmin();

        $employee = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => false,
        ]);
        $employee->branches()->sync([
            $this->tenantBranch->id => ['is_primary' => true],
        ]);
        EmployeeProfile::create([
            'company_id' => $this->tenantCompany->id,
            'user_id' => $employee->id,
            'employee_number' => 'EMP-STMT-1',
            'employment_status' => 'active',
            'pay_frequency' => 'daily',
            'pay_rate' => 1000,
            'standard_hours_per_day' => 8,
            'overtime_rate' => 0,
            'short_hours_policy' => 'full_day',
            'working_days' => [1, 2, 3, 4, 5, 6, 7],
        ]);

        $cashSource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Drawer',
            'type' => 'CASH',
            'opening_balance' => 10000,
            'active' => true,
        ]);
        $cashSource->branches()->sync([$this->tenantBranch->id]);
        Account::ensureSystemAccount((int) $this->tenantCompany->id, 'Salary', 'expense');

        $this->tenantCompany->update([
            'settings' => array_merge($this->tenantCompany->settings ?? [], [
                'strict_direct_pay_rate' => true,
            ]),
        ]);
        $this->companyAdmin->unsetRelation('company');

        app(EmployeePaymentService::class)->pay([
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $employee->id,
            'money_source_id' => $cashSource->id,
            'kind' => 'wage',
            'payment_date' => '2026-07-16',
            'amount' => 800,
            'payment_method' => 'cash',
        ], $this->companyAdmin->fresh());

        $this->get(route('account-statements.index', [
            'type' => 'employee',
            'party_id' => $employee->id,
        ]))->assertRedirect(route('reports.index', [
            'type' => 'employee',
            'party_id' => $employee->id,
            'report' => 'account-statement',
        ]));

        $this->getJson(route('reports.panel', [
            'report' => 'account-statement',
            'type' => 'employee',
            'party_id' => $employee->id,
            'branch_id' => $this->tenantBranch->id,
        ]))->assertOk()->assertSee($employee->name, false);

        $statement = app(AccountStatementService::class)->employeeStatement(
            $employee,
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
        );

        $this->assertNotEmpty($statement['lines']);
        $this->assertSame(200.0, (float) $statement['closing_balance']);
    }

    private function createIngredient(string $name, string $sku, float $purchasePricePerKg): Ingredient
    {
        $gramUnit = IngredientUnit::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->tenantCompany->id, 'name' => 'Gram'],
            ['code' => 'g-'.uniqid()]
        );

        $kgUnit = IngredientUnit::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $this->tenantCompany->id, 'name' => 'Kilogram'],
            ['code' => 'kg-'.uniqid()]
        );

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

    public function test_customer_statement_includes_opening_balance_row_for_date_range(): void
    {
        $customer = \App\Models\Customer::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Tariq Room',
            'phone' => '03001234567',
            'is_active' => true,
            'balance' => 7000,
        ]);

        \App\Models\Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'customer_id' => $customer->id,
            'order_number' => 'PRE-001',
            'type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'payment_method' => 'credit',
            'subtotal' => 5000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 5000,
            'paid_amount' => 0,
            'paid_at_sale' => 0,
            'business_date' => '2026-07-10',
            'completed_at' => Carbon::parse('2026-07-10 12:00:00'),
            'created_at' => Carbon::parse('2026-07-10 12:00:00'),
        ]);

        \App\Models\Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'customer_id' => $customer->id,
            'order_number' => 'IN-001',
            'type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'payment_method' => 'credit',
            'subtotal' => 2000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 2000,
            'paid_amount' => 0,
            'paid_at_sale' => 0,
            'business_date' => '2026-07-15',
            'completed_at' => Carbon::parse('2026-07-15 12:00:00'),
            'created_at' => Carbon::parse('2026-07-15 12:00:00'),
        ]);

        $statement = app(AccountStatementService::class)->customerStatement(
            $customer,
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
            '2026-07-14',
            '2026-07-20'
        );

        $this->assertSame(5000.0, $statement['opening_balance']);
        $this->assertSame(7000.0, $statement['closing_balance']);
        $this->assertSame('opening_balance', $statement['lines'][0]['type']);
        $this->assertSame('Opening balance', $statement['lines'][0]['label']);
        $this->assertSame(5000.0, $statement['lines'][0]['balance']);
        $this->assertSame('Credit sale', $statement['lines'][1]['label']);
        $this->assertSame(7000.0, $statement['lines'][1]['balance']);
    }

    public function test_customer_statement_includes_opening_balance_row_for_all_dates_when_balance_has_seed(): void
    {
        $customer = \App\Models\Customer::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Tariq Room No.8',
            'phone' => '03007654321',
            'is_active' => true,
            'balance' => 8917,
            'created_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);

        \App\Models\Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'customer_id' => $customer->id,
            'order_number' => 'SALE-001',
            'type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'payment_method' => 'credit',
            'subtotal' => 2000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 2000,
            'paid_amount' => 0,
            'paid_at_sale' => 0,
            'business_date' => '2026-07-15',
            'completed_at' => Carbon::parse('2026-07-15 12:00:00'),
            'created_at' => Carbon::parse('2026-07-15 12:00:00'),
        ]);

        $statement = app(AccountStatementService::class)->customerStatement(
            $customer,
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
            null,
            null
        );

        $this->assertSame(6917.0, $statement['opening_balance']);
        $this->assertSame(8917.0, $statement['closing_balance']);
        $this->assertSame('opening_balance', $statement['lines'][0]['type']);
        $this->assertSame('Opening balance', $statement['lines'][0]['label']);
        $this->assertSame(6917.0, $statement['lines'][0]['balance']);
        $this->assertSame('Credit sale', $statement['lines'][1]['label']);
        $this->assertSame(8917.0, $statement['lines'][1]['balance']);
    }

    public function test_supplier_statement_includes_opening_balance_row_for_date_range(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Imtyaz Mall',
            'code' => 'IM-'.uniqid(),
            'status' => 'active',
            'balance' => 4500,
        ]);

        $ingredient = $this->createIngredient('Rice', 'RC-'.uniqid(), 80);

        Purchase::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->companyAdmin->id,
            'purchase_number' => 'PO-PRE-1',
            'purchase_date' => '2026-07-05',
            'subtotal' => 3000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 3000,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'status' => 'received',
        ]);

        Purchase::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->companyAdmin->id,
            'purchase_number' => 'PO-IN-1',
            'purchase_date' => '2026-07-16',
            'subtotal' => 1500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 1500,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'status' => 'received',
        ]);

        // Touch unused ingredient so helper stays meaningful if purchase items required later.
        $this->assertNotNull($ingredient->id);

        $statement = app(AccountStatementService::class)->supplierStatement(
            $supplier,
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
            '2026-07-14',
            '2026-07-20'
        );

        $this->assertSame(3000.0, $statement['opening_balance']);
        $this->assertSame(4500.0, $statement['closing_balance']);
        $this->assertSame('opening_balance', $statement['lines'][0]['type']);
        $this->assertSame(3000.0, $statement['lines'][0]['balance']);
        $this->assertGreaterThan(1, count($statement['lines']));
        $this->assertSame(4500.0, $statement['closing_balance']);
    }

    public function test_employee_statement_includes_opening_balance_row_for_date_range(): void
    {
        $employee = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'waiter',
            'status' => 'active',
            'can_login' => false,
            'name' => 'Waiter One',
        ]);

        EmployeeProfile::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'user_id' => $employee->id,
            'employee_number' => 'EMP-OB-1',
            'employment_status' => 'active',
            'pay_frequency' => 'monthly',
            'pay_rate' => 0,
            'standard_hours_per_day' => 8,
            'overtime_rate' => 0,
            'short_hours_policy' => 'full_day',
            'working_days' => EmployeeProfile::DEFAULT_WORKING_DAYS,
        ]);

        \App\Models\EmployeeLedgerEntry::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $employee->id,
            'type' => 'wage',
            'direction' => 'credit',
            'amount' => 4000,
            'entry_date' => '2026-07-08',
            'description' => 'Prior wage',
            'created_by' => $this->companyAdmin->id,
        ]);

        \App\Models\EmployeeLedgerEntry::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $employee->id,
            'type' => 'wage',
            'direction' => 'credit',
            'amount' => 1000,
            'entry_date' => '2026-07-18',
            'description' => 'Period wage',
            'created_by' => $this->companyAdmin->id,
        ]);

        $statement = app(AccountStatementService::class)->employeeStatement(
            $employee,
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
            '2026-07-14',
            '2026-07-20'
        );

        $this->assertSame('opening_balance', $statement['lines'][0]['type']);
        $this->assertSame(4000.0, $statement['opening_balance']);
        $this->assertSame(4000.0, $statement['lines'][0]['balance']);
        $this->assertSame(5000.0, $statement['closing_balance']);
    }

    public function test_supplier_zero_to_balance_adjustment_shows_as_opening_balance(): void
    {
        $this->actingAsCompanyAdmin();

        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Kainat Gas Agency',
            'code' => 'KG-'.uniqid(),
            'status' => 'active',
            'balance' => 38000,
        ]);

        PartyBalanceAdjustment::create([
            'company_id' => $this->tenantCompany->id,
            'party_type' => PartyBalanceAdjustment::PARTY_SUPPLIER,
            'party_id' => $supplier->id,
            'previous_balance' => 0,
            'new_balance' => 38000,
            'reason' => null,
            'created_by' => $this->companyAdmin->id,
            'created_at' => Carbon::parse('2026-07-13 12:00:00'),
        ]);

        $statement = app(AccountStatementService::class)->supplierStatement(
            $supplier,
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
            null,
            null
        );

        $this->assertCount(1, $statement['lines']);
        $this->assertSame('opening_balance', $statement['lines'][0]['type']);
        $this->assertSame('Opening balance', $statement['lines'][0]['label']);
        $this->assertSame('Opening balance', $statement['lines'][0]['reference']);
        $this->assertSame(38000.0, $statement['lines'][0]['credit']);
        $this->assertSame(38000.0, $statement['lines'][0]['balance']);
        $this->assertSame(38000.0, $statement['closing_balance']);
    }

    public function test_later_from_zero_adjustment_is_not_labeled_opening_balance(): void
    {
        $this->actingAsCompanyAdmin();

        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Resettable Supplier',
            'code' => 'RS-'.uniqid(),
            'status' => 'active',
            'balance' => 5000,
        ]);

        PartyBalanceAdjustment::create([
            'company_id' => $this->tenantCompany->id,
            'party_type' => PartyBalanceAdjustment::PARTY_SUPPLIER,
            'party_id' => $supplier->id,
            'previous_balance' => 0,
            'new_balance' => 10000,
            'reason' => 'Opening balance',
            'created_by' => $this->companyAdmin->id,
            'created_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);

        // Settled back to zero.
        PartyBalanceAdjustment::create([
            'company_id' => $this->tenantCompany->id,
            'party_type' => PartyBalanceAdjustment::PARTY_SUPPLIER,
            'party_id' => $supplier->id,
            'previous_balance' => 10000,
            'new_balance' => 0,
            'reason' => 'Settled',
            'created_by' => $this->companyAdmin->id,
            'created_at' => Carbon::parse('2026-07-10 10:00:00'),
        ]);

        // Later adjustment from zero again — must not be a second opening.
        PartyBalanceAdjustment::create([
            'company_id' => $this->tenantCompany->id,
            'party_type' => PartyBalanceAdjustment::PARTY_SUPPLIER,
            'party_id' => $supplier->id,
            'previous_balance' => 0,
            'new_balance' => 5000,
            'reason' => null,
            'created_by' => $this->companyAdmin->id,
            'created_at' => Carbon::parse('2026-07-20 10:00:00'),
        ]);

        $statement = app(AccountStatementService::class)->supplierStatement(
            $supplier,
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
            null,
            null
        );

        $labels = collect($statement['lines'])->pluck('label')->all();
        $this->assertSame(['Opening balance', 'Balance adjustment', 'Balance adjustment'], $labels);
        $this->assertSame('opening_balance', $statement['lines'][0]['type']);
        $this->assertSame('balance_adjustment', $statement['lines'][2]['type']);
        $this->assertSame(5000.0, $statement['closing_balance']);
    }
}
