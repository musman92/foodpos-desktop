<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class SystemAccountLockTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_default_accounts_cannot_be_edited_or_deleted(): void
    {
        $account = Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'FOC',
            'type' => 'expense',
            'is_active' => true,
            'is_deletable' => false,
        ]);

        $this->assertTrue($account->isSystemAccount());
        $this->assertFalse($account->canBeEdited());
        $this->assertFalse($account->canBeDeleted());

        $this->actingAsCompanyAdmin()
            ->get(route('accounts.edit', $account))
            ->assertRedirect(route('accounts.index'));

        $this->actingAsCompanyAdmin()
            ->put(route('accounts.update', $account), [
                'name' => 'Renamed FOC',
                'type' => 'income',
                'is_active' => 1,
            ])
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'FOC',
            'type' => 'expense',
        ]);

        $this->actingAsCompanyAdmin()
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'FOC',
        ]);
    }

    public function test_user_accounts_can_be_edited(): void
    {
        $account = Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Marketing',
            'type' => 'expense',
            'is_active' => true,
            'is_deletable' => true,
        ]);

        $this->actingAsCompanyAdmin()
            ->put(route('accounts.update', $account), [
                'name' => 'Ads',
                'type' => 'expense',
                'is_active' => 1,
            ])
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Ads',
        ]);
    }
}
