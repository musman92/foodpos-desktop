<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Support\HrAccess;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('attendance.index');
        $user = Auth::user();
        $perPage = ListingPerPage::fromRequest($request);
        $branchId = $request->filled('branch_id')
            ? (int) $request->branch_id
            : current_branch_id();
        if ($branchId) {
            HrAccess::assertBranch($user, $branchId);
        }

        $query = AttendanceRecord::withoutGlobalScope('branch')
            ->with(['employee.employeeProfile', 'branch'])
            ->where('company_id', $user->company_id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('attendance_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('attendance_date', '<=', $request->to))
            ->orderByDesc('attendance_date')
            ->orderByDesc('id');

        $records = $query->paginate($perPage)->withQueryString();
        $branches = HrAccess::branchesFor($user);
        $employees = HrAccess::employeeUsers($user)
            ->with('employeeProfile')
            ->orderBy('name')
            ->get();
        $boardDate = local_today($branchId);
        $boardEmployees = HrAccess::employeeUsers($user)
            ->with('employeeProfile')
            ->where('status', 'active')
            ->whereHas('employeeProfile', fn ($q) => $q->where('employment_status', 'active'))
            ->when($branchId, function ($employeeQuery) use ($branchId) {
                $employeeQuery->where(function ($branchQuery) use ($branchId) {
                    $branchQuery->where('branch_id', $branchId)
                        ->orWhereHas('branches', fn ($q) => $q->whereKey($branchId));
                });
            })
            ->orderBy('name')
            ->get();
        $todayRecords = AttendanceRecord::withoutGlobalScopes(['tenant', 'branch'])
            ->where('company_id', $user->company_id)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('attendance_date', $boardDate)
            ->whereIn('employee_id', $boardEmployees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        return view('hr.attendance.index', compact(
            'records',
            'branches',
            'employees',
            'boardEmployees',
            'todayRecords',
            'boardDate',
            'branchId',
            'perPage'
        ));
    }

    public function action(Request $request, int $employee)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'action' => ['required', Rule::in([
                'check_in',
                'start_break',
                'end_break',
                'check_out',
                'absent',
            ])],
        ]);
        $branchId = (int) $data['branch_id'];
        $user = Auth::user();
        HrAccess::assertBranch($user, $branchId);
        $profile = $this->activeEmployeeProfile($employee, $branchId);
        $date = local_today($branchId);
        $now = local_now($branchId);

        try {
            DB::transaction(function () use ($data, $branchId, $employee, $profile, $date, $now, $user) {
                $record = AttendanceRecord::withoutGlobalScopes(['tenant', 'branch'])
                    ->withTrashed()
                    ->where('company_id', $user->company_id)
                    ->where('employee_id', $employee)
                    ->whereDate('attendance_date', $date)
                    ->lockForUpdate()
                    ->first();

                $permission = $record && ! $record->trashed()
                    ? 'attendance.update'
                    : 'attendance.store';
                $this->authorizePermission($permission);

                if (! $record) {
                    $record = new AttendanceRecord();
                } elseif ($record->trashed()) {
                    $record->restore();
                    $record->fill([
                        'leave_request_id' => null,
                        'clock_in' => null,
                        'clock_out' => null,
                        'break_minutes' => 0,
                        'break_started_at' => null,
                        'worked_minutes' => 0,
                        'regular_minutes' => 0,
                        'overtime_minutes' => 0,
                        'status' => 'present',
                        'notes' => null,
                    ])->save();
                }

                match ($data['action']) {
                    'check_in' => $this->checkIn($record, $branchId, $employee, $date, $now),
                    'start_break' => $this->startBreak($record, $now),
                    'end_break' => $this->endBreak($record, $now),
                    'check_out' => $this->checkOut($record, $profile, $now),
                    'absent' => $this->markAbsent($record, $branchId, $employee, $date),
                };
            });
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['attendance' => $exception->getMessage()]);
        }

        return redirect()
            ->route('hr.attendance.index', ['branch_id' => $branchId])
            ->with('success', $this->actionMessage($data['action']));
    }

    public function create(Request $request)
    {
        $this->authorizePermission('attendance.store');

        return $this->formView(null, $request);
    }

    public function store(StoreAttendanceRequest $request)
    {
        $data = $this->attendanceData($request);
        AttendanceRecord::withoutGlobalScopes()->create($data);

        return redirect()
            ->route('hr.attendance.index', ['branch_id' => $data['branch_id']])
            ->with('success', 'Attendance recorded successfully.');
    }

    public function edit(Request $request, AttendanceRecord $attendanceRecord)
    {
        $this->authorizeRecord($attendanceRecord, 'attendance.update');

        return $this->formView($attendanceRecord, $request);
    }

    public function update(StoreAttendanceRequest $request, AttendanceRecord $attendanceRecord)
    {
        $this->authorizeRecord($attendanceRecord, 'attendance.update');
        $attendanceRecord->update($this->attendanceData($request));

        return redirect()
            ->route('hr.attendance.index', ['branch_id' => $attendanceRecord->branch_id])
            ->with('success', 'Attendance updated successfully.');
    }

    public function destroy(AttendanceRecord $attendanceRecord)
    {
        $this->authorizeRecord($attendanceRecord, 'attendance.destroy');
        $attendanceRecord->delete();

        return back()->with('success', 'Attendance record deleted.');
    }

    protected function attendanceData(StoreAttendanceRequest $request): array
    {
        $validated = $request->validated();
        $branchId = (int) $validated['branch_id'];
        HrAccess::assertBranch(Auth::user(), $branchId);
        $profile = EmployeeProfile::withoutGlobalScopes()
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', $validated['employee_id'])
            ->whereHas('user', function ($employeeQuery) use ($branchId) {
                $employeeQuery->where('branch_id', $branchId)
                    ->orWhereHas('branches', fn ($branch) => $branch->whereKey($branchId));
            })
            ->firstOrFail();

        if ($request->filled('worked_hours') && $validated['status'] === 'present') {
            $worked = (int) round((float) $validated['worked_hours'] * 60);
            $regular = min($worked, $profile->standardMinutesPerDay());
            $minutes = [
                'worked' => $worked,
                'regular' => $regular,
                'overtime' => max(0, $worked - $profile->standardMinutesPerDay()),
            ];
        } else {
            $minutes = AttendanceRecord::calculateMinutes(
                $validated['clock_in'] ?? null,
                $validated['clock_out'] ?? null,
                (int) ($validated['break_minutes'] ?? 0),
                $profile->standardMinutesPerDay(),
                $validated['status']
            );
        }

        unset($validated['worked_hours']);

        return array_merge($validated, [
            'company_id' => Auth::user()->company_id,
            'branch_id' => $branchId,
            'worked_minutes' => $minutes['worked'],
            'regular_minutes' => $minutes['regular'],
            'overtime_minutes' => $minutes['overtime'],
            'break_started_at' => null,
            'source' => 'manual',
            'created_by' => Auth::id(),
            'approved_by' => Auth::id(),
        ]);
    }

    protected function formView(?AttendanceRecord $record, Request $request)
    {
        $user = Auth::user();
        $branches = HrAccess::branchesFor($user);
        $employees = HrAccess::employeeUsers($user)
            ->with('employeeProfile')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $selectedBranchId = $record?->branch_id
            ?? ($request->filled('branch_id') ? (int) $request->branch_id : current_branch_id());

        return view('hr.attendance.form', [
            'attendanceRecord' => $record,
            'branches' => $branches,
            'employees' => $employees,
            'selectedBranchId' => $selectedBranchId,
            'selectedEmployeeId' => $record?->employee_id ?? $request->employee_id,
        ]);
    }

    protected function activeEmployeeProfile(int $employeeId, int $branchId): EmployeeProfile
    {
        return EmployeeProfile::withoutGlobalScopes()
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', $employeeId)
            ->where('employment_status', 'active')
            ->whereHas('user', function ($query) use ($branchId) {
                $query->where('status', 'active')
                    ->where(function ($branchQuery) use ($branchId) {
                        $branchQuery->where('branch_id', $branchId)
                            ->orWhereHas('branches', fn ($branch) => $branch->whereKey($branchId));
                    });
            })
            ->firstOrFail();
    }

    protected function checkIn(
        AttendanceRecord $record,
        int $branchId,
        int $employeeId,
        string $date,
        mixed $now
    ): void {
        if ($record->exists && ($record->clock_in || $record->status !== 'present')) {
            throw new \InvalidArgumentException('Attendance has already been recorded for this employee today.');
        }

        $record->fill([
            'company_id' => Auth::user()->company_id,
            'branch_id' => $branchId,
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'clock_in' => $now,
            'clock_out' => null,
            'break_minutes' => 0,
            'break_started_at' => null,
            'worked_minutes' => 0,
            'regular_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'present',
            'source' => 'live',
            'created_by' => Auth::id(),
            'approved_by' => Auth::id(),
        ])->save();
    }

    protected function startBreak(AttendanceRecord $record, mixed $now): void
    {
        $this->assertOpenShift($record);
        if ($record->break_started_at) {
            throw new \InvalidArgumentException('This employee is already on break.');
        }

        $record->update(['break_started_at' => $now]);
    }

    protected function endBreak(AttendanceRecord $record, mixed $now): void
    {
        $this->assertOpenShift($record);
        if (! $record->break_started_at) {
            throw new \InvalidArgumentException('This employee is not currently on break.');
        }

        $record->update([
            'break_minutes' => $record->break_minutes + $this->elapsedBreakMinutes($record, $now),
            'break_started_at' => null,
        ]);
    }

    protected function checkOut(AttendanceRecord $record, EmployeeProfile $profile, mixed $now): void
    {
        $this->assertOpenShift($record);
        $breakMinutes = (int) $record->break_minutes;
        if ($record->break_started_at) {
            $breakMinutes += $this->elapsedBreakMinutes($record, $now);
        }
        $minutes = AttendanceRecord::calculateMinutes(
            $record->clock_in?->toDateTimeString(),
            $now->toDateTimeString(),
            $breakMinutes,
            $profile->standardMinutesPerDay(),
            'present'
        );

        $record->update([
            'clock_out' => $now,
            'break_minutes' => $breakMinutes,
            'break_started_at' => null,
            'worked_minutes' => $minutes['worked'],
            'regular_minutes' => $minutes['regular'],
            'overtime_minutes' => $minutes['overtime'],
        ]);
    }

    protected function markAbsent(
        AttendanceRecord $record,
        int $branchId,
        int $employeeId,
        string $date
    ): void {
        if ($record->exists && ($record->clock_in || $record->status !== 'present')) {
            throw new \InvalidArgumentException('Attendance has already been recorded for this employee today.');
        }

        $record->fill([
            'company_id' => Auth::user()->company_id,
            'branch_id' => $branchId,
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'clock_in' => null,
            'clock_out' => null,
            'break_minutes' => 0,
            'break_started_at' => null,
            'worked_minutes' => 0,
            'regular_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'absent',
            'source' => 'live',
            'created_by' => Auth::id(),
            'approved_by' => Auth::id(),
        ])->save();
    }

    protected function assertOpenShift(AttendanceRecord $record): void
    {
        if (! $record->exists || $record->status !== 'present' || ! $record->clock_in || $record->clock_out) {
            throw new \InvalidArgumentException('The employee must be checked in and not checked out.');
        }
    }

    protected function elapsedBreakMinutes(AttendanceRecord $record, mixed $now): int
    {
        return max(0, (int) floor($record->break_started_at->diffInSeconds($now) / 60));
    }

    protected function actionMessage(string $action): string
    {
        return match ($action) {
            'check_in' => 'Employee checked in.',
            'start_break' => 'Break started.',
            'end_break' => 'Employee returned from break.',
            'check_out' => 'Employee checked out.',
            'absent' => 'Employee marked absent.',
        };
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(Auth::user()->hasAppPermission($permission), 403);
    }

    protected function authorizeRecord(AttendanceRecord $record, string $permission): void
    {
        $this->authorizePermission($permission);
        abort_unless((int) $record->company_id === (int) Auth::user()->company_id, 403);
        HrAccess::assertBranch(Auth::user(), (int) $record->branch_id);
    }
}
