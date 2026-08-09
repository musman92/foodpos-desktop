<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollAdjustmentRequest;
use App\Models\EmployeePayrollAdjustment;
use App\Services\EmployeePaymentService;
use App\Support\HrAccess;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayrollAdjustmentController extends Controller
{
    public function __construct(
        protected EmployeePaymentService $paymentService
    ) {}

    public function index(Request $request)
    {
        abort_unless(Auth::user()->hasAppPermission('payroll.index'), 403);
        $perPage = ListingPerPage::fromRequest($request);
        $employees = HrAccess::employeeUsers(Auth::user())
            ->with('employeeProfile')
            ->orderBy('name')
            ->get();

        $adjustments = EmployeePayrollAdjustment::query()
            ->with(['employee.employeeProfile', 'payrollItem.payrollRun', 'creator'])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('hr.adjustments.index', compact('adjustments', 'employees', 'perPage'));
    }

    public function create(Request $request)
    {
        abort_unless(Auth::user()->hasAppPermission('payroll.store'), 403);

        return view('hr.adjustments.form', [
            'employees' => HrAccess::employeeUsers(Auth::user())
                ->with('employeeProfile')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'branches' => HrAccess::branchesFor(Auth::user()),
            'selectedEmployeeId' => $request->employee_id,
            'selectedType' => $request->type,
        ]);
    }

    public function store(StorePayrollAdjustmentRequest $request)
    {
        HrAccess::assertBranch(Auth::user(), (int) $request->branch_id);
        $this->paymentService->recordAdjustment($request->validated(), Auth::user());

        return redirect()
            ->route('hr.adjustments.index')
            ->with('success', ucfirst($request->type).' recorded for the next applicable payroll.');
    }

    public function destroy(EmployeePayrollAdjustment $payrollAdjustment)
    {
        abort_unless(Auth::user()->hasAppPermission('payroll.destroy'), 403);
        abort_unless((int) $payrollAdjustment->company_id === (int) Auth::user()->company_id, 403);
        abort_if($payrollAdjustment->status !== 'pending', 422, 'Applied payroll adjustments cannot be deleted.');
        abort_if($payrollAdjustment->payroll_item_id, 422, 'This adjustment is included in a payroll draft. Delete that draft first.');
        $payrollAdjustment->delete();

        return back()->with('success', 'Payroll adjustment deleted.');
    }
}
