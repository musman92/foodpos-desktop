<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\Supplier;
use App\Services\CustomerPaymentService;
use App\Services\PartyBalanceAdjustmentService;
use App\Services\PosCreditService;
use App\Services\SupplierPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class PartyBalanceTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private MoneySource $cashSource;

    private Account $purchaseAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();

        $this->cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 50000,
            'active' => true,
        ]);
        $this->cashSource->branches()->attach($this->tenantBranch->id);

        Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Sales',
            'type' => 'income',
            'is_active' => true,
        ]);

        $this->purchaseAccount = Account::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Purchases',
            'type' => 'expense',
            'is_active' => true,
        ]);
    }

    public function test_customer_can_be_created_with_negative_opening_balance(): void
    {
        $this->actingAsCompanyAdmin();

        $response = $this->post(route('customers.store'), [
            'name' => 'Advance Customer',
            'code' => 'CU-ADV',
            'balance' => -500,
            'gender' => 'male',
        ]);

        $response->assertRedirect();

        $customer = Customer::withoutTenantScope()->where('code', 'CU-ADV')->first();
        $this->assertNotNull($customer);
        $this->assertSame(-500.0, (float) $customer->balance);
        $this->assertDatabaseHas('party_balance_adjustments', [
            'party_type' => 'customer',
            'party_id' => $customer->id,
            'previous_balance' => 0,
            'new_balance' => -500,
            'reason' => 'Opening balance',
        ]);
    }

    public function test_customer_payment_overpayment_creates_credit(): void
    {
        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Payer',
            'code' => 'CU-PAY',
            'balance' => 100,
            'is_active' => true,
        ]);

        app(CustomerPaymentService::class)->receivePayment(
            $customer,
            150,
            $this->cashSource->id,
            $this->companyAdmin,
            $this->tenantBranch->id
        );

        $customer->refresh();
        $this->assertSame(-50.0, (float) $customer->balance);
    }

    public function test_customer_advance_reduces_balance(): void
    {
        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Advance',
            'code' => 'CU-AD2',
            'balance' => 0,
            'is_active' => true,
        ]);

        app(CustomerPaymentService::class)->receiveAdvance(
            $customer,
            300,
            $this->cashSource->id,
            $this->companyAdmin,
            $this->tenantBranch->id
        );

        $customer->refresh();
        $this->assertSame(-300.0, (float) $customer->balance);
    }

    public function test_pos_order_applies_signed_balance_delta(): void
    {
        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'POS Customer',
            'code' => 'CU-POS',
            'balance' => -200,
            'is_active' => true,
        ]);

        $service = app(PosCreditService::class);
        $service->applyOrderToCustomerBalance($customer, 500, 300);

        $customer->refresh();
        $this->assertSame(0.0, (float) $customer->balance);
    }

    public function test_balance_adjustment_updates_customer_balance_with_audit(): void
    {
        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Adjust Me',
            'code' => 'CU-ADJ',
            'balance' => 250,
            'is_active' => true,
        ]);

        app(PartyBalanceAdjustmentService::class)->adjustCustomer(
            $customer,
            -100,
            $this->companyAdmin,
            'Correct opening balance'
        );

        $customer->refresh();
        $this->assertSame(-100.0, (float) $customer->balance);
        $this->assertDatabaseHas('party_balance_adjustments', [
            'party_type' => 'customer',
            'party_id' => $customer->id,
            'previous_balance' => 250,
            'new_balance' => -100,
        ]);
    }

    public function test_supplier_advance_creates_prepayment(): void
    {
        $supplier = Supplier::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Prepaid Supplier',
            'code' => 'SU-ADV',
            'balance' => 0,
            'status' => 'active',
        ]);

        app(SupplierPaymentService::class)->payAdvance(
            $supplier,
            400,
            $this->purchaseAccount->id,
            $this->cashSource->id,
            $this->companyAdmin,
            $this->tenantBranch->id
        );

        $supplier->refresh();
        $this->assertSame(-400.0, (float) $supplier->balance);
    }

    public function test_supplier_advance_delete_restores_balances(): void
    {
        $this->actingAsCompanyAdmin();

        $supplier = Supplier::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Prepaid Supplier',
            'code' => 'SU-DEL',
            'balance' => 0,
            'status' => 'active',
        ]);

        $balanceBefore = $this->cashSource->getCurrentBalance($this->tenantBranch->id);

        $payment = app(SupplierPaymentService::class)->payAdvance(
            $supplier,
            400,
            $this->purchaseAccount->id,
            $this->cashSource->id,
            $this->companyAdmin,
            $this->tenantBranch->id
        );

        $supplier->refresh();
        $this->assertSame(-400.0, (float) $supplier->balance);
        $this->assertSame($balanceBefore - 400, $this->cashSource->getCurrentBalance($this->tenantBranch->id));

        app(SupplierPaymentService::class)->deletePayment($payment);

        $supplier->refresh();
        $this->assertSame(0.0, (float) $supplier->balance);
        $this->assertSame($balanceBefore, $this->cashSource->getCurrentBalance($this->tenantBranch->id));
        $this->assertSoftDeleted('supplier_payments', ['id' => $payment->id]);
        $this->assertSoftDeleted('transactions', [
            'ref_id' => $payment->id,
            'reference_type' => 'purchase',
        ]);
    }

    public function test_customer_payment_delete_restores_balances(): void
    {
        $this->actingAsCompanyAdmin();

        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Payer',
            'code' => 'CU-DEL',
            'balance' => 100,
            'is_active' => true,
        ]);

        $balanceBefore = $this->cashSource->getCurrentBalance($this->tenantBranch->id);

        $payment = app(CustomerPaymentService::class)->receivePayment(
            $customer,
            150,
            $this->cashSource->id,
            $this->companyAdmin,
            $this->tenantBranch->id
        );

        $customer->refresh();
        $this->assertSame(-50.0, (float) $customer->balance);
        $this->assertSame($balanceBefore + 150, $this->cashSource->getCurrentBalance($this->tenantBranch->id));

        app(CustomerPaymentService::class)->deletePayment($payment);

        $customer->refresh();
        $this->assertSame(100.0, (float) $customer->balance);
        $this->assertSame($balanceBefore, $this->cashSource->getCurrentBalance($this->tenantBranch->id));
        $this->assertSoftDeleted('customer_payments', ['id' => $payment->id]);
        $this->assertSoftDeleted('transactions', [
            'ref_id' => $payment->id,
            'reference_type' => 'customer_payment',
        ]);
    }

    public function test_customer_payment_delete_reverses_order_allocation(): void
    {
        $this->actingAsCompanyAdmin();

        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Order Payer',
            'code' => 'CU-ORD',
            'balance' => 200,
            'is_active' => true,
        ]);

        $order = Order::withoutGlobalScopes(['tenant', 'branch'])->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-DEL-1',
            'order_type' => 'dine_in',
            'subtotal' => 200,
            'total_amount' => 200,
            'paid_amount' => 0,
            'payment_status' => 'partial',
            'status' => 'completed',
        ]);

        $payment = app(CustomerPaymentService::class)->receivePayment(
            $customer,
            200,
            $this->cashSource->id,
            $this->companyAdmin,
            $this->tenantBranch->id
        );

        $order->refresh();
        $customer->refresh();
        $this->assertSame(0.0, (float) $customer->balance);
        $this->assertSame(200.0, (float) $order->paid_amount);
        $this->assertSame('paid', $order->payment_status);

        app(CustomerPaymentService::class)->deletePayment($payment);

        $order->refresh();
        $customer->refresh();
        $this->assertSame(200.0, (float) $customer->balance);
        $this->assertSame(0.0, (float) $order->paid_amount);
        $this->assertSame('unpaid', $order->payment_status);
    }

    public function test_supplier_payment_destroy_route(): void
    {
        $supplier = Supplier::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Route Supplier',
            'code' => 'SU-RT',
            'balance' => 0,
            'status' => 'active',
        ]);

        $payment = app(SupplierPaymentService::class)->payAdvance(
            $supplier,
            250,
            $this->purchaseAccount->id,
            $this->cashSource->id,
            $this->companyAdmin,
            $this->tenantBranch->id
        );

        $this->actingAsCompanyAdmin()
            ->delete(route('supplier-payments.destroy', $payment))
            ->assertRedirect(route('supplier-payments.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('supplier_payments', ['id' => $payment->id]);
    }

    public function test_customer_payment_destroy_route(): void
    {
        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Route Customer',
            'code' => 'CU-RT',
            'balance' => 50,
            'is_active' => true,
        ]);

        $payment = app(CustomerPaymentService::class)->receivePayment(
            $customer,
            50,
            $this->cashSource->id,
            $this->companyAdmin,
            $this->tenantBranch->id
        );

        $this->actingAsCompanyAdmin()
            ->delete(route('customer-payments.destroy', $payment))
            ->assertRedirect(route('customer-payments.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('customer_payments', ['id' => $payment->id]);
    }
}
