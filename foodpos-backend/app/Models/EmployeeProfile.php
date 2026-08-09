<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeProfile extends Model
{
    use SoftDeletes, TenantScope;

    public const PAY_FREQUENCIES = ['daily', 'weekly', 'fortnight', 'monthly'];

    public const EMPLOYMENT_STATUSES = ['active', 'suspended', 'resigned', 'terminated'];

    public const SHORT_HOURS_POLICIES = ['full_day', 'pro_rata'];

    public const DEFAULT_WORKING_DAYS = [1, 2, 3, 4, 5, 6];

    protected $fillable = [
        'company_id',
        'user_id',
        'employee_number',
        'designation',
        'department',
        'hire_date',
        'end_date',
        'employment_status',
        'pay_frequency',
        'pay_rate',
        'standard_hours_per_day',
        'overtime_rate',
        'short_hours_policy',
        'working_days',
        'national_id',
        'cnic_attachment_path',
        'cnic_attachment_name',
        'other_attachment_path',
        'other_attachment_name',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'bank_name',
        'bank_account',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'end_date' => 'date',
        'pay_rate' => 'decimal:2',
        'standard_hours_per_day' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'working_days' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('employment_status', 'active');
    }

    public function workingDays(): array
    {
        $days = $this->working_days ?: self::DEFAULT_WORKING_DAYS;

        return array_values(array_unique(array_map('intval', $days)));
    }

    public function standardMinutesPerDay(): int
    {
        return max(1, (int) round((float) $this->standard_hours_per_day * 60));
    }
}
