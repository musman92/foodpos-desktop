<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\MoneySource;
use App\Models\MoneySourceFundMovement;
use App\Models\Transaction;
use App\Support\ProfitLossReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class OwnerWithdrawalTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected MoneySource $cashSource;

    protected MoneySource $ownerBucket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();

        $this->cashSource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash Drawer',
            'type' => 'CASH',
            'opening_balance' => 10000,
            'active' => true,
            'is_system' => false,
        ]);
        $this->cashSource->branches()->sync([$this->tenantBranch->id]);

        $this->ownerBucket = MoneySource::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $this->tenantCompany->id,
                'system_key' => MoneySource::SYSTEM_OWNER_WITHDRAWAL,
            ],
            [
                'name' => 'Owner Withdrawal',
                'type' => 'OWNER_DRAW',
                'opening_balance' => 0,
                'active' => true,
                'is_system' => true,
            ]
        );
    }

    public function test_owner_withdrawal_reduces_cash_and_increases_owner_bucket(): void
    {
        $this->actingAsCompanyAdmin();
        $this->openTenantShift();

        $response = $this->post(route('money-sources.owner-withdrawal.store'), [
            'from_money_source_id' => $this->cashSource->id,
            'amount' => 2500,
            'branch_id' => $this->tenantBranch->id,
            'date' => now()->toDateString(),
            'notes' => 'Monthly draw',
        ]);

        $response->assertRedirect(route('money-sources.reports', ['movement_kind' => 'owner_withdrawal']));
        $response->assertSessionHas('success');

        $this->assertSame(7500.0, $this->cashSource->fresh()->getCurrentBalance($this->tenantBranch->id));
        $this->assertSame(2500.0, $this->ownerBucket->fresh()->getCurrentBalance($this->tenantBranch->id));

        $this->assertDatabaseHas('money_source_fund_movements', [
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'from_money_source_id' => $this->cashSource->id,
            'to_money_source_id' => $this->ownerBucket->id,
            'movement_type' => MoneySourceFundMovement::TYPE_OWNER_WITHDRAWAL,
            'amount' => 2500,
        ]);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_owner_withdrawal_does_not_affect_profit_and_loss(): void
    {
        $this->actingAsCompanyAdmin();
        $this->openTenantShift();

        $account = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Utilities',
            'type' => 'expense',
            'is_active' => true,
        ]);

        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $account->id,
            'amount' => 500,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $this->cashSource->id,
            'reference_type' => 'expense',
            'date' => now()->toDateString(),
            'created_by' => $this->companyAdmin->id,
        ]);

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $before = ProfitLossReport::build($this->companyAdmin, $this->tenantBranch->id, $start, $end);

        $this->post(route('money-sources.owner-withdrawal.store'), [
            'from_money_source_id' => $this->cashSource->id,
            'amount' => 3000,
            'branch_id' => $this->tenantBranch->id,
            'date' => now()->toDateString(),
        ])->assertRedirect();

        $after = ProfitLossReport::build($this->companyAdmin, $this->tenantBranch->id, $start, $end);

        $this->assertSame($before['operating_expenses']['total'], $after['operating_expenses']['total']);
        $this->assertSame($before['net_profit'], $after['net_profit']);
    }

    public function test_internal_transfer_moves_balance_between_operational_sources(): void
    {
        $this->actingAsCompanyAdmin();
        $shift = $this->openTenantShift();

        $bankSource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Bank Account',
            'type' => 'BANK',
            'opening_balance' => 0,
            'active' => true,
            'is_system' => false,
        ]);
        $bankSource->branches()->sync([$this->tenantBranch->id]);

        $response = $this->post(route('money-sources.transfer.store'), [
            'from_money_source_id' => $this->cashSource->id,
            'to_money_source_id' => $bankSource->id,
            'amount' => 4000,
            'branch_id' => $this->tenantBranch->id,
            'date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('money-sources.reports'));
        $response->assertSessionHas('success');

        $this->assertSame(6000.0, $this->cashSource->fresh()->getCurrentBalance($this->tenantBranch->id));
        $this->assertSame(4000.0, $bankSource->fresh()->getCurrentBalance($this->tenantBranch->id));
        $this->assertSame(2, Transaction::withoutGlobalScopes()->where('reference_type', 'transfer')->count());
        $this->assertDatabaseCount('money_source_fund_movements', 0);

        $transfers = Transaction::withoutGlobalScopes()->where('reference_type', 'transfer')->get();
        foreach ($transfers as $transfer) {
            $this->assertSame($shift->id, $transfer->shift_id);
            $this->assertNotNull($transfer->business_date);
        }
    }

    public function test_internal_transfer_requires_active_shift(): void
    {
        $this->actingAsCompanyAdmin();

        $bankSource = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Bank Account',
            'type' => 'BANK',
            'opening_balance' => 0,
            'active' => true,
            'is_system' => false,
        ]);
        $bankSource->branches()->sync([$this->tenantBranch->id]);

        $response = $this->post(route('money-sources.transfer.store'), [
            'from_money_source_id' => $this->cashSource->id,
            'to_money_source_id' => $bankSource->id,
            'amount' => 100,
            'branch_id' => $this->tenantBranch->id,
            'date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('shifts.create', ['branch_id' => $this->tenantBranch->id]));
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('reference_type', 'transfer')->count());
    }

    public function test_owner_withdrawal_rejects_insufficient_balance(): void
    {
        $this->actingAsCompanyAdmin();
        $this->openTenantShift();

        $response = $this->from(route('money-sources.owner-withdrawal.create'))
            ->post(route('money-sources.owner-withdrawal.store'), [
                'from_money_source_id' => $this->cashSource->id,
                'amount' => 50000,
                'branch_id' => $this->tenantBranch->id,
                'date' => now()->toDateString(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('money_source_fund_movements', 0);
    }

    public function test_owner_withdrawal_bucket_is_not_selectable_for_payments(): void
    {
        $this->ownerBucket->update(['is_system' => false]);

        $this->assertFalse($this->ownerBucket->fresh()->isSelectableForPayment());

        $this->assertFalse(
            MoneySource::withoutGlobalScopes()
                ->forPayments()
                ->where('company_id', $this->tenantCompany->id)
                ->whereKey($this->ownerBucket->id)
                ->exists()
        );

        $this->assertTrue(
            MoneySource::withoutGlobalScopes()
                ->forPayments()
                ->where('company_id', $this->tenantCompany->id)
                ->whereKey($this->cashSource->id)
                ->exists()
        );
    }
}
