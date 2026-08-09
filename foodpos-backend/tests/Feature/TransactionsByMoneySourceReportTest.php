<?php

namespace Tests\Feature;

use App\Helpers\TenantDefaultRoles;
use App\Models\Account;
use App\Models\MoneySource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TenantRoleBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class TransactionsByMoneySourceReportTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_report_requires_permission(): void
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
            ->get(route('reports.transactions-by-money-source'))
            ->assertForbidden();
    }

    public function test_report_lists_in_and_out_transactions_by_money_source(): void
    {
        $this->actingAsCompanyAdmin();
        app(TenantRoleBootstrapService::class)->syncDefaultRolesForCompany($this->tenantCompany);

        $cash = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash Drawer',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $cash->branches()->attach($this->tenantBranch->id);

        $account = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);

        $today = now()->toDateString();

        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $account->id,
            'amount' => 500,
            'type' => 'in',
            'payment_method' => 'cash',
            'money_source_id' => $cash->id,
            'reference_type' => 'sale',
            'date' => $today,
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Sale in',
        ]);

        Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $account->id,
            'amount' => 150,
            'type' => 'out',
            'payment_method' => 'cash',
            'money_source_id' => $cash->id,
            'reference_type' => 'expense',
            'date' => $today,
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Expense out',
        ]);

        $this->assertDatabaseCount('transactions', 2);

        $response = $this->getJson(route('reports.panel', [
            'report' => 'transactions-by-money-source',
            'branch_id' => $this->tenantBranch->id,
            'from' => $today,
            'to' => $today,
            'money_source_ids' => [$cash->id],
        ]));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Cash Drawer', $html);
        $this->assertStringContainsString('Sale', $html);
        $this->assertStringContainsString('Expense', $html);
        $this->assertMatchesRegularExpression('/500([.,]00)?/', $html);
        $this->assertMatchesRegularExpression('/150([.,]00)?/', $html);
    }

    public function test_report_filters_multiple_money_sources(): void
    {
        $this->actingAsCompanyAdmin();
        app(TenantRoleBootstrapService::class)->syncDefaultRolesForCompany($this->tenantCompany);

        $cash = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash Drawer',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $bank = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Bank Account',
            'type' => 'BANK',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $other = MoneySource::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Other Wallet',
            'type' => 'APP',
            'opening_balance' => 0,
            'active' => true,
        ]);
        foreach ([$cash, $bank, $other] as $source) {
            $source->branches()->attach($this->tenantBranch->id);
        }

        $account = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);

        $today = now()->toDateString();

        foreach ([[$cash, 100], [$bank, 200], [$other, 300]] as [$source, $amount]) {
            Transaction::withoutGlobalScopes()->create([
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'account_id' => $account->id,
                'amount' => $amount,
                'type' => 'in',
                'payment_method' => 'cash',
                'money_source_id' => $source->id,
                'reference_type' => 'sale',
                'date' => $today,
                'created_by' => $this->companyAdmin->id,
            ]);
        }

        $response = $this->getJson(route('reports.panel', [
            'report' => 'transactions-by-money-source',
            'branch_id' => $this->tenantBranch->id,
            'from' => $today,
            'to' => $today,
            'money_source_ids' => [$cash->id, $bank->id],
        ]));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Cash Drawer', $html);
        $this->assertStringContainsString('Bank Account', $html);
        $this->assertMatchesRegularExpression('/300([.,]00)?/', $html);
        $this->assertStringNotContainsString('Other Wallet', $html);
    }
}
