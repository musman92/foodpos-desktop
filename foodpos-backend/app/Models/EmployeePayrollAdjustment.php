<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeePayrollAdjustment extends Model
{
    use HasTenantAndBranch, SoftDeletes;

    public const TYPES = ['bonus', 'deduction'];

    public const STATUSES = ['pending', 'partially_paid', 'paid', 'applied', 'cancelled'];

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'payroll_item_id',
        'employee_payment_id',
        'type',
        'effective_date',
        'amount',
        'paid_amount',
        'status',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function remainingAmount(): float
    {
        return round(max(0, (float) $this->amount - (float) $this->paid_amount), 2);
    }

    public function applyPayment(float $amount, int $paymentId): void
    {
        $settle = round(min($this->remainingAmount(), max(0, $amount)), 2);
        $this->paid_amount = round((float) $this->paid_amount + $settle, 2);
        $this->employee_payment_id = $paymentId;
        $this->status = $this->remainingAmount() <= 0.009 ? 'paid' : 'partially_paid';
        $this->save();
    }
}
