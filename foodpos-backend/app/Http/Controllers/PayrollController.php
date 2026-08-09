<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneratePayrollRequest;
use App\Http\Requests\UpdatePayrollItemRequest;
use App\Models\EmployeeProfile;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use App\Support\HrAccess;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('payroll.index');
        $user = Auth::user();
        $perPage = ListingPerPage::fromRequest($request);
        $allowedBranches = HrAccess::branchesFor($user);

        $runs = PayrollRun::withoutGlobalScope('branch')
            ->with(['branch', 'generator'])
            ->where('company_id', $user->company_id)
            ->whereIn('branch_id', $allowedBranches->pluck('id'))
            ->when($request->filled('branch_id'), function ($q) use ($request, $user) {
                HrAccess::assertBranch($user, (int) $request->branch_id);
                $q->where('branch_id', $request->branch_id);
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('pay_frequency'), fn ($q) => $q->where('pay_frequency', $request->pay_frequency))
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('hr.payroll.index', [
            'runs' => $runs,
            'branches' => $allowedBranches,
            'perPage' => $perPage,
        ]);
    }

    public function create()
    {
        $this->authorizePermission('payroll.store');

        return view('hr.payroll.create', [
            'branches' => HrAccess::branchesFor(Auth::user()),
            'frequencies' => EmployeeProfile::PAY_FREQUENCIES,
        ]);
    }

    public function store(GeneratePayrollRequest $request)
    {
        HrAccess::assertBranch(Auth::user(), (int) $request->branch_id);

        try {
            $run = $this->payrollService->generate(
                (int) Auth::user()->company_id,
                (int) $request->branch_id,
                $request->pay_frequency,
                $request->period_start,
                $request->period_end,
                (int) Auth::id(),
                $request->notes
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['payroll' => $e->getMessage()]);
        }

        return redirect()
            ->route('hr.payroll.show', $run)
            ->with('success', 'Draft payroll generated. Review it before finalizing.');
    }

    public function show(PayrollRun $payrollRun)
    {
        $this->authorizeRun($payrollRun, 'payroll.index');
        $payrollRun->load([
            'branch',
            'generator',
            'finalizer',
            'items' => fn ($q) => $q->with([
                'employee.employeeProfile',
                'adjustments',
                'advanceRecoveries.advance',
                'payments',
            ])->orderBy('id'),
        ]);

        return view('hr.payroll.show', compact('payrollRun'));
    }

    public function updateItem(
        UpdatePayrollItemRequest $request,
        PayrollRun $payrollRun,
        PayrollItem $payrollItem
    ) {
        $this->authorizeRun($payrollRun, 'payroll.update');
        abort_unless((int) $payrollItem->payroll_run_id === (int) $payrollRun->id, 404);

        try {
            $this->payrollService->updateDraftItem($payrollItem, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        return back()->with('success', 'Payslip updated.');
    }

    public function finalize(PayrollRun $payrollRun)
    {
        $this->authorizeRun($payrollRun, 'payroll.update');

        try {
            $this->payrollService->finalize($payrollRun, (int) Auth::id());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        return back()->with('success', 'Payroll finalized. It is ready for payment.');
    }

    public function destroy(PayrollRun $payrollRun)
    {
        $this->authorizeRun($payrollRun, 'payroll.destroy');

        try {
            $this->payrollService->deleteDraft($payrollRun);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        return redirect()->route('hr.payroll.index')->with('success', 'Draft payroll deleted.');
    }

    public function payslip(PayrollItem $payrollItem)
    {
        $payrollItem->load([
            'payrollRun.branch',
            'employee.employeeProfile',
            'adjustments',
            'advanceRecoveries.advance',
            'payments.moneySource',
        ]);
        $this->authorizeRun($payrollItem->payrollRun, 'payroll.index');

        return view('hr.payroll.payslip', compact('payrollItem'));
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(Auth::user()->hasAppPermission($permission), 403);
    }

    protected function authorizeRun(PayrollRun $run, string $permission): void
    {
        $this->authorizePermission($permission);
        abort_unless((int) $run->company_id === (int) Auth::user()->company_id, 403);
        HrAccess::assertBranch(Auth::user(), (int) $run->branch_id);
    }
}
