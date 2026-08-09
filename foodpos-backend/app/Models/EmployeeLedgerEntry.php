<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeLedgerEntry extends Model
{
    use HasTenantAndBranch;

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'payroll_item_id',
        'employee_payment_id',
        'employee_advance_id',
        'payroll_adjustment_id',
        'entry_date',
        'type',
        'direction',
        'amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function payrollItem()
    {
        return $this->belongsTo(PayrollItem::class);
    }

    public function payment()
    {
        return $this->belongsTo(EmployeePayment::class, 'employee_payment_id');
    }

    public function advance()
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public static function balanceForEmployee(int $employeeId): float
    {
        $credit = (float) static::query()
            ->where('employee_id', $employeeId)
            ->where('direction', 'credit')
            ->sum('amount');
        $debit = (float) static::query()
            ->where('employee_id', $employeeId)
            ->where('direction', 'debit')
            ->sum('amount');

        return round($credit - $debit, 2);
    }
}
