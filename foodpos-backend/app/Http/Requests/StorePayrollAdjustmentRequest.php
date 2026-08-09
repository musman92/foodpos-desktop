<?php

namespace App\Http\Requests;

use App\Models\EmployeePayrollAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAppPermission('payroll.store') ?? false;
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
            'type' => ['required', Rule::in(EmployeePayrollAdjustment::TYPES)],
            'effective_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
