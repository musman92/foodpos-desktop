<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeePaymentRequest;
use App\Models\Account;
use App\Models\EmployeeLedgerEntry;
use App\Models\EmployeePayment;
use App\Models\EmployeePayrollAdjustment;
use App\Models\MoneySource;
use App\Models\PayrollItem;
use App\Services\EmployeePaymentService;
use App\Support\HrAccess;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeePaymentController extends Controller
{
    public function __construct(
        protected EmployeePaymentService $paymentService
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('employee-payments.index');
        $user = Auth::user();
        $perPage = ListingPerPage::fromRequest($request);
        $allowedBranchIds = HrAccess::branchesFor($user)->pluck('id');

        $payments = EmployeePayment::withoutGlobalScope('branch')
            ->with(['employee.employeeProfile', 'branch', 'moneySource', 'payrollItem.payrollRun'])
            ->where('company_id', $user->company_id)
            ->whereIn('branch_id', $allowedBranchIds)
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->kind))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->to))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('employee-payments.index', [
            'payments' => $payments,
            'employees' => HrAccess::employeeUsers($user)->with('employeeProfile')->orderBy('name')->get(),
            'perPage' => $perPage,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizePermission('employee-payments.store');
        $user = Auth::user();
        $payrollItem = $request->filled('payroll_item_id')
            ? PayrollItem::query()
                ->with(['employee.employeeProfile', 'payrollRun'])
                ->findOrFail($request->payroll_item_id)
            : null;
        if ($payrollItem) {
            abort_unless((int) $payrollItem->company_id === (int) $user->company_id, 403);
        }

        $salaryAccount = Account::ensureSystemAccount((int) $user->company_id, 'Salary', 'expense');
        $employees = HrAccess::employeeUsers($user)
            ->with('employeeProfile')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $employeeIds = $employees->pluck('id');
        $employeeBalances = $employeeIds
            ->mapWithKeys(fn ($id) => [(int) $id => EmployeeLedgerEntry::balanceForEmployee((int) $id)])
            ->all();
        $pendingAdjustments = EmployeePayrollAdjustment::query()
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->whereNull('payroll_item_id')
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get()
            ->map(fn (EmployeePayrollAdjustment $item) => [
                'id' => (int) $item->id,
                'employee_id' => (int) $item->employee_id,
                'type' => $item->type,
                'amount' => (float) $item->amount,
                'paid_amount' => (float) $item->paid_amount,
                'remaining' => $item->remainingAmount(),
                'effective_date' => optional($item->effective_date)->format('Y-m-d'),
                'effective_date_label' => format_date($item->effective_date),
                'notes' => $item->notes ?: ($item->type === 'bonus' ? 'Bonus' : 'Deduction'),
            ])
            ->groupBy('employee_id');

        $payablePayslips = PayrollItem::query()
            ->with('payrollRun')
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', ['finalized', 'partially_paid'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PayrollItem $item) => $item->remainingAmount() > 0.009)
            ->map(fn (PayrollItem $item) => [
                'id' => (int) $item->id,
                'employee_id' => (int) $item->employee_id,
                'branch_id' => (int) $item->branch_id,
                'remaining' => $item->remainingAmount(),
                'net_pay' => (float) $item->net_pay,
                'payroll_number' => $item->payrollRun?->payroll_number ?: ('Payslip #'.$item->id),
                'period_label' => format_date($item->payrollRun?->period_start).' – '.format_date($item->payrollRun?->period_end),
                'label' => ($item->payrollRun?->payroll_number ?: ('Payslip #'.$item->id))
                    .' · '.format_date($item->payrollRun?->period_start).' – '.format_date($item->payrollRun?->period_end)
                    .' · remaining '.format_currency($item->remainingAmount()),
            ])
            ->groupBy('employee_id');

        $branches = HrAccess::branchesFor($user);
        $branchId = (int) ($payrollItem?->branch_id ?? current_branch_id() ?? $user->branch_id);
        $businessDatesByBranch = $branches->mapWithKeys(
            fn ($branch) => [(int) $branch->id => EmployeePaymentService::businessDateForBranch((int) $branch->id, $user)]
        )->all();
        if ($branchId && ! isset($businessDatesByBranch[$branchId])) {
            $businessDatesByBranch[$branchId] = EmployeePaymentService::businessDateForBranch($branchId, $user);
        }
        $businessDate = $businessDatesByBranch[$branchId]
            ?? EmployeePaymentService::businessDateForBranch($branchId ?: (int) $user->branch_id, $user);

        return view('employee-payments.create', [
            'employees' => $employees,
            'branches' => $branches,
            'moneySources' => MoneySource::forPayments()
                ->where('company_id', $user->company_id)
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'salaryAccount' => $salaryAccount,
            'payrollItem' => $payrollItem,
            'pendingAdjustments' => $pendingAdjustments,
            'payablePayslips' => $payablePayslips,
            'employeeBalances' => $employeeBalances,
            'selectedEmployeeId' => $payrollItem?->employee_id ?? $request->employee_id,
            'selectedKind' => $payrollItem ? 'payroll' : ($request->kind ?? 'wage'),
            'businessDate' => $businessDate,
            'businessDatesByBranch' => $businessDatesByBranch,
        ]);
    }

    public function store(StoreEmployeePaymentRequest $request)
    {
        HrAccess::assertBranch(Auth::user(), (int) $request->branch_id);

        try {
            $payment = $this->paymentService->pay($request->validated(), Auth::user());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()
            ->route('employee-payments.show', $payment)
            ->with('success', 'Employee payment recorded successfully.');
    }

    public function show(EmployeePayment $employeePayment)
    {
        $this->authorizePayment($employeePayment, 'employee-payments.index');
        $employeePayment->load([
            'employee.employeeProfile',
            'branch',
            'account',
            'moneySource',
            'creator',
            'payrollItem.payrollRun',
            'advance',
            'transaction',
        ]);

        return view('employee-payments.show', compact('employeePayment'));
    }

    public function destroy(EmployeePayment $employeePayment)
    {
        $this->authorizePayment($employeePayment, 'employee-payments.destroy');

        try {
            $this->paymentService->deletePayment($employeePayment);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()
            ->route('employee-payments.index')
            ->with('success', 'Employee payment reversed and deleted.');
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(Auth::user()->hasAppPermission($permission), 403);
    }

    protected function authorizePayment(EmployeePayment $payment, string $permission): void
    {
        $this->authorizePermission($permission);
        abort_unless((int) $payment->company_id === (int) Auth::user()->company_id, 403);
        HrAccess::assertBranch(Auth::user(), (int) $payment->branch_id);
    }
}
