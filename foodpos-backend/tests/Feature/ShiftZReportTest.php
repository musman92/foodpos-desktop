<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\MoneySource;
use App\Models\MoneySourceFundMovement;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ShiftService;
use App\Services\TenantRoleBootstrapService;
use App\Support\ShiftZReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class ShiftZReportTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private MoneySource $cashSource;

    private Account $salesAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();

        $this->cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash Drawer',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $this->cashSource->branches()->attach($this->tenantBranch->id);

        $this->salesAccount = Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);
    }

    public function test_z_report_includes_stamped_and_legacy_shift_activity(): void
    {
        $this->actingAsCompanyAdmin();
        $shift = $this->startShiftWithCash(1000.0);

        $legacyOrder = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'shift_id' => null,
            'order_number' => 'LEG-Z-001',
            'status' => 'completed',
            'type' => 'takeaway',
            'subtotal' => 100,
            'total_amount' => 100,
            'payment_status' => 'paid',
            'created_at' => $shift->opened_at,
            'updated_at' => $shift->opened_at,
        ]);

        $stampedOrder = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'shift_id' => $shift->id,
            'order_number' => 'STAMP-Z-001',
            'status' => 'completed',
            'type' => 'dine_in',
            'subtotal' => 200,
            'total_amount' => 200,
            'payment_status' => 'paid',
            'created_at' => $shift->opened_at,
            'updated_at' => $shift->opened_at,
        ]);

        OrderRefund::create([
            'order_id' => $stampedOrder->id,
            'created_by' => $this->companyAdmin->id,
            'subtotal_refund' => 50,
            'tax_refund' => 0,
            'total_refund' => 50,
            'notes' => 'Test refund',
        ]);

        $this->createSaleTransaction($shift, amount: 100.0, shiftId: null, refId: $legacyOrder->id);
        $this->createSaleTransaction($shift, amount: 200.0, shiftId: $shift->id, refId: $stampedOrder->id);
        $this->createRefundTransaction($shift, amount: 50.0, shiftId: $shift->id, refId: $stampedOrder->id);

        MoneySourceFundMovement::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'from_money_source_id' => $this->cashSource->id,
            'to_money_source_id' => $this->cashSource->id,
            'amount' => 75.0,
            'movement_date' => $shift->shift_date->format('Y-m-d'),
            'created_by' => $shift->opened_by,
            'shift_id' => null,
            'notes' => 'Petty cash',
        ]);

        $report = ShiftZReport::build($shift->fresh(['branch', 'company', 'openedBy', 'moneySources']), app(ShiftService::class));

        $this->assertTrue($report['is_interim']);
        $this->assertSame(2, $report['sales']['order_count']);
        $this->assertSame(300.0, $report['sales']['gross_total']);
        $this->assertSame(1, $report['sales']['refund_count']);
        $this->assertSame(50.0, $report['sales']['refunds']);
        $this->assertSame(250.0, $report['sales']['net_sales']);
        $this->assertCount(2, $report['order_types']);
        $this->assertNotEmpty($report['payment_methods']);
        $this->assertCount(1, $report['fund_movements']);
        $this->assertSame(1175.0, $report['cash_summary']['expected']);
    }

    public function test_z_report_html_and_pdf_endpoints_are_accessible(): void
    {
        $this->actingAsCompanyAdmin();
        $shift = $this->startShiftWithCash(500.0);

        $this->get(route('reports.z-report'))
            ->assertRedirect(route('reports.index', ['report' => 'z-report']));

        $this->getJson(route('reports.panel', ['report' => 'z-report']))
            ->assertOk()
            ->assertJsonPath('title', 'Z Report');

        $this->get(route('shifts.z-report', $shift))
            ->assertOk()
            ->assertSee('Z Report', false)
            ->assertSee($shift->branch->name, false);

        $this->get(route('shifts.z-report.pdf', $shift))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_closed_shift_z_report_uses_persisted_cash_totals(): void
    {
        $this->actingAsCompanyAdmin();
        $shift = $this->startShiftWithCash(1000.0);
        $this->createSaleTransaction($shift, amount: 300.0, shiftId: $shift->id, refId: 9001);

        app(ShiftService::class)->closeShift(
            $shift,
            $this->companyAdmin->id,
            [(int) $this->cashSource->id => 1295.0],
            now()->format('Y-m-d H:i:s')
        );

        $report = ShiftZReport::build($shift->fresh(['branch', 'company', 'openedBy', 'closedBy', 'moneySources']), app(ShiftService::class));

        $this->assertFalse($report['is_interim']);
        $this->assertSame(1295.0, $report['cash_summary']['actual']);
        $this->assertSame(-5.0, $report['cash_summary']['difference']);
    }

    public function test_user_from_another_company_cannot_view_z_report(): void
    {
        $this->actingAsCompanyAdmin();
        $shift = $this->startShiftWithCash(100.0);

        $otherCompany = Company::create([
            'name' => 'Other Cafe',
            'slug' => 'other-'.Str::random(8),
            'email' => 'other-'.Str::random(8).'@example.com',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $otherAdmin = User::factory()->create([
            'company_id' => $otherCompany->id,
            'type' => 'company_admin',
            'status' => 'active',
            'can_login' => true,
        ]);

        app(TenantRoleBootstrapService::class)->bootstrapNewCompany($otherCompany, $otherAdmin);

        $this->actingAs($otherAdmin)
            ->get(route('shifts.z-report', $shift))
            ->assertNotFound();
    }

    private function startShiftWithCash(float $openingBalance): Shift
    {
        $shift = app(ShiftService::class)->startShift(
            $this->tenantBranch->id,
            $this->companyAdmin->id,
            now()->toDateString(),
            [(int) $this->cashSource->id => $openingBalance]
        );

        return $shift->fresh(['moneySources', 'branch', 'company', 'openedBy']);
    }

    private function createSaleTransaction(Shift $shift, float $amount, ?int $shiftId, int $refId): Transaction
    {
        return Transaction::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $this->salesAccount->id,
            'amount' => $amount,
            'type' => 'in',
            'payment_method' => 'cash',
            'money_source_id' => $this->cashSource->id,
            'reference_type' => 'sale',
            'date' => $shift->shift_date->format('Y-m-d'),
            'ref_id' => $refId,
            'created_by' => $shift->opened_by,
            'shift_id' => $shiftId,
            'created_at' => $shift->opened_at,
        ]);
    }

    private function createRefundTransaction(Shift $shift, float $amount, ?int $shiftId, int $refId): Transaction
    {
        return Transaction::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $this->salesAccount->id,
            'amount' => $amount,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $this->cashSource->id,
            'reference_type' => 'refund',
            'date' => $shift->shift_date->format('Y-m-d'),
            'ref_id' => $refId,
            'created_by' => $shift->opened_by,
            'shift_id' => $shiftId,
            'created_at' => $shift->opened_at,
        ]);
    }
}
