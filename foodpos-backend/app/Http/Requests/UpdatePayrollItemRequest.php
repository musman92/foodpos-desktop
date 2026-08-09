<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAppPermission('payroll.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'base_pay' => ['required', 'numeric', 'min:0'],
            'overtime_pay' => ['required', 'numeric', 'min:0'],
            'bonus_amount' => ['required', 'numeric', 'min:0'],
            'deduction_amount' => ['required', 'numeric', 'min:0'],
            'advance_recovery_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
