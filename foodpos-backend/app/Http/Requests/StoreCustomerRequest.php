<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'phone' => Customer::normalizePhone($this->input('phone')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $companyId = Customer::requireTenantCompanyId();

        return [
            'name' => 'required|string|max:255',
            'code' => Customer::tenantCodeValidationRules($companyId),
            'email' => 'nullable|email|max:255',
            'phone' => Customer::tenantPhoneValidationRules($companyId),
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'notes' => 'nullable|string',
            'balance' => 'nullable|numeric|min:-99999999.99|max:99999999.99',
            'is_active' => 'boolean',
            'addresses' => 'nullable|array',
            'addresses.*.type' => 'nullable|string|max:255',
            'addresses.*.label' => 'required_with:addresses|string|max:255',
            'addresses.*.contact_name' => 'nullable|string|max:255',
            'addresses.*.contact_phone' => 'nullable|string|max:255',
            'addresses.*.address_line_1' => 'required_with:addresses|string',
            'addresses.*.address_line_2' => 'nullable|string',
            'addresses.*.city' => 'required_with:addresses|string|max:255',
            'addresses.*.state' => 'nullable|string|max:255',
            'addresses.*.postal_code' => 'nullable|string|max:255',
            'addresses.*.country' => 'nullable|string|max:255',
            'addresses.*.latitude' => 'nullable|numeric',
            'addresses.*.longitude' => 'nullable|numeric',
            'addresses.*.is_default' => 'boolean',
            'addresses.*.delivery_instructions' => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'This customer code is already used in your company.',
        ];
    }
}
