<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class DashboardOperationalComparisonTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->actingAsCompanyAdmin();
    }

    public function test_cash_outflow_counts_supplier_payments_not_purchase_value(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'General Market',
            'code' => 'GM-'.uniqid(),
            'status' => 'active',
        ]);

        $purchaseAccount = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Purchase',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $date = local_today($this->tenantBranch->id);

        Purchase::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $this->companyAdmin->id,
            'purchase_number' => 'P-'.uniqid(),
            'purchase_date' => $date,
            'payment_status' => 'paid',
            'payment_method' => 'credit',
            'subtotal' => 5000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 5000,
            'paid_amount' => 0,
        ]);

        SupplierPayment::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplier->id,
            'account_id' => $purchaseAccount->id,
            'created_by' => $this->companyAdmin->id,
            'payment_number' => 'SP-'.uniqid(),
            'payment_date' => $date,
            'total_amount' => 5000,
            'payment_method' => 'transfer',
        ]);

        $this->assertSame(1, Purchase::query()->count());
        $this->assertSame(1, SupplierPayment::query()->count());

        $purchaseDate = Purchase::query()->value('purchase_date');
        $paymentDate = SupplierPayment::query()->value('payment_date');
        $this->assertNotNull($purchaseDate);
        $this->assertNotNull($paymentDate);

        $report = DashboardMetrics::operationalComparison(
            $this->companyAdmin,
            $this->tenantBranch->id,
            (string) $purchaseDate,
            (string) $paymentDate,
        );

        $this->assertSame(5000.0, $report['values'][0]);
        $this->assertSame(5000.0, $report['values'][3]);
        $this->assertSame(5000.0, $report['cash_outflow']);
        $this->assertNotSame(
            10000.0,
            $report['cash_outflow'],
            'Paying a credit purchase must not count purchase value and supplier payment in outflow'
        );
    }
}
