<?php

namespace App\Http\Requests;

use App\Models\EmployeeProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAppPermission('payroll.store') ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where('company_id', $this->user()?->company_id),
            ],
            'pay_frequency' => ['required', Rule::in(EmployeeProfile::PAY_FREQUENCIES)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
