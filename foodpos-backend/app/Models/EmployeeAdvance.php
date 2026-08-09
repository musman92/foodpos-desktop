<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAdvance extends Model
{
    use HasTenantAndBranch, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'employee_payment_id',
        'advance_date',
        'amount',
        'recovered_amount',
        'status',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'advance_date' => 'date',
        'amount' => 'decimal:2',
        'recovered_amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function payment()
    {
        return $this->belongsTo(EmployeePayment::class, 'employee_payment_id');
    }

    public function recoveries()
    {
        return $this->hasMany(PayrollAdvanceRecovery::class);
    }

    public function outstandingAmount(): float
    {
        return max(0, round((float) $this->amount - (float) $this->recovered_amount, 2));
    }
}
