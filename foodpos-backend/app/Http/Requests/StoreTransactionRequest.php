<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'exists:accounts,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:in,out'],
            'payment_method' => ['required', 'in:cash,transfer,card,online'],
            'money_source_id' => ['nullable', 'exists:money_sources,id'],
            'reference_type' => ['nullable', 'in:sale,purchase,refund,expense,customer_payment,transfer,reconciliation,adjustment,employee_payment'],
            'date' => ['required', 'date'],
            'ref_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
