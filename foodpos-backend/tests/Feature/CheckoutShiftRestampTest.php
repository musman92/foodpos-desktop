<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\Shift;
use App\Models\Transaction;
use App\Services\PosPrintReadinessService;
use App\Services\ShiftService;
use App\Support\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class CheckoutShiftRestampTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private MoneySource $cashSource;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->actingAsCompanyAdmin();

        $this->mock(PosPrintReadinessService::class, function ($mock) {
            $mock->shouldReceive('readinessErrorResponse')->andReturn(null);
            $mock->shouldReceive('check')->andReturn([
                'ok' => true,
                'errors' => [],
                'warnings' => [],
            ]);
        });

        $this->cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'active' => true,
            'opening_balance' => 0,
        ]);
        $this->cashSource->branches()->attach($this->tenantBranch->id);

        Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Mains',
            'code' => 'MN',
            'slug' => 'mains-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Burger',
            'slug' => 'burger-'.uniqid(),
            'type' => 'simple',
            'price' => 500,
            'cost' => 0,
            'is_available' => true,
            'track_inventory' => false,
        ]);
    }

    public function test_checkout_restamps_open_tab_to_current_shift(): void
    {
        $shiftService = app(ShiftService::class);
        $dayA = now()->subDay()->toDateString();
        $dayB = now()->toDateString();

        $shiftA = $shiftService->startShift(
            $this->tenantBranch->id,
            $this->companyAdmin->id,
            $dayA,
            [(int) $this->cashSource->id => 0]
        );

        $order = $this->createOpenTab();
        $this->assertSame($shiftA->id, $order->shift_id);
        $this->assertSame($dayA, substr((string) $order->business_date, 0, 10));

        $shiftService->closeShift($shiftA, $this->companyAdmin->id, [(int) $this->cashSource->id => 0]);

        $shiftB = $shiftService->startShift(
            $this->tenantBranch->id,
            $this->companyAdmin->id,
            $dayB,
            [(int) $this->cashSource->id => 0]
        );

        $this->tenantCompany->update([
            'settings' => array_merge($this->tenantCompany->settings ?? [], [
                'activity_logging_enabled' => true,
            ]),
        ]);
        \App\Services\ActivityLogger::clearCache();

        $response = $this->postJson(route('pos.orders.checkout', $order), [
            'money_source_id' => $this->cashSource->id,
            'paid_amount' => 500,
            'payment_status' => 'paid',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame($shiftB->id, $order->shift_id);
        $this->assertSame($dayB, substr((string) $order->business_date, 0, 10));

        $sale = Transaction::withoutGlobalScopes()
            ->where('reference_type', 'sale')
            ->where('ref_id', $order->id)
            ->first();

        $this->assertNotNull($sale);
        $this->assertSame($shiftB->id, $sale->shift_id);
        $this->assertSame($dayB, substr((string) $sale->business_date, 0, 10));
        $this->assertSame($dayB, substr((string) $sale->date, 0, 10));

        $this->assertTrue(
            ActivityLog::query()
                ->where('action', 'order.shift_restamped')
                ->where('subject_id', $order->id)
                ->exists()
        );
    }

    public function test_dashboard_includes_order_by_checkout_business_date_not_tab_created_at(): void
    {
        $shiftService = app(ShiftService::class);
        $dayA = now()->subDay()->toDateString();
        $dayB = now()->toDateString();

        $shiftService->startShift(
            $this->tenantBranch->id,
            $this->companyAdmin->id,
            $dayA,
            [(int) $this->cashSource->id => 0]
        );

        $order = $this->createOpenTab();
        $order->forceFill(['created_at' => now()->subDay()])->saveQuietly();

        $shiftA = Shift::query()->findOrFail($order->shift_id);
        $shiftService->closeShift($shiftA, $this->companyAdmin->id, [(int) $this->cashSource->id => 0]);

        $shiftService->startShift(
            $this->tenantBranch->id,
            $this->companyAdmin->id,
            $dayB,
            [(int) $this->cashSource->id => 0]
        );

        $this->postJson(route('pos.orders.checkout', $order), [
            'money_source_id' => $this->cashSource->id,
            'paid_amount' => 500,
            'payment_status' => 'paid',
        ])->assertOk();

        $summaryToday = DashboardMetrics::summaryForRange(
            $this->companyAdmin,
            $this->tenantBranch->id,
            $dayB,
            $dayB
        );
        $summaryYesterday = DashboardMetrics::summaryForRange(
            $this->companyAdmin,
            $this->tenantBranch->id,
            $dayA,
            $dayA
        );

        $this->assertSame(500.0, $summaryToday['revenue']);
        $this->assertSame(0.0, $summaryYesterday['revenue']);
    }

    private function createOpenTab(): Order
    {
        $response = $this->postJson(route('pos.store'), [
            'mode' => 'tab',
            'type' => 'takeaway',
            'branch_id' => $this->tenantBranch->id,
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'item_name' => $this->menuItem->name,
                'name' => $this->menuItem->name,
                'quantity' => 1,
                'unit_price' => 500,
                'variants' => null,
                'addons' => null,
                'special_instructions' => '',
            ]],
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_type' => null,
            'discount_value' => null,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500,
            'payment_status' => 'unpaid',
            'notes' => null,
        ]);

        $response->assertOk();

        return Order::withoutGlobalScopes()->findOrFail($response->json('order.id'));
    }
}
