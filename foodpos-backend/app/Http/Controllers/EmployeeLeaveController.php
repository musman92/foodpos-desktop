<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeLeaveRequest;
use App\Models\AttendanceRecord;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeProfile;
use App\Support\HrAccess;
use App\Support\ListingPerPage;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeLeaveController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('leaves.index');
        $perPage = ListingPerPage::fromRequest($request);

        $leaves = EmployeeLeaveRequest::query()
            ->with(['employee.employeeProfile', 'branch', 'requester', 'reviewer'])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('leave_type'), fn ($q) => $q->where('leave_type', $request->leave_type))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('hr.leaves.index', [
            'leaves' => $leaves,
            'employees' => HrAccess::employeeUsers(Auth::user())->with('employeeProfile')->orderBy('name')->get(),
            'perPage' => $perPage,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizePermission('leaves.store');

        return view('hr.leaves.form', [
            'employees' => HrAccess::employeeUsers(Auth::user())
                ->with('employeeProfile')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'branches' => HrAccess::branchesFor(Auth::user()),
            'selectedEmployeeId' => $request->employee_id,
            'selectedBranchId' => $request->filled('branch_id')
                ? (int) $request->branch_id
                : current_branch_id(),
            'selectedStartDate' => $request->start_date ?? local_today($request->branch_id),
            'selectedEndDate' => $request->end_date ?? $request->start_date ?? local_today($request->branch_id),
        ]);
    }

    public function store(StoreEmployeeLeaveRequest $request)
    {
        $data = $request->validated();
        $branchId = (int) $data['branch_id'];
        HrAccess::assertBranch(Auth::user(), $branchId);
        $profile = $this->employeeProfile((int) $data['employee_id'], $branchId);
        $days = $this->leaveDates($profile, $data['start_date'], $data['end_date']);

        $overlaps = EmployeeLeaveRequest::withoutGlobalScopes()
            ->where('company_id', Auth::user()->company_id)
            ->where('employee_id', $data['employee_id'])
            ->where('status', '!=', 'rejected')
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();
        if ($overlaps) {
            return back()->withInput()->withErrors(['leave' => 'This employee already has an overlapping leave request.']);
        }

        EmployeeLeaveRequest::create(array_merge($data, [
            'company_id' => Auth::user()->company_id,
            'days' => count($days),
            'status' => 'pending',
            'requested_by' => Auth::id(),
        ]));

        return redirect()->route('hr.leaves.index')->with('success', 'Leave request created.');
    }

    public function approve(Request $request, EmployeeLeaveRequest $employeeLeave)
    {
        $this->authorizeLeave($employeeLeave, 'leaves.update');
        $request->validate(['review_notes' => ['nullable', 'string', 'max:2000']]);

        try {
            DB::transaction(function () use ($employeeLeave, $request) {
                $leave = EmployeeLeaveRequest::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($employeeLeave->id);
                if ($leave->status !== 'pending') {
                    throw new \InvalidArgumentException('Only pending leave can be approved.');
                }

                $profile = $this->employeeProfile((int) $leave->employee_id, (int) $leave->branch_id);
                $dates = $this->leaveDates($profile, $leave->start_date, $leave->end_date);
                foreach ($dates as $date) {
                    $existing = AttendanceRecord::withoutGlobalScopes()
                        ->where('company_id', $leave->company_id)
                        ->where('employee_id', $leave->employee_id)
                        ->whereDate('attendance_date', $date)
                        ->first();
                    if ($existing && $existing->status === 'present') {
                        throw new \InvalidArgumentException("Attendance is already marked present on {$date}.");
                    }

                    $attendanceData = [
                        'company_id' => $leave->company_id,
                        'branch_id' => $leave->branch_id,
                        'employee_id' => $leave->employee_id,
                        'leave_request_id' => $leave->id,
                        'attendance_date' => $date,
                        'worked_minutes' => 0,
                        'regular_minutes' => 0,
                        'overtime_minutes' => 0,
                        'break_minutes' => 0,
                        'status' => $leave->leave_type === 'paid' ? 'paid_leave' : 'unpaid_leave',
                        'source' => 'leave',
                        'created_by' => $leave->requested_by,
                        'approved_by' => Auth::id(),
                        'notes' => $leave->reason,
                    ];
                    $existing
                        ? $existing->update($attendanceData)
                        : AttendanceRecord::withoutGlobalScopes()->create($attendanceData);
                }

                $leave->update([
                    'status' => 'approved',
                    'days' => count($dates),
                    'review_notes' => $request->review_notes,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        return back()->with('success', 'Leave approved and attendance updated.');
    }

    public function reject(Request $request, EmployeeLeaveRequest $employeeLeave)
    {
        $this->authorizeLeave($employeeLeave, 'leaves.update');
        $request->validate(['review_notes' => ['nullable', 'string', 'max:2000']]);
        abort_if($employeeLeave->status !== 'pending', 422, 'Only pending leave can be rejected.');
        $employeeLeave->update([
            'status' => 'rejected',
            'review_notes' => $request->review_notes,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Leave request rejected.');
    }

    public function destroy(EmployeeLeaveRequest $employeeLeave)
    {
        $this->authorizeLeave($employeeLeave, 'leaves.destroy');
        abort_if($employeeLeave->status !== 'pending', 422, 'Only pending leave can be deleted.');
        $employeeLeave->delete();

        return back()->with('success', 'Leave request deleted.');
    }

    protected function employeeProfile(int $employeeId, int $branchId): EmployeeProfile
    {
        return EmployeeProfile::withoutGlobalScopes()
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', $employeeId)
            ->whereHas('user', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)
                    ->orWhereHas('branches', fn ($branch) => $branch->whereKey($branchId));
            })
            ->firstOrFail();
    }

    protected function leaveDates(EmployeeProfile $profile, mixed $start, mixed $end): array
    {
        return collect(CarbonPeriod::create(Carbon::parse($start), Carbon::parse($end)))
            ->filter(fn ($date) => in_array($date->dayOfWeekIso, $profile->workingDays(), true))
            ->map(fn ($date) => $date->toDateString())
            ->values()
            ->all();
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(Auth::user()->hasAppPermission($permission), 403);
    }

    protected function authorizeLeave(EmployeeLeaveRequest $leave, string $permission): void
    {
        $this->authorizePermission($permission);
        abort_unless((int) $leave->company_id === (int) Auth::user()->company_id, 403);
        HrAccess::assertBranch(Auth::user(), (int) $leave->branch_id);
    }
}
