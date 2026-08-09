<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class ManualTransactionModificationTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();

        $this->account = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Miscellaneous expense',
            'type' => 'expense',
            'is_active' => true,
            'is_deletable' => true,
        ]);
    }

    public function test_manually_entered_transaction_can_be_edited(): void
    {
        $transaction = $this->createTransaction(true);

        $response = $this->actingAsCompanyAdmin()->put(
            route('transactions.update', $transaction),
            [
                'branch_id' => $this->tenantBranch->id,
                'account_id' => $this->account->id,
                'amount' => 175.50,
                'type' => 'out',
                'payment_method' => 'cash',
                'date' => now()->toDateString(),
                'notes' => 'Corrected amount',
            ]
        );

        $response->assertRedirect(route('transactions.index'));
        $transaction->refresh();
        $this->assertSame(175.50, (float) $transaction->amount);
        $this->assertSame('Corrected amount', $transaction->notes);
        $this->assertTrue($transaction->is_manual);
    }

    public function test_transaction_created_from_transactions_screen_is_marked_manual(): void
    {
        $this->actingAsCompanyAdmin()->post(route('transactions.store'), [
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $this->account->id,
            'amount' => 250,
            'type' => 'out',
            'payment_method' => 'cash',
            'reference_type' => 'expense',
            'date' => now()->toDateString(),
            'notes' => 'Direct expense entry',
        ])->assertRedirect(route('transactions.index'));

        $transaction = Transaction::withoutGlobalScopes()
            ->where('notes', 'Direct expense entry')
            ->firstOrFail();

        $this->assertTrue($transaction->is_manual);
        $this->assertSame('expense', $transaction->reference_type);
    }

    public function test_manually_entered_transaction_can_be_soft_deleted(): void
    {
        $transaction = $this->createTransaction(true);

        $this->actingAsCompanyAdmin()
            ->delete(route('transactions.destroy', $transaction))
            ->assertRedirect(route('transactions.index'));

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    public function test_system_generated_transaction_cannot_be_edited_or_deleted(): void
    {
        $transaction = $this->createTransaction(false, 'sale');

        $this->actingAsCompanyAdmin()
            ->get(route('transactions.edit', $transaction))
            ->assertForbidden();

        $this->actingAsCompanyAdmin()
            ->put(route('transactions.update', $transaction), [
                'branch_id' => $this->tenantBranch->id,
                'account_id' => $this->account->id,
                'amount' => 1,
                'type' => 'in',
                'payment_method' => 'cash',
                'date' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAsCompanyAdmin()
            ->delete(route('transactions.destroy', $transaction))
            ->assertForbidden();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'deleted_at' => null,
            'amount' => 100,
        ]);
    }

    public function test_list_only_shows_modification_actions_for_manual_transactions(): void
    {
        $manual = $this->createTransaction(true);
        $system = $this->createTransaction(false, 'sale');

        $response = $this->actingAsCompanyAdmin()->get(route('transactions.index'));

        $response->assertOk();
        $response->assertSee(route('transactions.edit', $manual), false);
        $response->assertSee('action="'.route('transactions.destroy', $manual).'"', false);
        $response->assertDontSee(route('transactions.edit', $system), false);
        $response->assertDontSee('action="'.route('transactions.destroy', $system).'"', false);
    }

    public function test_index_filters_by_date_range(): void
    {
        $inRange = $this->createTransaction(true);
        $inRange->forceFill([
            'date' => '2026-07-20',
            'notes' => 'IN-RANGE-TXN',
        ])->save();

        $outOfRange = $this->createTransaction(true);
        $outOfRange->forceFill([
            'date' => '2026-07-10',
            'notes' => 'OUT-OF-RANGE-TXN',
        ])->save();

        $response = $this->actingAsCompanyAdmin()->get(route('transactions.index', [
            'from' => '2026-07-20',
            'to' => '2026-07-20',
            'type' => 'out',
        ]));

        $response->assertOk();
        $response->assertSee('name="from"', false);
        $response->assertSee('name="to"', false);
        $response->assertSee('action="'.route('transactions.destroy', $inRange).'"', false);
        $response->assertDontSee('action="'.route('transactions.destroy', $outOfRange).'"', false);
    }

    protected function createTransaction(bool $manual, ?string $referenceType = null): Transaction
    {
        return Transaction::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => $this->account->id,
            'amount' => 100,
            'type' => $referenceType === 'sale' ? 'in' : 'out',
            'payment_method' => 'cash',
            'reference_type' => $referenceType,
            'date' => now()->toDateString(),
            'created_by' => $this->companyAdmin->id,
            'is_manual' => $manual,
            'notes' => $manual ? 'Manual entry' : 'Order #TEST-1',
        ]);
    }
}
