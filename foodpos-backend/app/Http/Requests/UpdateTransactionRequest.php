<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');
        $user = $this->user();

        return $user
            && $transaction
            && $transaction->canBeModifiedBy($user)
            && $user->hasAppPermission('transactions.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->route('transaction')?->company_id ?? $this->user()?->company_id;

        return [
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where('company_id', $companyId),
            ],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('company_id', $companyId),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:in,out'],
            'payment_method' => ['required', 'in:cash,transfer,card,online'],
            'money_source_id' => [
                'nullable',
                Rule::exists('money_sources', 'id')->where('company_id', $companyId),
            ],
            'reference_type' => ['nullable', 'in:sale,purchase,refund,expense,customer_payment,transfer,reconciliation,adjustment,employee_payment'],
            'date' => ['required', 'date'],
            'ref_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
