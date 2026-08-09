<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\Transaction;
use App\Support\DashboardMetrics;
use App\Support\ProfitLossReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class DashboardNetProfitMoneySourceFlagTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->actingAsCompanyAdmin();
    }

    public function test_dashboard_net_profit_excludes_all_outflows_from_flagged_money_source(): void
    {
        $branchId = (int) $this->tenantBranch->id;
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'D-'.Str::upper(Str::random(6)),
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'paid_amount' => 1000,
            'business_date' => $today,
            'completed_at' => now(),
        ]);

        $ownerSource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Owner A/C',
            'type' => 'BANK',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $ownerSource->branches()->attach($branchId);

        $cashSource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $cashSource->branches()->attach($branchId);

        $expenseAccount = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Salary',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $purchaseAccount = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Purchase',
            'type' => 'expense',
            'is_active' => true,
        ]);

        // Owner: expense + purchase = 850 total outflow
        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'account_id' => $expenseAccount->id,
            'amount' => 600,
            'type' => 'out',
            'payment_method' => 'transfer',
            'money_source_id' => $ownerSource->id,
            'reference_type' => 'expense',
            'date' => $today,
            'created_by' => $this->companyAdmin->id,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'account_id' => $purchaseAccount->id,
            'amount' => 250,
            'type' => 'out',
            'payment_method' => 'transfer',
            'money_source_id' => $ownerSource->id,
            'reference_type' => 'purchase',
            'date' => $today,
            'created_by' => $this->companyAdmin->id,
        ]);

        // Cash expense still counts when Owner is excluded
        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'account_id' => $expenseAccount->id,
            'amount' => 100,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $cashSource->id,
            'reference_type' => 'expense',
            'date' => $today,
            'created_by' => $this->companyAdmin->id,
        ]);

        $pl = ProfitLossReport::build($this->companyAdmin, $branchId, $monthStart, $monthEnd);
        $netSales = (float) $pl['revenue']['net_sales'];
        $cogs = (float) $pl['cogs']['total'];

        $included = DashboardMetrics::summaryForRange($this->companyAdmin, $branchId, $monthStart, $monthEnd);
        // Include Owner: subtract expense 600 + purchase 250 + cash 100
        $this->assertSame(round($netSales - $cogs - 950, 2), $included['net_profit']);

        $ownerSource->update(['exclude_from_dashboard_profit' => true]);

        $excluded = DashboardMetrics::summaryForRange($this->companyAdmin, $branchId, $monthStart, $monthEnd);
        // Exclude Owner entirely: only cash 100 remains
        $this->assertSame(round($netSales - $cogs - 100, 2), $excluded['net_profit']);
        // Gap between include/exclude = full Owner outflow (850)
        $this->assertSame(850.0, round($excluded['net_profit'] - $included['net_profit'], 2));

        $breakdown = $excluded['net_profit_breakdown'];
        $this->assertSame($excluded['net_profit'], $breakdown['net_profit']);
        $this->assertSame($netSales, $breakdown['total_sale']);
        $this->assertSame($cogs, $breakdown['cogs']);
        $this->assertSame(0.0, $breakdown['expenses_total']);
        $this->assertSame(100.0, $breakdown['payouts_total']);
        $this->assertCount(1, $breakdown['payouts']);
        $this->assertSame('Expense', $breakdown['payouts'][0]['label']);
        $this->assertSame(100.0, $breakdown['payouts'][0]['amount']);

        // P&L report still unchanged (purchase never in P&L opex; expenses still counted)
        $plAfter = ProfitLossReport::build($this->companyAdmin, $branchId, $monthStart, $monthEnd);
        $this->assertSame((float) $pl['net_profit'], (float) $plAfter['net_profit']);
    }

    public function test_dashboard_net_profit_breakdown_lists_expense_and_payout_rows(): void
    {
        $branchId = (int) $this->tenantBranch->id;
        $today = now()->toDateString();

        Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'D-'.Str::upper(Str::random(6)),
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 1500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1500,
            'paid_amount' => 1500,
            'business_date' => $today,
            'completed_at' => now(),
        ]);

        \App\Models\Expense::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'created_by' => $this->companyAdmin->id,
            'category' => 'Utilities',
            'description' => 'Electricity',
            'amount' => 200,
            'expense_date' => $today,
            'notes' => 'July bill',
        ]);

        $cashSource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $cashSource->branches()->attach($branchId);

        $transferAccount = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Owner transfer',
            'type' => 'expense',
            'is_active' => true,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'account_id' => $transferAccount->id,
            'amount' => 500,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $cashSource->id,
            'reference_type' => 'transfer',
            'date' => $today,
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Transfer to Owner A/C',
        ]);

        $summary = DashboardMetrics::summaryForRange($this->companyAdmin, $branchId, $today, $today);
        $breakdown = $summary['net_profit_breakdown'];

        $this->assertSame(1500.0, $breakdown['total_sale']);
        $this->assertSame(200.0, $breakdown['expenses_total']);
        $this->assertSame(0.0, $breakdown['payouts_total']);
        $this->assertCount(1, $breakdown['expenses']);
        $this->assertSame('Electricity', $breakdown['expenses'][0]['label']);
        $this->assertCount(0, $breakdown['payouts']);
        $this->assertSame(
            round(1500 - $breakdown['cogs'] - 200 - 0, 2),
            $breakdown['net_profit']
        );
    }

    public function test_dashboard_net_profit_ignores_internal_transfers(): void
    {
        $branchId = (int) $this->tenantBranch->id;
        $today = now()->toDateString();

        Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'D-'.Str::upper(Str::random(6)),
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'paid_amount' => 1000,
            'business_date' => $today,
            'completed_at' => now(),
        ]);

        $cashSource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $cashSource->branches()->attach($branchId);

        $bankSource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Bank',
            'type' => 'BANK',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $bankSource->branches()->attach($branchId);

        $transferAccount = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Transfer',
            'type' => 'expense',
            'is_active' => true,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'account_id' => $transferAccount->id,
            'amount' => 800,
            'type' => 'out',
            'payment_method' => 'transfer',
            'money_source_id' => $cashSource->id,
            'reference_type' => 'transfer',
            'date' => $today,
            'ref_id' => $bankSource->id,
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Transfer to Bank',
        ]);

        $expenseAccount = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Rent',
            'type' => 'expense',
            'is_active' => true,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'account_id' => $expenseAccount->id,
            'amount' => 150,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $cashSource->id,
            'reference_type' => 'expense',
            'date' => $today,
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Shop rent',
        ]);

        $summary = DashboardMetrics::summaryForRange($this->companyAdmin, $branchId, $today, $today);
        $breakdown = $summary['net_profit_breakdown'];

        $this->assertSame(150.0, $breakdown['payouts_total']);
        $this->assertCount(1, $breakdown['payouts']);
        $this->assertSame('Expense', $breakdown['payouts'][0]['label']);
        $this->assertSame('Rent', $breakdown['payouts'][0]['account']);
        $this->assertCount(1, $breakdown['payout_groups']);
        $this->assertSame('Rent', $breakdown['payout_groups'][0]['label']);
        $this->assertSame(150.0, $breakdown['payout_groups'][0]['total']);
        $this->assertSame(
            round(1000 - $breakdown['cogs'] - 0 - 150, 2),
            $breakdown['net_profit']
        );
    }

    public function test_dashboard_net_profit_payout_groups_by_account(): void
    {
        $branchId = (int) $this->tenantBranch->id;
        $today = now()->toDateString();

        Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'D-'.Str::upper(Str::random(6)),
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 2000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 2000,
            'paid_amount' => 2000,
            'business_date' => $today,
            'completed_at' => now(),
        ]);

        $cashSource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
            'exclude_from_dashboard_profit' => false,
        ]);
        $cashSource->branches()->attach($branchId);

        $salary = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Salary',
            'type' => 'expense',
            'is_active' => true,
        ]);
        $restaurant = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Other (Restaurant)',
            'type' => 'expense',
            'is_active' => true,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'account_id' => $salary->id,
            'amount' => 600,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $cashSource->id,
            'reference_type' => 'expense',
            'date' => $today,
            'created_by' => $this->companyAdmin->id,
        ]);
        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'account_id' => $restaurant->id,
            'amount' => 250,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $cashSource->id,
            'reference_type' => 'expense',
            'date' => $today,
            'created_by' => $this->companyAdmin->id,
        ]);
        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $branchId,
            'account_id' => $restaurant->id,
            'amount' => 150,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $cashSource->id,
            'reference_type' => 'expense',
            'date' => $today,
            'created_by' => $this->companyAdmin->id,
        ]);

        $breakdown = DashboardMetrics::summaryForRange(
            $this->companyAdmin,
            $branchId,
            $today,
            $today
        )['net_profit_breakdown'];

        $this->assertSame(1000.0, $breakdown['payouts_total']);
        $this->assertCount(2, $breakdown['payout_groups']);
        $this->assertSame('Salary', $breakdown['payout_groups'][0]['label']);
        $this->assertSame(600.0, $breakdown['payout_groups'][0]['total']);
        $this->assertSame('Other (Restaurant)', $breakdown['payout_groups'][1]['label']);
        $this->assertSame(400.0, $breakdown['payout_groups'][1]['total']);
        $this->assertCount(2, $breakdown['payout_groups'][1]['rows']);
    }
}
