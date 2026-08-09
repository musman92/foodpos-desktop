<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    use HasTenantAndBranch;

    protected $fillable = [
        'company_id',
        'branch_id',
        'payroll_run_id',
        'employee_id',
        'employee_number',
        'pay_frequency',
        'pay_rate',
        'standard_hours_per_day',
        'overtime_rate',
        'short_hours_policy',
        'scheduled_days',
        'payable_days',
        'worked_minutes',
        'regular_minutes',
        'overtime_minutes',
        'base_pay',
        'overtime_pay',
        'bonus_amount',
        'deduction_amount',
        'advance_recovery_amount',
        'gross_pay',
        'net_pay',
        'paid_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'pay_rate' => 'decimal:2',
        'standard_hours_per_day' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'payable_days' => 'decimal:2',
        'base_pay' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'advance_recovery_amount' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function adjustments()
    {
        return $this->hasMany(EmployeePayrollAdjustment::class);
    }

    public function payments()
    {
        return $this->hasMany(EmployeePayment::class);
    }

    public function advanceRecoveries()
    {
        return $this->hasMany(PayrollAdvanceRecovery::class);
    }

    public function remainingAmount(): float
    {
        return max(0, round((float) $this->net_pay - (float) $this->paid_amount, 2));
    }
}
