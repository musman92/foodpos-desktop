<?php

namespace App\Http\Requests;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAppPermission(
            $this->route('employeeProfile') ? 'employees.update' : 'employees.store'
        ) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'working_days' => array_values(array_filter(
                (array) $this->input('working_days', []),
                fn ($day) => $day !== null && $day !== ''
            )),
        ]);
    }

    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $profile = $this->route('employeeProfile');
        $ignoreUserId = $profile?->user_id;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($ignoreUserId),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'operational_type' => ['required', Rule::in(User::STAFF_LIKE_ACCOUNT_TYPES)],
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employee_profiles', 'employee_number')
                    ->where('company_id', $companyId)
                    ->ignore($profile?->id),
            ],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'employment_status' => ['required', Rule::in(EmployeeProfile::EMPLOYMENT_STATUSES)],
            'pay_frequency' => ['required', Rule::in(EmployeeProfile::PAY_FREQUENCIES)],
            'pay_rate' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'standard_hours_per_day' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'overtime_rate' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'short_hours_policy' => ['required', Rule::in(EmployeeProfile::SHORT_HOURS_POLICIES)],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7', 'distinct'],
            'national_id' => ['nullable', 'string', 'max:100'],
            'cnic_attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'other_attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (! $profile) {
            $rules['opening_balance'] = ['nullable', 'numeric', 'min:-999999999.99', 'max:999999999.99'];
        }

        return $rules;
    }
}
