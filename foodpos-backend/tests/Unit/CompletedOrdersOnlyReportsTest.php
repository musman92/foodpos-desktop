<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Support\OrderHistoryReport;
use App\Support\ProfitLossReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class CompletedOrdersOnlyReportsTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->actingAsCompanyAdmin();
    }

    public function test_profit_loss_and_order_history_exclude_open_and_placed_orders(): void
    {
        $branchId = (int) $this->tenantBranch->id;
        $today = now()->toDateString();

        $this->makeOrder('completed', 1000, $today);
        $this->makeOrder('open', 400, $today);
        $this->makeOrder('placed', 250, $today);
        $this->makeOrder('cancelled', 900, $today);

        $pl = ProfitLossReport::build($this->companyAdmin, $branchId, $today, $today);
        $this->assertSame(1, $pl['revenue']['order_count']);
        $this->assertSame(1000.0, $pl['revenue']['gross_sales']);
        $this->assertSame(1000.0, $pl['revenue']['net_sales']);

        $history = OrderHistoryReport::summarizeFromQuery(
            OrderHistoryReport::baseQuery($this->companyAdmin, [
                'branch_id' => $branchId,
                'from' => $today,
                'to' => $today,
            ])
        );

        $this->assertSame(1, $history['order_count']);
        $this->assertSame(1000.0, $history['total_amount']);
    }

    private function makeOrder(string $status, float $amount, string $businessDate): Order
    {
        return Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'T-'.Str::upper(Str::random(6)),
            'type' => 'takeaway',
            'status' => $status,
            'payment_status' => $status === 'completed' ? 'paid' : 'unpaid',
            'subtotal' => $amount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => $status === 'completed' ? $amount : 0,
            'business_date' => $businessDate,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }
}
