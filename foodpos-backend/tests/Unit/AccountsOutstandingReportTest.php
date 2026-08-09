<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\AccountsOutstandingReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class AccountsOutstandingReportTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_receivables_use_customer_ledger_balance_company_wide(): void
    {
        Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Imported Balance',
            'code' => 'CU-IMP',
            'balance' => 24604,
            'is_active' => true,
        ]);

        $report = AccountsOutstandingReport::receivables($this->companyAdmin, null);

        $this->assertSame(1, $report['party_count']);
        $this->assertSame(24604.0, $report['total']);
        $this->assertSame(24604.0, $report['rows']->first()['balance']);
    }

    public function test_receivables_with_branch_show_ledger_balance_for_linked_customers(): void
    {
        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Branch Customer',
            'code' => 'CU-BR',
            'balance' => 1500,
            'is_active' => true,
        ]);

        Order::withoutGlobalScopes(['tenant', 'branch'])->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-001',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => 1500,
            'total_amount' => 1500,
            'paid_amount' => 1500,
            'completed_at' => now(),
        ]);

        $report = AccountsOutstandingReport::receivables($this->companyAdmin, $this->tenantBranch->id);

        $this->assertSame(1, $report['party_count']);
        $this->assertSame(1500.0, $report['total']);
    }

    public function test_receivables_with_branch_still_show_import_only_balances(): void
    {
        Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Import Only',
            'code' => 'CU-ONLY',
            'balance' => 900,
            'is_active' => true,
        ]);

        $report = AccountsOutstandingReport::receivables($this->companyAdmin, $this->tenantBranch->id);

        $this->assertSame(1, $report['party_count']);
        $this->assertSame(900.0, $report['total']);
    }

    public function test_receivables_ignore_fully_paid_orders_when_balance_from_ledger(): void
    {
        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Ledger Owes',
            'code' => 'CU-LED',
            'balance' => 3200,
            'is_active' => true,
        ]);

        Order::withoutGlobalScopes(['tenant', 'branch'])->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-002',
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => 3200,
            'total_amount' => 3200,
            'paid_amount' => 3200,
            'completed_at' => now(),
        ]);

        $report = AccountsOutstandingReport::receivables($this->companyAdmin, $this->tenantBranch->id);

        $this->assertSame(1, $report['party_count']);
        $this->assertSame(3200.0, $report['total']);
    }

    public function test_payables_use_supplier_ledger_balance_company_wide(): void
    {
        Supplier::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Imported Balance',
            'code' => 'SU-IMP',
            'balance' => 18450,
            'status' => 'active',
        ]);

        $report = AccountsOutstandingReport::payables($this->companyAdmin, null);

        $this->assertSame(1, $report['party_count']);
        $this->assertSame(18450.0, $report['total']);
        $this->assertSame(18450.0, $report['rows']->first()['balance']);
    }

    public function test_payables_with_branch_show_ledger_balance_for_linked_suppliers(): void
    {
        $supplier = Supplier::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Branch Supplier',
            'code' => 'SU-BR',
            'balance' => 2200,
            'status' => 'active',
        ]);

        Purchase::withoutGlobalScopes(['tenant', 'branch'])->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->companyAdmin->id,
            'purchase_number' => 'P-001',
            'purchase_date' => now()->toDateString(),
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => 2200,
            'total_amount' => 2200,
            'paid_amount' => 2200,
        ]);

        $report = AccountsOutstandingReport::payables($this->companyAdmin, $this->tenantBranch->id);

        $this->assertSame(1, $report['party_count']);
        $this->assertSame(2200.0, $report['total']);
    }

    public function test_payables_with_branch_still_show_import_only_balances(): void
    {
        Supplier::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Import Only',
            'code' => 'SU-ONLY',
            'balance' => 750,
            'status' => 'active',
        ]);

        $report = AccountsOutstandingReport::payables($this->companyAdmin, $this->tenantBranch->id);

        $this->assertSame(1, $report['party_count']);
        $this->assertSame(750.0, $report['total']);
    }

    public function test_payables_ignore_fully_paid_purchases_when_balance_from_ledger(): void
    {
        $supplier = Supplier::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Ledger Owes',
            'code' => 'SU-LED',
            'balance' => 4100,
            'status' => 'active',
        ]);

        Purchase::withoutGlobalScopes(['tenant', 'branch'])->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->companyAdmin->id,
            'purchase_number' => 'P-002',
            'purchase_date' => now()->toDateString(),
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => 4100,
            'total_amount' => 4100,
            'paid_amount' => 4100,
        ]);

        $report = AccountsOutstandingReport::payables($this->companyAdmin, $this->tenantBranch->id);

        $this->assertSame(1, $report['party_count']);
        $this->assertSame(4100.0, $report['total']);
    }
}
