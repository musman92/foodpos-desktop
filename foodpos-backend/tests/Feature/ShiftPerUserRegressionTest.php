<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Models\MoneySourceFundMovement;
use App\Models\Order;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ShiftService;
use App\Services\TenantRoleBootstrapService;
use App\Support\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class ShiftPerUserRegressionTest extends TestCase
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

    public function test_calculate_expected_balances_counts_legacy_transactions_without_shift_id(): void
    {
        $this->actingAsCompanyAdmin();
        $shift = $this->startShiftWithCash(1000.0);

        $this->createSaleTransaction($shift, amount: 250.0, shiftId: null);

        $expected = app(ShiftService::class)->calculateExpectedBalances($shift->fresh(['moneySources']));

        $this->assertSame(1250.0, $expected[(int) $this->cashSource->id]);
    }

    public function test_calculate_expected_balances_includes_both_stamped_and_legacy_transactions(): void
    {
        $this->actingAsCompanyAdmin();
        $shift = $this->startShiftWithCash(1000.0);

        $this->createSaleTransaction($shift, amount: 100.0, shiftId: null);
        $this->createSaleTransaction($shift, amount: 200.0, shiftId: $shift->id);

        $expected = app(ShiftService::class)->calculateExpectedBalances($shift->fresh(['moneySources']));

        $this->assertSame(1300.0, $expected[(int) $this->cashSource->id]);
    }

    public function test_calculate_expected_balances_ignores_other_users_transactions(): void
    {
        $this->actingAsCompanyAdmin();
        $shift = $this->startShiftWithCash(500.0);
        $otherUser = $this->createStaffUser('other-cashier');

        Transaction::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $this->salesAccount->id,
            'amount' => 999.0,
            'type' => 'in',
            'payment_method' => 'cash',
            'money_source_id' => $this->cashSource->id,
            'reference_type' => 'sale',
            'date' => now()->toDateString(),
            'ref_id' => 1,
            'created_by' => $otherUser->id,
            'shift_id' => null,
        ]);

        $this->createSaleTransaction($shift, amount: 50.0, shiftId: $shift->id);

        $expected = app(ShiftService::class)->calculateExpectedBalances($shift->fresh(['moneySources']));

        $this->assertSame(550.0, $expected[(int) $this->cashSource->id]);
    }

    public function test_calculate_expected_balances_includes_legacy_fund_movements_without_shift_id(): void
    {
        $this->actingAsCompanyAdmin();
        $shift = $this->startShiftWithCash(1000.0);

        MoneySourceFundMovement::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'from_money_source_id' => $this->cashSource->id,
            'to_money_source_id' => $this->cashSource->id,
            'amount' => 150.0,
            'movement_date' => now()->toDateString(),
            'created_by' => $shift->opened_by,
            'shift_id' => null,
            'notes' => 'Legacy withdrawal',
        ]);

        $expected = app(ShiftService::class)->calculateExpectedBalances($shift->fresh(['moneySources']));

        $this->assertSame(850.0, $expected[(int) $this->cashSource->id]);
    }

    public function test_pos_checkout_stamps_order_and_sale_transaction_with_active_shift(): void
    {
        $shift = $this->startShiftWithCash(0.0);
        $menuItem = $this->createSimpleMenuItem();

        $response = $this->actingAsCompanyAdmin()
            ->postJson(route('pos.store'), [
                'mode' => 'pay',
                'type' => 'takeaway',
                'branch_id' => $this->tenantBranch->id,
                'items' => [[
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'name' => $menuItem->name,
                    'quantity' => 1,
                    'unit_price' => 300,
                    'variants' => null,
                    'addons' => null,
                    'special_instructions' => '',
                ]],
                'subtotal' => 300,
                'tax_amount' => 0,
                'discount_type' => null,
                'discount_value' => null,
                'service_charge' => 0,
                'delivery_fee' => 0,
                'total_amount' => 300,
                'paid_amount' => 300,
                'money_source_id' => $this->cashSource->id,
                'payment_status' => 'paid',
                'notes' => 'Shift stamp test',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $order = Order::withoutGlobalScopes()->findOrFail($response->json('order.id'));
        $this->assertSame($shift->id, $order->shift_id);
        $this->assertSame($this->companyAdmin->id, $order->cashier_id);

        $saleTransaction = Transaction::query()
            ->where('reference_type', 'sale')
            ->where('ref_id', $order->id)
            ->first();

        $this->assertNotNull($saleTransaction);
        $this->assertSame($shift->id, $saleTransaction->shift_id);
        $this->assertSame(300.0, (float) $saleTransaction->amount);
    }

    public function test_dashboard_metrics_include_orders_with_and_without_shift_id(): void
    {
        $localNow = local_now($this->tenantBranch->id);
        $today = $localNow->toDateString();

        Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'shift_id' => null,
            'order_number' => 'LEGACY-001',
            'status' => 'completed',
            'type' => 'takeaway',
            'subtotal' => 100,
            'total_amount' => 100,
            'payment_status' => 'paid',
            'created_at' => $localNow,
            'updated_at' => $localNow,
        ]);

        $shift = $this->openTenantShift($this->companyAdmin);

        Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'shift_id' => $shift->id,
            'order_number' => 'SHIFT-001',
            'status' => 'completed',
            'type' => 'takeaway',
            'subtotal' => 200,
            'total_amount' => 200,
            'payment_status' => 'paid',
            'created_at' => $localNow,
            'updated_at' => $localNow,
        ]);

        $this->actingAsCompanyAdmin();

        $summary = DashboardMetrics::summaryForRange(
            $this->companyAdmin,
            $this->tenantBranch->id,
            $today,
            $today
        );

        $this->assertSame(300.0, $summary['revenue']);
        $this->assertSame(2, $summary['transactions']);
    }

    public function test_company_admin_can_open_close_form_for_another_users_shift(): void
    {
        $cashier = $this->createStaffUser('cashier-one');
        $shift = $this->openTenantShift($cashier);

        $this->actingAsCompanyAdmin()
            ->get(route('shifts.edit', $shift))
            ->assertOk();
    }

    public function test_user_with_active_shift_can_access_pos(): void
    {
        $this->startShiftWithCash(0.0);

        $this->actingAsCompanyAdmin()
            ->get(route('pos.index'))
            ->assertOk();
    }

    public function test_shift_close_expected_balance_reflects_stamped_pos_sale(): void
    {
        $this->actingAsCompanyAdmin();
        $shift = $this->startShiftWithCash(1000.0);

        $this->createSaleTransaction($shift, amount: 400.0, shiftId: $shift->id);

        $expected = app(ShiftService::class)->calculateExpectedBalances($shift->fresh(['moneySources']));

        $this->assertSame(1400.0, $expected[(int) $this->cashSource->id]);
    }

    private function startShiftWithCash(float $openingBalance): Shift
    {
        $shift = app(ShiftService::class)->startShift(
            $this->tenantBranch->id,
            $this->companyAdmin->id,
            now()->toDateString(),
            [(int) $this->cashSource->id => $openingBalance]
        );

        return $shift->fresh(['moneySources']);
    }

    private function createSaleTransaction(Shift $shift, float $amount, ?int $shiftId): Transaction
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
            'ref_id' => random_int(1000, 9999),
            'created_by' => $shift->opened_by,
            'shift_id' => $shiftId,
            'created_at' => $shift->opened_at,
        ]);
    }

    private function createStaffUser(string $suffix): User
    {
        $user = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => true,
        ]);

        app(TenantRoleBootstrapService::class)->bootstrapNewCompany($this->tenantCompany, $user);

        return $user;
    }

    private function createSimpleMenuItem(): MenuItem
    {
        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Drinks',
            'code' => 'DRK',
            'slug' => 'drinks-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'single',
            'name' => 'Bottled Water',
            'slug' => 'water-'.uniqid(),
            'sku' => 'WATER-'.uniqid(),
            'price' => 300,
            'cost' => 50,
            'is_available' => true,
            'track_inventory' => false,
            'sort_order' => 1,
        ]);
    }
}
