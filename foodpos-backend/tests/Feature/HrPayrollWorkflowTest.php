<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AttendanceRecord;
use App\Models\EmployeeLedgerEntry;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeePayrollAdjustment;
use App\Models\EmployeeProfile;
use App\Models\MoneySource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EmployeePaymentService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class HrPayrollWorkflowTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected User $employee;

    protected EmployeeProfile $profile;

    protected MoneySource $cashSource;

    protected Account $salaryAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();
        $this->actingAsCompanyAdmin();

        $this->employee = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => false,
        ]);
        $this->employee->branches()->sync([
            $this->tenantBranch->id => ['is_primary' => true],
        ]);
        $this->profile = EmployeeProfile::create([
            'company_id' => $this->tenantCompany->id,
            'user_id' => $this->employee->id,
            'employee_number' => 'EMP-001',
            'employment_status' => 'active',
            'pay_frequency' => 'daily',
            'pay_rate' => 1000,
            'standard_hours_per_day' => 10,
            'overtime_rate' => 150,
            'short_hours_policy' => 'full_day',
            'working_days' => [1, 2, 3, 4, 5, 6, 7],
        ]);
        $this->cashSource = MoneySource::create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash Drawer',
            'type' => 'CASH',
            'opening_balance' => 10000,
            'active' => true,
            'is_system' => false,
        ]);
        $this->cashSource->branches()->sync([$this->tenantBranch->id]);
        $this->salaryAccount = Account::ensureSystemAccount(
            (int) $this->tenantCompany->id,
            'Salary',
            'expense'
        );
    }

    public function test_complete_payroll_flow_calculates_overtime_adjustments_and_advance(): void
    {
        $date = '2026-07-13';
        Carbon::setTestNow($date.' 12:00:00');
        \App\Models\Shift::query()
            ->where('branch_id', $this->tenantBranch->id)
            ->where('status', 'active')
            ->update(['shift_date' => $date]);

        AttendanceRecord::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'attendance_date' => $date,
            'worked_minutes' => 720,
            'regular_minutes' => 600,
            'overtime_minutes' => 120,
            'status' => 'present',
            'source' => 'manual',
            'created_by' => $this->companyAdmin->id,
        ]);
        EmployeePayrollAdjustment::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'type' => 'bonus',
            'effective_date' => $date,
            'amount' => 100,
            'status' => 'pending',
            'created_by' => $this->companyAdmin->id,
        ]);
        $this->assertSame(1, AttendanceRecord::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)
            ->whereDate('attendance_date', $date)
            ->count());
        EmployeePayrollAdjustment::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'type' => 'deduction',
            'effective_date' => $date,
            'amount' => 50,
            'status' => 'pending',
            'created_by' => $this->companyAdmin->id,
        ]);

        $paymentService = app(EmployeePaymentService::class);
        $advancePayment = $paymentService->pay([
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'account_id' => $this->salaryAccount->id,
            'money_source_id' => $this->cashSource->id,
            'kind' => 'advance',
            'payment_date' => $date,
            'amount' => 200,
            'payment_method' => 'cash',
            'notes' => 'Early wage advance',
        ], $this->companyAdmin);

        $run = app(PayrollService::class)->generate(
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
            'daily',
            $date,
            $date,
            (int) $this->companyAdmin->id
        );
        $item = $run->items->firstOrFail();

        $this->assertSame(1000.0, (float) $item->base_pay);
        $this->assertSame(300.0, (float) $item->overtime_pay);
        $this->assertSame(100.0, (float) $item->bonus_amount);
        $this->assertSame(50.0, (float) $item->deduction_amount);
        $this->assertSame(200.0, (float) $item->advance_recovery_amount);
        $this->assertSame(1150.0, (float) $item->net_pay);

        $run = app(PayrollService::class)->finalize($run, (int) $this->companyAdmin->id);
        $this->assertSame('recovered', $advancePayment->advance->fresh()->status);
        $this->assertSame(1150.0, EmployeeLedgerEntry::balanceForEmployee($this->employee->id));

        $paymentService->pay([
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'payroll_item_id' => $item->id,
            'account_id' => $this->salaryAccount->id,
            'money_source_id' => $this->cashSource->id,
            'kind' => 'payroll',
            'payment_date' => $date,
            'amount' => 1150,
            'payment_method' => 'cash',
        ], $this->companyAdmin);

        $this->assertSame(0.0, EmployeeLedgerEntry::balanceForEmployee($this->employee->id));
        $this->assertSame('paid', $item->fresh()->status);
        $this->assertSame($date, $advancePayment->fresh()->payment_date->format('Y-m-d'));

        Carbon::setTestNow();
        $this->assertSame('paid', $run->fresh()->status);
        $this->assertSame(2, Transaction::query()
            ->where('reference_type', 'employee_payment')
            ->where('is_manual', false)
            ->count());
        $this->assertSame(8650.0, $this->cashSource->fresh()->getCurrentBalance($this->tenantBranch->id));
    }

    public function test_zero_overtime_rate_keeps_overtime_hours_but_adds_no_pay(): void
    {
        $this->profile->update(['overtime_rate' => 0]);
        $date = '2026-07-14';
        AttendanceRecord::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'attendance_date' => $date,
            'worked_minutes' => 720,
            'regular_minutes' => 600,
            'overtime_minutes' => 120,
            'status' => 'present',
            'source' => 'manual',
        ]);

        $run = app(PayrollService::class)->generate(
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
            'daily',
            $date,
            $date,
            (int) $this->companyAdmin->id
        );
        $item = $run->items->firstOrFail();

        $this->assertSame(120, $item->overtime_minutes);
        $this->assertSame(0.0, (float) $item->overtime_pay);
        $this->assertSame(1000.0, (float) $item->net_pay);
    }

    public function test_weekly_fortnight_and_monthly_cycles_pay_full_rate_for_full_attendance(): void
    {
        $cases = [
            ['weekly', 7000, '2026-06-01', '2026-06-07'],
            ['fortnight', 14000, '2026-06-08', '2026-06-21'],
            ['monthly', 30000, '2026-07-01', '2026-07-31'],
        ];

        foreach ($cases as [$frequency, $rate, $from, $to]) {
            $this->profile->update([
                'pay_frequency' => $frequency,
                'pay_rate' => $rate,
                'overtime_rate' => 0,
            ]);
            foreach (CarbonPeriod::create($from, $to) as $date) {
                AttendanceRecord::create([
                    'company_id' => $this->tenantCompany->id,
                    'branch_id' => $this->tenantBranch->id,
                    'employee_id' => $this->employee->id,
                    'attendance_date' => $date->toDateString(),
                    'worked_minutes' => 600,
                    'regular_minutes' => 600,
                    'overtime_minutes' => 0,
                    'status' => 'present',
                    'source' => 'manual',
                ]);
            }

            $run = app(PayrollService::class)->generate(
                (int) $this->tenantCompany->id,
                (int) $this->tenantBranch->id,
                $frequency,
                $from,
                $to,
                (int) $this->companyAdmin->id
            );

            $this->assertSame((float) $rate, (float) $run->items->firstOrFail()->base_pay);
        }
    }

    public function test_pro_rata_policy_reduces_daily_pay_for_short_hours(): void
    {
        $this->profile->update(['short_hours_policy' => 'pro_rata']);
        $date = '2026-07-16';
        AttendanceRecord::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'attendance_date' => $date,
            'worked_minutes' => 300,
            'regular_minutes' => 300,
            'overtime_minutes' => 0,
            'status' => 'present',
            'source' => 'manual',
        ]);

        $run = app(PayrollService::class)->generate(
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
            'daily',
            $date,
            $date,
            (int) $this->companyAdmin->id
        );

        $item = $run->items->firstOrFail();
        $this->assertSame(0.5, (float) $item->payable_days);
        $this->assertSame(500.0, (float) $item->base_pay);
    }

    public function test_employee_is_created_without_login_and_can_get_login_later(): void
    {
        Storage::fake('local');

        $this->post(route('hr.employees.store'), [
            'name' => 'Ali Worker',
            'email' => 'ali.worker@example.com',
            'phone' => '03001234567',
            'branch_id' => $this->tenantBranch->id,
            'operational_type' => 'waiter',
            'employment_status' => 'active',
            'pay_frequency' => 'daily',
            'pay_rate' => 1000,
            'standard_hours_per_day' => 10,
            'overtime_rate' => 150,
            'short_hours_policy' => 'full_day',
            'working_days' => [1, 2, 3, 4, 5, 6],
            'cnic_attachment' => UploadedFile::fake()->image('ali-cnic.jpg'),
            'other_attachment' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $profile = EmployeeProfile::query()->whereHas('user', fn ($q) => $q->where('email', 'ali.worker@example.com'))->firstOrFail();
        $this->assertFalse($profile->user->can_login);
        $this->assertSame('waiter', $profile->user->type);
        Storage::disk('local')->assertExists($profile->cnic_attachment_path);
        Storage::disk('local')->assertExists($profile->other_attachment_path);
        $this->get(route('hr.employees.documents.download', [$profile, 'cnic']))->assertOk();

        $this->post(route('users.store'), [
            'employee_profile_id' => $profile->id,
            'name' => 'Ali Worker',
            'email' => 'ali.worker@example.com',
            'can_login' => true,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'type' => 'waiter',
            'status' => 'active',
            'role' => 'Cashier',
            'branches' => [$this->tenantBranch->id],
            'primary_branch_id' => $this->tenantBranch->id,
            'company_id' => $this->tenantCompany->id,
        ])->assertRedirect(route('users.index'));

        $this->assertTrue($profile->user->fresh()->can_login);
        $this->assertSame(1, User::query()->where('email', 'ali.worker@example.com')->count());
    }

    public function test_employee_create_posts_opening_balance_to_ledger(): void
    {
        $this->post(route('hr.employees.store'), [
            'name' => 'Sara Staff',
            'email' => 'sara.staff@example.com',
            'branch_id' => $this->tenantBranch->id,
            'operational_type' => 'staff',
            'employment_status' => 'active',
            'hire_date' => '2026-07-01',
            'pay_frequency' => 'monthly',
            'pay_rate' => 50000,
            'standard_hours_per_day' => 8,
            'overtime_rate' => 0,
            'short_hours_policy' => 'full_day',
            'working_days' => [1, 2, 3, 4, 5, 6],
            'opening_balance' => 3500,
        ])->assertRedirect();

        $profile = EmployeeProfile::query()
            ->whereHas('user', fn ($q) => $q->where('email', 'sara.staff@example.com'))
            ->firstOrFail();

        $this->assertSame(3500.0, EmployeeLedgerEntry::balanceForEmployee((int) $profile->user_id));

        $entry = EmployeeLedgerEntry::query()
            ->where('employee_id', $profile->user_id)
            ->where('type', 'opening_balance')
            ->firstOrFail();

        $this->assertSame('credit', $entry->direction);
        $this->assertSame(3500.0, (float) $entry->amount);
        $this->assertSame('2026-07-01', $entry->entry_date->format('Y-m-d'));
        $this->assertSame('Opening balance', $entry->description);
    }

    public function test_employee_create_posts_negative_opening_balance_as_advance(): void
    {
        $this->post(route('hr.employees.store'), [
            'name' => 'Advance Worker',
            'email' => 'advance.worker@example.com',
            'branch_id' => $this->tenantBranch->id,
            'operational_type' => 'staff',
            'employment_status' => 'active',
            'pay_frequency' => 'daily',
            'pay_rate' => 1000,
            'standard_hours_per_day' => 8,
            'overtime_rate' => 0,
            'short_hours_policy' => 'full_day',
            'working_days' => [1, 2, 3, 4, 5],
            'opening_balance' => -1200,
        ])->assertRedirect();

        $profile = EmployeeProfile::query()
            ->whereHas('user', fn ($q) => $q->where('email', 'advance.worker@example.com'))
            ->firstOrFail();

        $this->assertSame(-1200.0, EmployeeLedgerEntry::balanceForEmployee((int) $profile->user_id));

        $entry = EmployeeLedgerEntry::query()
            ->where('employee_id', $profile->user_id)
            ->where('type', 'opening_balance')
            ->firstOrFail();

        $this->assertSame('debit', $entry->direction);
        $this->assertSame(1200.0, (float) $entry->amount);
    }

    public function test_employee_create_reclaims_orphaned_profile_user_id_slot(): void
    {
        Storage::fake('local');

        $ghost = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'email' => 'ghost.worker@example.com',
            'type' => 'staff',
            'can_login' => false,
        ]);
        $ghostId = (int) $ghost->id;
        EmployeeProfile::create([
            'company_id' => $this->tenantCompany->id,
            'user_id' => $ghostId,
            'employee_number' => 'EMP-ORPHAN',
            'employment_status' => 'active',
            'pay_frequency' => 'daily',
            'pay_rate' => 500,
            'standard_hours_per_day' => 8,
            'overtime_rate' => 0,
            'short_hours_policy' => 'full_day',
            'working_days' => [1, 2, 3, 4, 5],
        ]);

        // Leave the profile behind while removing the user row (reproduces AI reuse collision).
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \Illuminate\Support\Facades\DB::table('users')->where('id', $ghostId)->delete();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $this->post(route('hr.employees.store'), [
            'name' => 'Reclaimed Worker',
            'email' => 'reclaimed.worker@example.com',
            'branch_id' => $this->tenantBranch->id,
            'operational_type' => 'staff',
            'employment_status' => 'active',
            'pay_frequency' => 'daily',
            'pay_rate' => 1000,
            'standard_hours_per_day' => 8,
            'overtime_rate' => 0,
            'short_hours_policy' => 'full_day',
            'working_days' => [1, 2, 3, 4, 5, 6],
        ])->assertRedirect();

        $user = User::query()->where('email', 'reclaimed.worker@example.com')->firstOrFail();
        $this->assertSame(1, EmployeeProfile::withTrashed()->where('user_id', $user->id)->count());
        $this->assertSame(1000.0, (float) EmployeeProfile::query()->where('user_id', $user->id)->value('pay_rate'));
    }

    public function test_attendance_form_calculates_regular_and_overtime_minutes(): void
    {
        $this->post(route('hr.attendance.store'), [
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'attendance_date' => '2026-07-15',
            'worked_hours' => 12,
            'break_minutes' => 0,
            'status' => 'present',
        ])->assertRedirect();

        $record = AttendanceRecord::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(720, $record->worked_minutes);
        $this->assertSame(600, $record->regular_minutes);
        $this->assertSame(120, $record->overtime_minutes);
    }

    public function test_live_attendance_board_handles_check_in_break_and_check_out(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 17, 9, 0, 0, 'Asia/Karachi'));

        $this->post(route('hr.attendance.action', $this->employee), [
            'branch_id' => $this->tenantBranch->id,
            'action' => 'check_in',
        ])->assertRedirect(route('hr.attendance.index', ['branch_id' => $this->tenantBranch->id]));

        $record = AttendanceRecord::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame('present', $record->status);
        $this->assertSame('09:00', $record->clock_in->format('H:i'));

        Carbon::setTestNow(Carbon::create(2026, 7, 17, 12, 0, 0, 'Asia/Karachi'));
        $this->post(route('hr.attendance.action', $this->employee), [
            'branch_id' => $this->tenantBranch->id,
            'action' => 'start_break',
        ])->assertRedirect();
        $this->assertNotNull($record->fresh()->break_started_at);

        Carbon::setTestNow(Carbon::create(2026, 7, 17, 12, 30, 0, 'Asia/Karachi'));
        $this->post(route('hr.attendance.action', $this->employee), [
            'branch_id' => $this->tenantBranch->id,
            'action' => 'end_break',
        ])->assertRedirect();
        $this->assertSame(30, $record->fresh()->break_minutes);
        $this->assertNull($record->fresh()->break_started_at);

        Carbon::setTestNow(Carbon::create(2026, 7, 17, 19, 30, 0, 'Asia/Karachi'));
        $this->post(route('hr.attendance.action', $this->employee), [
            'branch_id' => $this->tenantBranch->id,
            'action' => 'check_out',
        ])->assertRedirect();

        $record->refresh();
        $this->assertSame('19:30', $record->clock_out->format('H:i'));
        $this->assertSame(600, $record->worked_minutes);
        $this->assertSame(600, $record->regular_minutes);
        $this->assertSame(0, $record->overtime_minutes);

        Carbon::setTestNow();
    }

    public function test_live_attendance_board_can_mark_employee_absent(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 18, 9, 0, 0, 'Asia/Karachi'));

        $this->post(route('hr.attendance.action', $this->employee), [
            'branch_id' => $this->tenantBranch->id,
            'action' => 'absent',
        ])->assertRedirect();

        $record = AttendanceRecord::query()
            ->where('employee_id', $this->employee->id)
            ->whereDate('attendance_date', '2026-07-18')
            ->firstOrFail();
        $this->assertSame('absent', $record->status);
        $this->assertSame(0, $record->worked_minutes);

        Carbon::setTestNow();
    }

    public function test_deleting_direct_employee_payment_reverses_cash_and_ledger(): void
    {
        $service = app(EmployeePaymentService::class);
        $payment = $service->pay([
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'account_id' => $this->salaryAccount->id,
            'money_source_id' => $this->cashSource->id,
            'kind' => 'wage',
            'payment_date' => '2026-07-16',
            'amount' => 500,
            'payment_method' => 'cash',
        ], $this->companyAdmin);

        $this->assertSame(9500.0, $this->cashSource->fresh()->getCurrentBalance($this->tenantBranch->id));
        $this->assertSame(0.0, EmployeeLedgerEntry::balanceForEmployee($this->employee->id));

        $service->deletePayment($payment);

        $this->assertSame(10000.0, $this->cashSource->fresh()->getCurrentBalance($this->tenantBranch->id));
        $this->assertSoftDeleted('employee_payments', ['id' => $payment->id]);
        $this->assertSoftDeleted('transactions', ['id' => $payment->transaction_id]);
        $this->assertSame(0, EmployeeLedgerEntry::withoutGlobalScopes()
            ->where('employee_payment_id', $payment->id)
            ->count());
    }

    public function test_strict_direct_pay_rate_tracks_under_and_over_payment_on_balance(): void
    {
        $this->tenantCompany->update([
            'settings' => array_merge($this->tenantCompany->settings ?? [], [
                'strict_direct_pay_rate' => true,
            ]),
        ]);
        $this->companyAdmin->unsetRelation('company');
        $service = app(EmployeePaymentService::class);
        $actor = $this->companyAdmin->fresh();

        $underpay = $service->pay([
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'money_source_id' => $this->cashSource->id,
            'kind' => 'wage',
            'payment_date' => '2026-07-16',
            'amount' => 800,
            'payment_method' => 'cash',
        ], $actor);

        $this->assertSame(800.0, (float) $underpay->amount);
        $this->assertSame(200.0, EmployeeLedgerEntry::balanceForEmployee($this->employee->id));
        $this->assertSame(9200.0, $this->cashSource->fresh()->getCurrentBalance($this->tenantBranch->id));

        $overpay = $service->pay([
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'money_source_id' => $this->cashSource->id,
            'kind' => 'wage',
            'payment_date' => '2026-07-17',
            'amount' => 1200,
            'payment_method' => 'cash',
        ], $actor);

        $this->assertSame(1200.0, (float) $overpay->amount);
        $this->assertSame(0.0, EmployeeLedgerEntry::balanceForEmployee($this->employee->id));
        $this->assertSame(8000.0, $this->cashSource->fresh()->getCurrentBalance($this->tenantBranch->id));
    }

    public function test_direct_wage_can_settle_pending_bonus_and_deduction(): void
    {
        $bonus = EmployeePayrollAdjustment::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'type' => 'bonus',
            'effective_date' => '2026-07-16',
            'amount' => 500,
            'status' => 'pending',
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Rush bonus',
        ]);
        $deduction = EmployeePayrollAdjustment::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'type' => 'deduction',
            'effective_date' => '2026-07-16',
            'amount' => 200,
            'status' => 'pending',
            'created_by' => $this->companyAdmin->id,
            'notes' => 'Uniform',
        ]);

        $payment = app(EmployeePaymentService::class)->pay([
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'money_source_id' => $this->cashSource->id,
            'kind' => 'wage',
            'payment_date' => '2026-07-16',
            'amount' => 1300,
            'adjustment_ids' => [$bonus->id, $deduction->id],
            'payment_method' => 'cash',
        ], $this->companyAdmin);

        $this->assertSame(1300.0, (float) $payment->amount);
        $this->assertSame('paid', $bonus->fresh()->status);
        $this->assertSame('paid', $deduction->fresh()->status);
        $this->assertSame(0.0, $bonus->fresh()->remainingAmount());
        $this->assertSame(8700.0, $this->cashSource->fresh()->getCurrentBalance($this->tenantBranch->id));
        $this->assertSame(0.0, EmployeeLedgerEntry::balanceForEmployee($this->employee->id));
    }

    public function test_approved_paid_leave_is_included_in_payroll(): void
    {
        $date = '2026-07-17';
        $this->post(route('hr.leaves.store'), [
            'branch_id' => $this->tenantBranch->id,
            'employee_id' => $this->employee->id,
            'leave_type' => 'paid',
            'start_date' => $date,
            'end_date' => $date,
            'reason' => 'Medical leave',
        ])->assertRedirect(route('hr.leaves.index'));

        $leave = EmployeeLeaveRequest::query()->firstOrFail();
        $this->post(route('hr.leaves.approve', $leave))->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $this->employee->id,
            'leave_request_id' => $leave->id,
            'status' => 'paid_leave',
            'worked_minutes' => 0,
        ]);

        $run = app(PayrollService::class)->generate(
            (int) $this->tenantCompany->id,
            (int) $this->tenantBranch->id,
            'daily',
            $date,
            $date,
            (int) $this->companyAdmin->id
        );

        $this->assertSame(1.0, (float) $run->items->firstOrFail()->payable_days);
        $this->assertSame(1000.0, (float) $run->items->firstOrFail()->base_pay);
    }

    public function test_hr_and_employee_payment_pages_render(): void
    {
        $this->get(route('hr.employees.index'))->assertOk();
        $this->get(route('hr.employees.show', $this->profile))->assertOk();
        $this->get(route('hr.attendance.index'))->assertOk();
        $this->get(route('hr.attendance.create'))->assertOk();
        $this->get(route('hr.leaves.index'))->assertOk();
        $this->get(route('hr.leaves.create'))->assertOk();
        $this->get(route('hr.payroll.index'))->assertOk();
        $this->get(route('hr.payroll.create'))->assertOk();
        $this->get(route('hr.adjustments.index'))->assertOk();
        $this->get(route('hr.adjustments.create'))->assertOk();
        $this->get(route('employee-payments.index'))->assertOk();
        $this->get(route('employee-payments.create'))->assertOk();
    }
}
