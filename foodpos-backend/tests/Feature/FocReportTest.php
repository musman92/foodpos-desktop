<?php

namespace Tests\Feature;

use App\Helpers\TenantDefaultRoles;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\TenantRoleBootstrapService;
use App\Support\FocReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class FocReportTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_foc_report_requires_permission(): void
    {
        $cashier = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => true,
        ]);
        $cashier->branches()->attach($this->tenantBranch->id, ['is_primary' => true]);

        app(TenantRoleBootstrapService::class)->syncDefaultRolesForCompany($this->tenantCompany);
        setPermissionsTeamId($this->tenantCompany->id);
        $cashier->assignRole(TenantDefaultRoles::CASHIER);

        $this->actingAs($cashier)
            ->withSession(['current_branch_id' => $this->tenantBranch->id])
            ->get(route('reports.foc'))
            ->assertForbidden();
    }

    public function test_foc_report_lists_orders_within_date_range(): void
    {
        $branchId = (int) $this->tenantBranch->id;
        $inRangeAt = local_now($branchId)->copy()->subDay();
        $outOfRangeAt = local_now($branchId)->copy()->subMonths(2);

        $inRange = $this->createFocOrder(350, $inRangeAt);
        $this->createFocOrder(120, $outOfRangeAt);

        $from = local_now($branchId)->copy()->subDays(7)->toDateString();
        $to = local_today($branchId);

        $report = FocReport::build($this->companyAdmin, $branchId, $from, $to);

        $this->assertSame(1, $report['summary']['order_count']);
        $this->assertSame(350.0, $report['summary']['total_value']);
        $this->assertCount(1, $report['rows']);
        $this->assertSame($inRange->order_number, $report['rows'][0]['order_number']);
        $this->assertSame(350.0, $report['rows'][0]['total_amount']);

        $response = $this->actingAsCompanyAdmin()->get(route('reports.foc', [
            'branch_id' => $branchId,
            'from' => $from,
            'to' => $to,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('report=foc', $response->headers->get('Location'));
        $this->assertStringContainsString('branch_id='.$branchId, $response->headers->get('Location'));
    }

    private function createFocOrder(float $total, $createdAt): Order
    {
        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'FOC-'.Str::upper(Str::random(6)),
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'foc',
            'subtotal' => $total,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => $total,
            'paid_amount' => 0,
            'completed_at' => $createdAt,
        ]);

        OrderItem::withoutGlobalScopes()->create([
            'order_id' => $order->id,
            'item_name' => 'Complimentary item',
            'quantity' => 1,
            'unit_price' => $total,
            'total_price' => $total,
            'status' => 'served',
        ]);

        DB::table('orders')->where('id', $order->id)->update([
            'created_at' => $createdAt->utc()->format('Y-m-d H:i:s'),
            'updated_at' => $createdAt->utc()->format('Y-m-d H:i:s'),
            'completed_at' => $createdAt->utc()->format('Y-m-d H:i:s'),
        ]);

        return $order->fresh();
    }
}
