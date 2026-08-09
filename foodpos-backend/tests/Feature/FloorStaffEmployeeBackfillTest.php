<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\FloorStaffEmployeeBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class FloorStaffEmployeeBackfillTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_dry_run_lists_waiter_rider_users_without_writing(): void
    {
        $waiter = $this->makeFloorUser('Dry Waiter', 'waiter');
        $rider = $this->makeFloorUser('Dry Rider', 'rider');

        $exit = Artisan::call('hr:backfill-floor-employees', [
            '--company' => $this->tenantCompany->id,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('would_create', $output);
        $this->assertStringContainsString('Dry Waiter', $output);
        $this->assertStringContainsString('Dry Rider', $output);
        $this->assertSame(0, EmployeeProfile::withoutGlobalScopes()->whereIn('user_id', [$waiter->id, $rider->id])->count());
    }

    public function test_backfill_creates_profiles_and_skips_existing(): void
    {
        $waiter = $this->makeFloorUser('Live Waiter', 'waiter');
        $both = $this->makeFloorUser('Live Both', 'waiter_rider');
        $already = $this->makeFloorUser('Already Linked', 'rider');

        EmployeeProfile::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'user_id' => $already->id,
            'employee_number' => 'EMP-READY',
            'employment_status' => 'active',
            'pay_frequency' => 'daily',
            'pay_rate' => 500,
            'standard_hours_per_day' => 8,
            'overtime_rate' => 0,
            'short_hours_policy' => 'full_day',
            'working_days' => EmployeeProfile::DEFAULT_WORKING_DAYS,
        ]);

        $summary = app(FloorStaffEmployeeBackfillService::class)->backfill(
            (int) $this->tenantCompany->id,
            false
        );

        $this->assertSame(3, $summary['candidates']);
        $this->assertSame(2, $summary['created']);
        $this->assertSame(1, $summary['skipped']);

        $waiterProfile = EmployeeProfile::withoutGlobalScopes()->where('user_id', $waiter->id)->first();
        $this->assertNotNull($waiterProfile);
        $this->assertSame('Waiter', $waiterProfile->designation);
        $this->assertSame('active', $waiterProfile->employment_status);
        $this->assertNotEmpty($waiterProfile->employee_number);

        $bothProfile = EmployeeProfile::withoutGlobalScopes()->where('user_id', $both->id)->first();
        $this->assertNotNull($bothProfile);
        $this->assertSame('Waiter / Rider', $bothProfile->designation);

        $this->assertSame(1, EmployeeProfile::withoutGlobalScopes()->where('user_id', $already->id)->count());
    }

    public function test_backfill_restores_soft_deleted_profile(): void
    {
        $rider = $this->makeFloorUser('Soft Rider', 'rider');
        $profile = EmployeeProfile::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'user_id' => $rider->id,
            'employee_number' => 'EMP-SOFT',
            'employment_status' => 'active',
            'pay_frequency' => 'monthly',
            'pay_rate' => 0,
            'standard_hours_per_day' => 8,
            'overtime_rate' => 0,
            'short_hours_policy' => 'full_day',
            'working_days' => EmployeeProfile::DEFAULT_WORKING_DAYS,
        ]);
        $profile->delete();

        $summary = app(FloorStaffEmployeeBackfillService::class)->backfill(
            (int) $this->tenantCompany->id,
            false
        );

        $this->assertSame(1, $summary['restored']);
        $this->assertFalse($profile->fresh()->trashed());
    }

    private function makeFloorUser(string $name, string $type): User
    {
        $user = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'name' => $name,
            'type' => $type,
            'status' => 'active',
            'can_login' => false,
            'salary' => 0,
        ]);
        $user->branches()->attach($this->tenantBranch->id, ['is_primary' => true]);

        return $user;
    }
}
