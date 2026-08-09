<?php

namespace App\Http\Requests;

use App\Models\EmployeeLeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAppPermission('leaves.store') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'employee_id' => [
                'required',
                Rule::exists('employee_profiles', 'user_id')
                    ->where('company_id', $companyId)
                    ->where('employment_status', 'active'),
            ],
            'leave_type' => ['required', Rule::in(EmployeeLeaveRequest::TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
