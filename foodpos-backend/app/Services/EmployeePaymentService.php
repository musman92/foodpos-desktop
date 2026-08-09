<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeLedgerEntry;
use App\Models\EmployeePayment;
use App\Models\EmployeePayrollAdjustment;
use App\Models\MoneySource;
use App\Models\PayrollItem;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CurrentShift;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeePaymentService
{
    public const STRICT_DIRECT_PAY_RATE_SETTING = 'strict_direct_pay_rate';

    public function __construct(
        protected PayrollService $payrollService
    ) {}

    public static function strictDirectPayRateEnabled(?Company $company = null): bool
    {
        if (! $company) {
            return false;
        }

        return (bool) (($company->settings ?? [])[self::STRICT_DIRECT_PAY_RATE_SETTING] ?? false);
    }

    /**
     * Business date for cash movements: open shift date when available, else branch local today.
     */
    public static function businessDateForBranch(int $branchId, ?User $actor = null): string
    {
        $shift = CurrentShift::resolve($branchId, $actor);
        if ($shift?->shift_date) {
            return $shift->shift_date->format('Y-m-d');
        }

        return tz()->businessDate($branchId);
    }

    public function pay(array $data, User $actor): EmployeePayment
    {
        $kind = (string) $data['kind'];
        if (! in_array($kind, EmployeePayment::KINDS, true)) {
            throw new \InvalidArgumentException('Invalid employee payment type.');
        }

        return DB::transaction(function () use ($data, $actor, $kind) {
            $employee = User::query()
                ->with('employeeProfile')
                ->where('company_id', $actor->company_id)
                ->whereKey($data['employee_id'])
                ->whereHas('employeeProfile')
                ->firstOrFail();
            $branchId = (int) $data['branch_id'];
            $cashOut = round((float) $data['amount'], 2);
            $shift = CurrentShift::resolve($branchId, $actor);
            // Keep cash / reports aligned with the open shift day (or branch business date).
            $paymentDate = self::businessDateForBranch($branchId, $actor);

            $employeeBelongsToBranch = (int) $employee->branch_id === $branchId
                || $employee->branches()->whereKey($branchId)->exists();
            if (! $employeeBelongsToBranch) {
                throw new \InvalidArgumentException('The employee is not assigned to the selected branch.');
            }

            $adjustments = $this->resolveSelectedAdjustments($data, $actor, $employee, $kind);
            $bonusTotal = round((float) $adjustments->where('type', 'bonus')->sum(fn ($item) => $item->remainingAmount()), 2);
            $deductionTotal = round((float) $adjustments->where('type', 'deduction')->sum(fn ($item) => $item->remainingAmount()), 2);
            $strictRate = $kind === 'wage' && self::strictDirectPayRateEnabled($actor->company);
            $payRate = round((float) ($employee->employeeProfile?->pay_rate ?? 0), 2);

            if ($strictRate && $payRate <= 0) {
                throw new \InvalidArgumentException('Employee pay rate must be greater than zero when track pay-rate balance is enabled.');
            }

            // Amount is always the cash leaving the till.
            if ($cashOut <= 0) {
                throw new \InvalidArgumentException('Payment amount must be greater than zero.');
            }

            $wageCredit = 0.0;
            if ($kind === 'wage') {
                // Strict: credit the configured rate so under/over pay updates employee balance.
                // Open: impute wage credit so this payment stays balance-neutral.
                $wageCredit = $strictRate
                    ? $payRate
                    : round($cashOut - $bonusTotal + $deductionTotal, 2);
            }

            $account = Account::ensureSystemAccount(
                (int) $actor->company_id,
                'Salary',
                'expense'
            );
            $moneySource = MoneySource::forPayments()
                ->where('company_id', $actor->company_id)
                ->whereKey($data['money_source_id'])
                ->where('active', true)
                ->where(function ($query) use ($branchId) {
                    $query->whereDoesntHave('branches')
                        ->orWhereHas('branches', fn ($branch) => $branch->whereKey($branchId));
                })
                ->firstOrFail();

            $payrollItem = null;
            if ($kind === 'payroll') {
                $payrollItem = PayrollItem::query()
                    ->with('payrollRun')
                    ->lockForUpdate()
                    ->findOrFail($data['payroll_item_id']);
                if ((int) $payrollItem->employee_id !== (int) $employee->id) {
                    throw new \InvalidArgumentException('This payslip belongs to a different employee.');
                }
                if (! in_array($payrollItem->status, ['finalized', 'partially_paid'], true)) {
                    throw new \InvalidArgumentException('Only finalized payroll can be paid.');
                }
                if ($cashOut > $payrollItem->remainingAmount() + 0.009) {
                    throw new \InvalidArgumentException('Payment exceeds the remaining payslip amount.');
                }
            }

            $payment = EmployeePayment::create([
                'company_id' => $actor->company_id,
                'branch_id' => $branchId,
                'employee_id' => $employee->id,
                'payroll_item_id' => $payrollItem?->id,
                'account_id' => $account->id,
                'money_source_id' => $moneySource->id,
                'created_by' => $actor->id,
                'payment_number' => EmployeePayment::generateNumber((int) $actor->company_id, $branchId),
                'kind' => $kind,
                'payment_date' => $paymentDate,
                'amount' => $cashOut,
                'payment_method' => $data['payment_method'],
                'notes' => $data['notes'] ?? null,
            ]);

            $transaction = Transaction::create([
                'company_id' => $actor->company_id,
                'branch_id' => $branchId,
                'account_id' => $account->id,
                'amount' => $cashOut,
                'type' => 'out',
                'payment_method' => $data['payment_method'],
                'money_source_id' => $moneySource->id,
                'reference_type' => 'employee_payment',
                'date' => $paymentDate,
                'ref_id' => $payment->id,
                'created_by' => $actor->id,
                'shift_id' => $shift?->id,
                'is_manual' => false,
                'notes' => $this->transactionNotes($payment, $employee),
            ]);
            $payment->update(['transaction_id' => $transaction->id]);

            if ($kind === 'advance') {
                $advance = EmployeeAdvance::create([
                    'company_id' => $actor->company_id,
                    'branch_id' => $branchId,
                    'employee_id' => $employee->id,
                    'employee_payment_id' => $payment->id,
                    'advance_date' => $paymentDate,
                    'amount' => $cashOut,
                    'status' => 'outstanding',
                    'created_by' => $actor->id,
                    'notes' => $data['notes'] ?? null,
                ]);
                $this->ledgerEntry($payment, 'advance', 'debit', $cashOut, $actor, $advance->id);
            } elseif ($kind === 'payroll') {
                $this->ledgerEntry($payment, 'payment', 'debit', $cashOut, $actor);
                $payrollItem->paid_amount = round((float) $payrollItem->paid_amount + $cashOut, 2);
                $payrollItem->status = $payrollItem->remainingAmount() <= 0.009 ? 'paid' : 'partially_paid';
                $payrollItem->save();
                $this->syncPayrollRunStatus($payrollItem);
            } else {
                if ($kind === 'wage' && $wageCredit > 0.009) {
                    $this->ledgerEntry($payment, 'direct_wage', 'credit', $wageCredit, $actor);
                } elseif ($kind === 'wage' && $wageCredit < -0.009) {
                    $this->ledgerEntry($payment, 'direct_wage', 'debit', abs($wageCredit), $actor);
                }

                foreach ($adjustments as $adjustment) {
                    $settle = $adjustment->remainingAmount();
                    if ($settle <= 0) {
                        continue;
                    }
                    $direction = $adjustment->type === 'bonus' ? 'credit' : 'debit';
                    $this->ledgerEntry(
                        $payment,
                        $adjustment->type,
                        $direction,
                        $settle,
                        $actor,
                        null,
                        $adjustment->id
                    );
                    $adjustment->applyPayment($settle, (int) $payment->id);
                }

                if ($kind === 'bonus' && $adjustments->isEmpty()) {
                    $this->ledgerEntry($payment, 'bonus', 'credit', $cashOut, $actor);
                }

                $this->ledgerEntry($payment, 'payment', 'debit', $cashOut, $actor);
            }

            return $payment->fresh([
                'employee.employeeProfile',
                'account',
                'moneySource',
                'payrollItem.payrollRun',
                'advance',
            ]);
        });
    }

    public function recordAdjustment(array $data, User $actor): EmployeePayrollAdjustment
    {
        if (! in_array($data['type'], EmployeePayrollAdjustment::TYPES, true)) {
            throw new \InvalidArgumentException('Invalid payroll adjustment type.');
        }

        $employee = User::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($data['employee_id'])
            ->whereHas('employeeProfile')
            ->firstOrFail();
        $branchId = (int) ($data['branch_id'] ?? $employee->branch_id);
        if (
            (int) $employee->branch_id !== $branchId
            && ! $employee->branches()->whereKey($branchId)->exists()
        ) {
            throw new \InvalidArgumentException('The employee is not assigned to the selected branch.');
        }

        return EmployeePayrollAdjustment::create([
            'company_id' => $actor->company_id,
            'branch_id' => $branchId,
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'effective_date' => $data['effective_date'],
            'amount' => round((float) $data['amount'], 2),
            'paid_amount' => 0,
            'status' => 'pending',
            'created_by' => $actor->id,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function deletePayment(EmployeePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment = EmployeePayment::withoutGlobalScopes()
                ->with(['advance', 'payrollItem.payrollRun', 'transaction'])
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->advance && (float) $payment->advance->recovered_amount > 0.009) {
                throw new \InvalidArgumentException('This advance has already been recovered through payroll and cannot be deleted.');
            }
            if ($payment->advance && $payment->advance->recoveries()->exists()) {
                throw new \InvalidArgumentException('This advance is included in a payroll draft. Remove or update that draft before deleting the advance.');
            }

            if ($payment->payrollItem) {
                $item = PayrollItem::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->payroll_item_id);
                $item->paid_amount = max(0, round((float) $item->paid_amount - (float) $payment->amount, 2));
                $item->status = $item->paid_amount > 0 ? 'partially_paid' : 'finalized';
                $item->save();
                $this->syncPayrollRunStatus($item);
            }

            EmployeePayrollAdjustment::withoutGlobalScopes()
                ->where('employee_payment_id', $payment->id)
                ->lockForUpdate()
                ->get()
                ->each(function (EmployeePayrollAdjustment $adjustment) {
                    $adjustment->update([
                        'paid_amount' => 0,
                        'employee_payment_id' => null,
                        'status' => 'pending',
                    ]);
                });

            EmployeeLedgerEntry::withoutGlobalScopes()
                ->where('employee_payment_id', $payment->id)
                ->delete();
            $payment->advance?->delete();
            $payment->transaction?->delete();
            $payment->delete();
        });
    }

    protected function resolveSelectedAdjustments(array $data, User $actor, User $employee, string $kind): Collection
    {
        if (! in_array($kind, ['wage', 'bonus'], true)) {
            return collect();
        }

        $ids = collect($data['adjustment_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $adjustments = EmployeePayrollAdjustment::withoutGlobalScopes()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $employee->id)
            ->whereIn('id', $ids)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->whereNull('payroll_item_id')
            ->lockForUpdate()
            ->get();

        if ($adjustments->count() !== $ids->count()) {
            throw new \InvalidArgumentException('One or more selected bonuses/deductions are no longer available.');
        }

        if ($kind === 'bonus' && $adjustments->contains(fn ($item) => $item->type !== 'bonus')) {
            throw new \InvalidArgumentException('Only pending bonuses can be settled with a bonus payment.');
        }

        return $adjustments;
    }

    protected function ledgerEntry(
        EmployeePayment $payment,
        string $type,
        string $direction,
        float $amount,
        User $actor,
        ?int $advanceId = null,
        ?int $adjustmentId = null
    ): void {
        EmployeeLedgerEntry::create([
            'company_id' => $payment->company_id,
            'branch_id' => $payment->branch_id,
            'employee_id' => $payment->employee_id,
            'payroll_item_id' => $payment->payroll_item_id,
            'employee_payment_id' => $payment->id,
            'employee_advance_id' => $advanceId,
            'payroll_adjustment_id' => $adjustmentId,
            'entry_date' => $payment->payment_date,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'description' => ucfirst(str_replace('_', ' ', $type))." — {$payment->payment_number}",
            'created_by' => $actor->id,
        ]);
    }

    protected function syncPayrollRunStatus(PayrollItem $item): void
    {
        $run = $item->payrollRun()->withoutGlobalScopes()->firstOrFail();
        $this->payrollService->refreshRunTotals($run);
        $run->refresh();
        $run->status = (float) $run->paid_total >= (float) $run->net_total - 0.009
            ? 'paid'
            : ((float) $run->paid_total > 0 ? 'partially_paid' : 'finalized');
        $run->save();
    }

    protected function transactionNotes(EmployeePayment $payment, User $employee): string
    {
        return sprintf(
            'Employee %s — %s — %s',
            ucfirst(str_replace('_', ' ', $payment->kind)),
            $employee->name,
            $payment->payment_number
        );
    }
}
