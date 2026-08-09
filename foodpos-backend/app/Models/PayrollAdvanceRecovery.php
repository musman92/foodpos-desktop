<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollAdvanceRecovery extends Model
{
    protected $fillable = [
        'payroll_item_id',
        'employee_advance_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payrollItem()
    {
        return $this->belongsTo(PayrollItem::class);
    }

    public function advance()
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }
}
