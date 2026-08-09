<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class SupplierPaymentNumberTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_allocate_payment_number_increments_sequentially(): void
    {
        DB::transaction(function () {
            $first = SupplierPayment::allocatePaymentNumber($this->tenantBranch->id);
            $second = SupplierPayment::allocatePaymentNumber($this->tenantBranch->id);

            $this->assertNotSame($first, $second);
            $this->assertSame(
                $this->sequenceFromNumber($first) + 1,
                $this->sequenceFromNumber($second)
            );
        });
    }

    public function test_allocate_skips_existing_soft_deleted_payment_number(): void
    {
        $supplier = Supplier::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Food Supplier',
            'code' => 'SUP01',
            'status' => 'active',
            'balance' => 0,
        ]);

        $account = Account::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Purchase',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $prefix = $this->tenantBranch->code;
        $dateKey = local_now($this->tenantBranch->id)->format('Ymd');
        $existing = sprintf('%s-%s-0001', $prefix, $dateKey);

        SupplierPayment::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
            'money_source_id' => null,
            'created_by' => $this->companyAdmin->id,
            'payment_number' => $existing,
            'payment_date' => now()->toDateString(),
            'total_amount' => 100,
            'payment_method' => 'cash',
        ])->delete();

        $next = DB::transaction(fn () => SupplierPayment::allocatePaymentNumber($this->tenantBranch->id));

        $this->assertSame(2, $this->sequenceFromNumber($next));
    }

    private function sequenceFromNumber(string $paymentNumber): int
    {
        $this->assertMatchesRegularExpression('/-(\d{4})$/', $paymentNumber, $paymentNumber);

        return (int) preg_replace('/^.*-(\d{4})$/', '$1', $paymentNumber);
    }
}
