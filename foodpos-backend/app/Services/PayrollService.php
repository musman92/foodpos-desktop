<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeLedgerEntry;
use App\Models\EmployeePayrollAdjustment;
use App\Models\EmployeeProfile;
use App\Models\PayrollAdvanceRecovery;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function generate(
        int $companyId,
        int $branchId,
        string $frequency,
        string $periodStart,
        string $periodEnd,
        int $userId,
        ?string $notes = null
    ): PayrollRun {
        if (! in_array($frequency, EmployeeProfile::PAY_FREQUENCIES, true)) {
            throw new \InvalidArgumentException('Invalid pay frequency.');
        }

        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->startOfDay();
        if ($end->lessThan($start)) {
            throw new \InvalidArgumentException('Payroll end date must be on or after its start date.');
        }

        return DB::transaction(function () use (
            $companyId,
            $branchId,
            $frequency,
            $start,
            $end,
            $userId,
            $notes
        ) {
            $exists = PayrollRun::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('pay_frequency', $frequency)
                ->whereDate('period_start', '<=', $end)
                ->whereDate('period_end', '>=', $start)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                throw new \InvalidArgumentException('An overlapping payroll run already exists for this branch and pay cycle.');
            }

            $run = PayrollRun::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payroll_number' => PayrollRun::generateNumber($companyId),
                'pay_frequency' => $frequency,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => 'draft',
                'generated_by' => $userId,
                'notes' => $notes,
            ]);

            $profiles = EmployeeProfile::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('employment_status', 'active')
                ->where('pay_frequency', $frequency)
                ->whereHas('user', function ($query) use ($branchId) {
                    $query->where('status', 'active')
                        ->where(function ($branchQuery) use ($branchId) {
                            $branchQuery->where('branch_id', $branchId)
                                ->orWhereHas('branches', fn ($q) => $q->where('branches.id', $branchId));
                        });
                })
                ->with('user')
                ->get();

            foreach ($profiles as $profile) {
                $this->createPayrollItem($run, $profile, $start, $end, $branchId);
            }

            $this->refreshRunTotals($run);

            return $run->fresh(['items.employee.employeeProfile']);
        });
    }

    public function updateDraftItem(PayrollItem $item, array $amounts): PayrollItem
    {
        return DB::transaction(function () use ($item, $amounts) {
            $item->loadMissing('payrollRun');
            if (! $item->payrollRun->isDraft()) {
                throw new \InvalidArgumentException('Only draft payroll items can be changed.');
            }

            $base = round((float) ($amounts['base_pay'] ?? $item->base_pay), 2);
            $overtime = round((float) ($amounts['overtime_pay'] ?? $item->overtime_pay), 2);
            $bonus = round((float) ($amounts['bonus_amount'] ?? $item->bonus_amount), 2);
            $deduction = round((float) ($amounts['deduction_amount'] ?? $item->deduction_amount), 2);
            $requestedRecovery = round((float) ($amounts['advance_recovery_amount'] ?? $item->advance_recovery_amount), 2);

            foreach ([$base, $overtime, $bonus, $deduction, $requestedRecovery] as $amount) {
                if ($amount < 0) {
                    throw new \InvalidArgumentException('Payroll amounts cannot be negative.');
                }
            }

            $gross = round($base + $overtime + $bonus, 2);
            $outstandingAdvance = $this->outstandingAdvanceTotal(
                (int) $item->employee_id,
                $item->payrollRun->period_end
            );
            $recovery = min(
                $requestedRecovery,
                max(0, $gross - $deduction),
                $outstandingAdvance
            );
            $this->allocateAdvanceRecovery($item, $recovery);

            $item->update([
                'base_pay' => $base,
                'overtime_pay' => $overtime,
                'bonus_amount' => $bonus,
                'deduction_amount' => $deduction,
                'advance_recovery_amount' => $recovery,
                'gross_pay' => $gross,
                'net_pay' => max(0, round($gross - $deduction - $recovery, 2)),
                'notes' => $amounts['notes'] ?? $item->notes,
            ]);

            $this->refreshRunTotals($item->payrollRun);

            return $item->fresh(['employee.employeeProfile', 'advanceRecoveries.advance']);
        });
    }

    public function finalize(PayrollRun $run, int $userId): PayrollRun
    {
        return DB::transaction(function () use ($run, $userId) {
            $run = PayrollRun::withoutGlobalScopes()->lockForUpdate()->findOrFail($run->id);
            if (! $run->isDraft()) {
                throw new \InvalidArgumentException('Only a draft payroll run can be finalized.');
            }

            $items = PayrollItem::withoutGlobalScopes()
                ->where('payroll_run_id', $run->id)
                ->with(['adjustments', 'advanceRecoveries.advance'])
                ->lockForUpdate()
                ->get();
            if ($items->isEmpty()) {
                throw new \InvalidArgumentException('This payroll run has no employees.');
            }

            foreach ($items as $item) {
                $this->createFinalizationLedgerEntries($item, $userId, $run->period_end->toDateString());

                foreach ($item->advanceRecoveries as $recovery) {
                    $advance = EmployeeAdvance::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->findOrFail($recovery->employee_advance_id);
                    $advance->recovered_amount = min(
                        (float) $advance->amount,
                        round((float) $advance->recovered_amount + (float) $recovery->amount, 2)
                    );
                    $advance->status = $advance->outstandingAmount() <= 0.009 ? 'recovered' : 'partially_recovered';
                    $advance->save();
                }

                EmployeePayrollAdjustment::withoutGlobalScopes()
                    ->where('payroll_item_id', $item->id)
                    ->whereIn('status', ['pending', 'partially_paid'])
                    ->update([
                        'status' => 'applied',
                        'paid_amount' => DB::raw('amount'),
                    ]);

                $item->update(['status' => (float) $item->net_pay > 0 ? 'finalized' : 'paid']);
            }

            $run->update([
                'status' => (float) $run->net_total > 0 ? 'finalized' : 'paid',
                'finalized_by' => $userId,
                'finalized_at' => now(),
            ]);

            return $run->fresh(['items.employee.employeeProfile']);
        });
    }

    public function deleteDraft(PayrollRun $run): void
    {
        DB::transaction(function () use ($run) {
            if (! $run->isDraft()) {
                throw new \InvalidArgumentException('Only draft payroll runs can be deleted.');
            }

            EmployeePayrollAdjustment::withoutGlobalScopes()
                ->whereIn('payroll_item_id', $run->items()->pluck('id'))
                ->update(['payroll_item_id' => null]);
            PayrollItem::withoutGlobalScopes()
                ->where('payroll_run_id', $run->id)
                ->delete();
            $run->delete();
        });
    }

    public function refreshRunTotals(PayrollRun $run): void
    {
        $items = PayrollItem::withoutGlobalScopes()
            ->where('payroll_run_id', $run->id)
            ->get();

        $run->update([
            'employee_count' => $items->count(),
            'gross_total' => round((float) $items->sum('gross_pay'), 2),
            'deduction_total' => round((float) $items->sum('deduction_amount'), 2),
            'advance_recovery_total' => round((float) $items->sum('advance_recovery_amount'), 2),
            'net_total' => round((float) $items->sum('net_pay'), 2),
            'paid_total' => round((float) $items->sum('paid_amount'), 2),
        ]);
    }

    protected function createPayrollItem(
        PayrollRun $run,
        EmployeeProfile $profile,
        Carbon $start,
        Carbon $end,
        int $branchId
    ): PayrollItem {
        $attendance = AttendanceRecord::withoutGlobalScopes()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $profile->user_id)
            ->whereDate('attendance_date', '>=', $start->toDateString())
            ->whereDate('attendance_date', '<=', $end->toDateString())
            ->get();

        $scheduledDays = collect(CarbonPeriod::create($start, $end))
            ->filter(fn ($date) => in_array($date->dayOfWeekIso, $profile->workingDays(), true))
            ->count();
        $standardMinutes = $profile->standardMinutesPerDay();
        $payableDays = 0.0;

        foreach ($attendance as $record) {
            if (in_array($record->status, ['paid_leave', 'holiday'], true)) {
                $payableDays += 1;
            } elseif ($record->status === 'present' && $record->worked_minutes > 0) {
                $payableDays += $profile->short_hours_policy === 'pro_rata'
                    ? min(1, (float) $record->regular_minutes / $standardMinutes)
                    : 1;
            }
        }

        $basePay = $this->calculateBasePay($profile, $payableDays, $scheduledDays);
        $overtimeMinutes = (int) $attendance->sum('overtime_minutes');
        $overtimePay = round(($overtimeMinutes / 60) * (float) $profile->overtime_rate, 2);

        $adjustments = EmployeePayrollAdjustment::withoutGlobalScopes()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $profile->user_id)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->whereNull('payroll_item_id')
            ->whereDate('effective_date', '>=', $start->toDateString())
            ->whereDate('effective_date', '<=', $end->toDateString())
            ->lockForUpdate()
            ->get();
        $bonus = round((float) $adjustments->where('type', 'bonus')->sum(fn ($item) => $item->remainingAmount()), 2);
        $deduction = round((float) $adjustments->where('type', 'deduction')->sum(fn ($item) => $item->remainingAmount()), 2);
        $gross = round($basePay + $overtimePay + $bonus, 2);
        $availableForAdvance = max(0, $gross - $deduction);
        $advanceOutstanding = $this->outstandingAdvanceTotal($profile->user_id, $end);
        $advanceRecovery = min($availableForAdvance, $advanceOutstanding);

        $item = PayrollItem::withoutGlobalScopes()->create([
            'company_id' => $run->company_id,
            'branch_id' => $branchId,
            'payroll_run_id' => $run->id,
            'employee_id' => $profile->user_id,
            'employee_number' => $profile->employee_number,
            'pay_frequency' => $profile->pay_frequency,
            'pay_rate' => $profile->pay_rate,
            'standard_hours_per_day' => $profile->standard_hours_per_day,
            'overtime_rate' => $profile->overtime_rate,
            'short_hours_policy' => $profile->short_hours_policy,
            'scheduled_days' => $scheduledDays,
            'payable_days' => round($payableDays, 2),
            'worked_minutes' => (int) $attendance->sum('worked_minutes'),
            'regular_minutes' => (int) $attendance->sum('regular_minutes'),
            'overtime_minutes' => $overtimeMinutes,
            'base_pay' => $basePay,
            'overtime_pay' => $overtimePay,
            'bonus_amount' => $bonus,
            'deduction_amount' => $deduction,
            'advance_recovery_amount' => $advanceRecovery,
            'gross_pay' => $gross,
            'net_pay' => max(0, round($gross - $deduction - $advanceRecovery, 2)),
            'status' => 'draft',
        ]);

        if ($adjustments->isNotEmpty()) {
            EmployeePayrollAdjustment::withoutGlobalScopes()
                ->whereIn('id', $adjustments->pluck('id'))
                ->update(['payroll_item_id' => $item->id]);
        }
        $this->allocateAdvanceRecovery($item, $advanceRecovery);

        return $item;
    }

    protected function calculateBasePay(
        EmployeeProfile $profile,
        float $payableDays,
        int $scheduledDays
    ): float {
        if ($profile->pay_frequency === 'daily') {
            return round((float) $profile->pay_rate * $payableDays, 2);
        }

        if ($scheduledDays <= 0) {
            return 0;
        }

        return round((float) $profile->pay_rate * ($payableDays / $scheduledDays), 2);
    }

    protected function outstandingAdvanceTotal(int $employeeId, Carbon $asOf): float
    {
        return round((float) EmployeeAdvance::withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->whereDate('advance_date', '<=', $asOf)
            ->whereIn('status', ['outstanding', 'partially_recovered'])
            ->get()
            ->sum(fn (EmployeeAdvance $advance) => $advance->outstandingAmount()), 2);
    }

    protected function allocateAdvanceRecovery(PayrollItem $item, float $amount): void
    {
        PayrollAdvanceRecovery::query()->where('payroll_item_id', $item->id)->delete();
        $remaining = round($amount, 2);
        if ($remaining <= 0) {
            return;
        }

        $advances = EmployeeAdvance::withoutGlobalScopes()
            ->where('employee_id', $item->employee_id)
            ->whereIn('status', ['outstanding', 'partially_recovered'])
            ->orderBy('advance_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($advances as $advance) {
            if ($remaining <= 0.009) {
                break;
            }
            $take = min($remaining, $advance->outstandingAmount());
            if ($take <= 0) {
                continue;
            }

            PayrollAdvanceRecovery::create([
                'payroll_item_id' => $item->id,
                'employee_advance_id' => $advance->id,
                'amount' => $take,
            ]);
            $remaining = round($remaining - $take, 2);
        }
    }

    protected function createFinalizationLedgerEntries(
        PayrollItem $item,
        int $userId,
        string $entryDate
    ): void {
        $entries = [
            ['base_pay', 'credit', (float) $item->base_pay, 'Base wage / salary'],
            ['overtime_pay', 'credit', (float) $item->overtime_pay, 'Overtime pay'],
            ['bonus', 'credit', (float) $item->bonus_amount, 'Payroll bonus'],
            ['deduction', 'debit', (float) $item->deduction_amount, 'Payroll deduction'],
        ];

        foreach ($entries as [$type, $direction, $amount, $description]) {
            if ($amount <= 0) {
                continue;
            }

            EmployeeLedgerEntry::withoutGlobalScopes()->create([
                'company_id' => $item->company_id,
                'branch_id' => $item->branch_id,
                'employee_id' => $item->employee_id,
                'payroll_item_id' => $item->id,
                'entry_date' => $entryDate,
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'description' => $description,
                'created_by' => $userId,
            ]);
        }
    }
}
