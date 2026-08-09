<?php

namespace App\Http\Requests;

use App\Models\EmployeePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAppPermission('employee-payments.store') ?? false;
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
            'payroll_item_id' => [
                Rule::requiredIf($this->input('kind') === 'payroll'),
                'nullable',
                Rule::exists('payroll_items', 'id')->where('company_id', $companyId),
            ],
            'money_source_id' => [
                'required',
                Rule::exists('money_sources', 'id')
                    ->where('company_id', $companyId)
                    ->where('active', true),
            ],
            'kind' => ['required', Rule::in(EmployeePayment::KINDS)],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'adjustment_ids' => ['nullable', 'array'],
            'adjustment_ids.*' => [
                'integer',
                Rule::exists('employee_payroll_adjustments', 'id')
                    ->where('company_id', $companyId)
                    ->whereIn('status', ['pending', 'partially_paid']),
            ],
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'card', 'online'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'payroll_item_id.required' => 'Select a finalized payslip to pay, or open Pay from the payroll screen.',
            'payroll_item_id.required_if' => 'Select a finalized payslip to pay, or open Pay from the payroll screen.',
        ];
    }
}
