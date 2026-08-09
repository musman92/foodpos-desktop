<?php

namespace App\Models;

use App\Traits\HasTenantAndBranch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRecord extends Model
{
    use HasTenantAndBranch, SoftDeletes;

    public const STATUSES = ['present', 'absent', 'paid_leave', 'unpaid_leave', 'holiday'];

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'leave_request_id',
        'attendance_date',
        'clock_in',
        'clock_out',
        'break_minutes',
        'break_started_at',
        'worked_minutes',
        'regular_minutes',
        'overtime_minutes',
        'status',
        'source',
        'created_by',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'break_started_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function leaveRequest()
    {
        return $this->belongsTo(EmployeeLeaveRequest::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function calculateMinutes(
        ?string $clockIn,
        ?string $clockOut,
        int $breakMinutes,
        int $standardMinutes,
        string $status
    ): array {
        if (! in_array($status, ['present'], true) || ! $clockIn || ! $clockOut) {
            return ['worked' => 0, 'regular' => 0, 'overtime' => 0];
        }

        $start = Carbon::parse($clockIn);
        $end = Carbon::parse($clockOut);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $worked = max(0, (int) $start->diffInMinutes($end) - max(0, $breakMinutes));
        $regular = min($worked, max(0, $standardMinutes));

        return [
            'worked' => $worked,
            'regular' => $regular,
            'overtime' => max(0, $worked - $standardMinutes),
        ];
    }
}
