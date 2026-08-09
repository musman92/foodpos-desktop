<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user && $user->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = $this->route('company')->id ?? null;

        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('companies', 'slug')->ignore($companyId),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('companies', 'email')->ignore($companyId),
            ],
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
            'timezone' => 'nullable|string|max:255',
            'status' => 'required|in:active,suspended,inactive',
            'demo' => 'sometimes|boolean',
            'billing_currency' => 'nullable|string|size:3',
            'billing_amount' => 'nullable|numeric|min:0',
            'billing_interval' => 'nullable|string|in:'.implode(',', array_keys(config('platform_billing.intervals', []))),
            'billing_enabled' => 'sometimes|boolean',
            'billing_notes' => 'nullable|string|max:2000',
            'billing_due_date' => 'nullable|date',
            'trial_ends_at' => 'nullable|date',
            'billing_starts_at' => 'nullable|date',
            'subscription_expires_at' => 'nullable|date',
            'addons' => 'nullable|array',
            'addons.*' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'slug.unique' => 'This slug is already taken.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be active, suspended, or inactive.',
            'currency.size' => 'Currency must be a 3-character code (e.g., USD).',
        ];
    }
}

