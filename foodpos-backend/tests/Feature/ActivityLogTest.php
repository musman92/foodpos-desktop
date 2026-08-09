<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\MoneySource;
use App\Models\Transaction;
use App\Services\ActivityLogger;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private MoneySource $cashSource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        ActivityLogger::clearCache();

        $this->cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash Drawer',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
        ]);
        $this->cashSource->branches()->attach($this->tenantBranch->id);
    }

    public function test_activity_log_index_requires_permission_or_admin(): void
    {
        $this->actingAsCompanyAdmin()
            ->get(route('activity-logs.index'))
            ->assertOk();
    }

    public function test_no_logs_written_when_setting_disabled(): void
    {
        $this->actingAsCompanyAdmin();
        $this->assertFalse((bool) ($this->tenantCompany->settings['activity_logging_enabled'] ?? false));

        app(ShiftService::class)->startShift(
            $this->tenantBranch->id,
            $this->companyAdmin->id,
            now()->toDateString(),
            [(int) $this->cashSource->id => 100]
        );

        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_shift_open_and_transaction_are_logged_when_enabled(): void
    {
        $this->enableLogging();
        $this->actingAsCompanyAdmin();

        $shift = app(ShiftService::class)->startShift(
            $this->tenantBranch->id,
            $this->companyAdmin->id,
            now()->toDateString(),
            [(int) $this->cashSource->id => 250]
        );

        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $this->tenantCompany->id,
            'action' => 'shift.opened',
            'shift_id' => $shift->id,
        ]);

        $openLog = ActivityLog::query()->where('action', 'shift.opened')->first();
        $this->assertNotNull($openLog);
        $this->assertSame(250.0, (float) data_get($openLog->properties, 'money_sources.0.opening_balance'));

        Transaction::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'account_id' => \App\Models\Account::withoutTenantScope()->create([
                'company_id' => $this->tenantCompany->id,
                'name' => 'Sales',
                'type' => 'income',
                'is_active' => true,
            ])->id,
            'amount' => 50,
            'type' => 'in',
            'payment_method' => 'cash',
            'money_source_id' => $this->cashSource->id,
            'reference_type' => 'sale',
            'date' => now()->toDateString(),
            'created_by' => $this->companyAdmin->id,
            'shift_id' => $shift->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $this->tenantCompany->id,
            'action' => 'transaction.created',
        ]);
    }

    public function test_preferences_can_toggle_activity_logging(): void
    {
        $this->actingAsCompanyAdmin()
            ->put(route('company-settings.update.preferences', $this->tenantCompany), [
                'currency' => 'PKR',
                'timezone' => 'Asia/Karachi',
                'currency_position' => 'left',
                'decimal_points' => 2,
                'time_format' => '12',
                'date_format' => 'd-m-Y',
                'week_starts_on' => 'monday',
                'listing_per_page' => 25,
                'activity_logging_enabled' => 1,
            ])
            ->assertRedirect();

        $this->tenantCompany->refresh();
        $this->assertTrue((bool) ($this->tenantCompany->settings['activity_logging_enabled'] ?? false));
    }

    public function test_activity_logs_page_can_toggle_logging_on(): void
    {
        $this->actingAsCompanyAdmin()
            ->post(route('activity-logs.toggle'), ['enabled' => 1])
            ->assertRedirect(route('activity-logs.index'));

        $this->tenantCompany->refresh();
        $this->assertTrue(filter_var($this->tenantCompany->settings['activity_logging_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN));
    }

    public function test_purchase_is_logged_when_enabled(): void
    {
        $this->enableLogging();
        $this->actingAsCompanyAdmin();

        \App\Models\Purchase::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'created_by' => $this->companyAdmin->id,
            'purchase_number' => 'PO-LOG-001',
            'purchase_date' => now()->toDateString(),
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $this->tenantCompany->id,
            'action' => 'purchase.created',
        ]);
    }

    private function enableLogging(): void
    {
        $settings = $this->tenantCompany->settings ?? [];
        $settings['activity_logging_enabled'] = true;
        $this->tenantCompany->update(['settings' => $settings]);
        ActivityLogger::clearCache();
    }
}
