<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Supplier::normalizeName($this->input('name')),
            'phone' => Supplier::normalizePhone($this->input('phone')),
            'email' => Supplier::normalizeEmail($this->input('email')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers', 'name')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
                function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
                    if (Supplier::nameIsTaken($companyId, is_string($value) ? $value : null)) {
                        $fail('This company name is already assigned to another supplier.');
                    }
                },
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('suppliers', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
                function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
                    if (Supplier::emailIsTaken($companyId, is_string($value) ? $value : null)) {
                        $fail('This email is already assigned to another supplier.');
                    }
                },
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('suppliers', 'phone')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
                function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
                    if (Supplier::phoneIsTaken($companyId, is_string($value) ? $value : null)) {
                        $fail('This phone number is already assigned to another supplier.');
                    }
                },
            ],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
            'balance' => ['nullable', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This company name is already assigned to another supplier.',
            'code.unique' => 'This supplier code is already in use.',
            'email.unique' => 'This email is already assigned to another supplier.',
            'phone.unique' => 'This phone number is already assigned to another supplier.',
        ];
    }
}
