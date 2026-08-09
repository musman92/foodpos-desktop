<?php

namespace App\Http\Requests;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAppPermission(
            $this->route('attendanceRecord') ? 'attendance.update' : 'attendance.store'
        ) ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $recordId = $this->route('attendanceRecord')?->id;

        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'employee_id' => [
                'required',
                Rule::exists('employee_profiles', 'user_id')
                    ->where('company_id', $companyId)
                    ->where('employment_status', 'active'),
            ],
            'attendance_date' => [
                'required',
                'date',
                Rule::unique('attendance_records', 'attendance_date')
                    ->where('company_id', $companyId)
                    ->where('employee_id', $this->input('employee_id'))
                    ->whereNull('deleted_at')
                    ->ignore($recordId),
            ],
            'clock_in' => ['nullable', 'date'],
            'clock_out' => ['nullable', 'date'],
            'worked_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
