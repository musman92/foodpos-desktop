<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class CustomerPhoneUniquenessTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_cannot_create_customer_with_phone_used_by_active_customer(): void
    {
        $this->actingAsCompanyAdmin();

        Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Existing Customer',
            'code' => 'CU99',
            'phone' => '03471399927',
            'is_active' => true,
        ]);

        $response = $this->from(route('customers.create'))
            ->post(route('customers.store'), [
                'name' => 'Uzair Room No 13',
                'code' => 'CU98',
                'phone' => '03471399927',
                'gender' => 'male',
                'notes' => 'Vision Tower Peshawar',
            ]);

        $response->assertRedirect(route('customers.create'));
        $response->assertSessionHasErrors('phone');
        $this->assertCount(1, session('errors')->get('phone'));
        $this->assertSame(
            2,
            Customer::withoutTenantScope()->where('company_id', $this->tenantCompany->id)->count()
        );
    }

    public function test_validation_failure_keeps_entered_customer_data(): void
    {
        $this->actingAsCompanyAdmin();

        Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Existing Customer',
            'code' => 'CU99',
            'phone' => '03471399927',
            'is_active' => true,
        ]);

        $response = $this->from(route('customers.create'))
            ->post(route('customers.store'), [
                'name' => 'Uzair Room No 13',
                'code' => 'CU98',
                'email' => 'uzair@example.com',
                'phone' => '03471399927',
                'gender' => 'male',
                'notes' => 'Vision Tower Peshawar',
            ]);

        $response->assertRedirect(route('customers.create'));
        $response->assertSessionHasInput('name', 'Uzair Room No 13');
        $response->assertSessionHasInput('code', 'CU98');
        $response->assertSessionHasInput('email', 'uzair@example.com');
        $response->assertSessionHasInput('phone', '03471399927');
        $response->assertSessionHasInput('gender', 'male');
        $response->assertSessionHasInput('notes', 'Vision Tower Peshawar');
    }

    public function test_cannot_create_customer_with_phone_used_by_soft_deleted_customer(): void
    {
        $this->actingAsCompanyAdmin();

        $deleted = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Deleted Customer',
            'code' => 'CU97',
            'phone' => '03471399927',
            'is_active' => true,
        ]);
        // Simulate legacy soft-deleted row that still holds the phone (pre-migration data).
        DB::table('customers')->where('id', $deleted->id)->update(['deleted_at' => now()]);

        $response = $this->from(route('customers.create'))
            ->post(route('customers.store'), [
                'name' => 'Uzair Room No 13',
                'code' => 'CU96',
                'phone' => '03471399927',
                'gender' => 'male',
            ]);

        $response->assertRedirect(route('customers.create'));
        $response->assertSessionHasErrors('phone');
        $this->assertCount(1, session('errors')->get('phone'));
        $this->assertStringContainsString(
            'deleted customer',
            session('errors')->first('phone')
        );
    }

    public function test_two_deleted_customers_with_same_phone_get_unique_archived_values(): void
    {
        $this->actingAsCompanyAdmin();

        $first = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'First Customer',
            'code' => 'CU93',
            'phone' => '03471399927',
            'is_active' => true,
        ]);

        $this->delete(route('customers.destroy', $first))->assertRedirect();

        $this->post(route('customers.store'), [
            'name' => 'Second Customer',
            'code' => 'CU92',
            'phone' => '03471399927',
            'gender' => 'male',
        ])->assertRedirect(route('customers.index'));

        $second = Customer::withoutTenantScope()
            ->where('company_id', $this->tenantCompany->id)
            ->where('phone', '03471399927')
            ->whereNull('deleted_at')
            ->firstOrFail();

        $this->delete(route('customers.destroy', $second))->assertRedirect();

        $first = Customer::withoutTenantScope()->withTrashed()->find($first->id);
        $second = Customer::withoutTenantScope()->withTrashed()->find($second->id);

        $this->assertSame('del-'.$first->id.'-03471399927', $first->phone);
        $this->assertSame('del-'.$second->id.'-03471399927', $second->phone);
    }

    public function test_soft_deleting_customer_releases_phone_for_reuse(): void
    {
        $this->actingAsCompanyAdmin();

        $customer = Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Old Customer',
            'code' => 'CU95',
            'phone' => '03471399927',
            'is_active' => true,
        ]);

        $this->delete(route('customers.destroy', $customer))->assertRedirect();

        $customer = Customer::withoutTenantScope()->withTrashed()->find($customer->id);
        $this->assertNotNull($customer);
        $this->assertSame('del-'.$customer->id.'-03471399927', $customer->phone);

        $response = $this->post(route('customers.store'), [
            'name' => 'Uzair Room No 13',
            'code' => 'CU94',
            'phone' => '03471399927',
            'gender' => 'male',
        ]);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('success');
        $this->assertTrue(
            Customer::withoutTenantScope()
                ->where('company_id', $this->tenantCompany->id)
                ->where('phone', '03471399927')
                ->whereNull('deleted_at')
                ->exists()
        );
    }

    public function test_same_phone_is_allowed_for_different_tenants(): void
    {
        $this->actingAsCompanyAdmin();

        Customer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Tenant A Customer',
            'code' => 'CU88',
            'phone' => '03471399927',
            'is_active' => true,
        ]);

        $otherCompany = \App\Models\Company::create([
            'name' => 'Other Cafe',
            'slug' => 'other-'.uniqid(),
            'email' => 'other-'.uniqid().'@example.com',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $otherBranch = \App\Models\Branch::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Branch',
            'code' => 'OB01',
            'timezone' => 'Asia/Karachi',
            'status' => 'active',
        ]);

        $otherAdmin = \App\Models\User::factory()->create([
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'type' => 'company_admin',
            'status' => 'active',
            'can_login' => true,
        ]);

        app(\App\Services\TenantRoleBootstrapService::class)->bootstrapNewCompany($otherCompany, $otherAdmin);

        $this->actingAs($otherAdmin)
            ->withSession(['current_branch_id' => $otherBranch->id])
            ->post(route('customers.store'), [
                'name' => 'Tenant B Customer',
                'code' => 'CU88',
                'phone' => '03471399927',
                'gender' => 'male',
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $this->assertTrue(
            Customer::withoutGlobalScopes()
                ->where('company_id', $otherCompany->id)
                ->where('phone', '03471399927')
                ->exists()
        );
    }
}
