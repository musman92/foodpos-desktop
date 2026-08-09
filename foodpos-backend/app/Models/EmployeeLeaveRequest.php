<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLeaveRequest extends Model
{
    use HasTenantAndBranch, SoftDeletes;

    public const TYPES = ['paid', 'unpaid'];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'days',
        'status',
        'reason',
        'review_notes',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class, 'leave_request_id');
    }
}
