<?php

namespace Tests\Feature;

use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class DashboardShiftReminderTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_dashboard_shows_shift_reminder_when_user_has_no_active_shift(): void
    {
        $this->actingAsCompanyAdmin()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Start your shift', false);
    }

    public function test_dashboard_hides_shift_reminder_when_user_has_active_shift(): void
    {
        $this->openTenantShift();

        $this->actingAsCompanyAdmin()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Start your shift', false);
    }

    public function test_dashboard_hides_shift_reminder_when_stale_session_flag_exists_but_shift_is_active(): void
    {
        $this->openTenantShift();

        $this->actingAsCompanyAdmin()
            ->withSession([
                'shift_reminder' => ['branch_id' => $this->tenantBranch->id],
                'current_branch_id' => $this->tenantBranch->id,
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Start your shift', false);

        $this->assertFalse(session()->has('shift_reminder'));
    }

    public function test_starting_shift_clears_shift_reminder_session(): void
    {
        app(ShiftService::class)->startShift(
            $this->tenantBranch->id,
            $this->companyAdmin->id,
            now()->toDateString(),
            []
        );

        $this->actingAsCompanyAdmin()
            ->withSession([
                'shift_reminder' => ['branch_id' => $this->tenantBranch->id],
                'current_branch_id' => $this->tenantBranch->id,
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Start your shift', false);
    }
}
