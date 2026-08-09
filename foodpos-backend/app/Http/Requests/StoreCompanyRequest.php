<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreCompanyRequest extends FormRequest
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
        return [
            // Company fields
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:companies,slug',
            'email' => 'required|email|max:255|unique:companies,email',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'tax_id' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
            'timezone' => 'nullable|string|max:255',
            'status' => 'required|in:active,suspended,inactive',
            'subscription_expires_at' => 'nullable|date',
            'trial_days' => 'nullable|integer|in:'.implode(',', array_keys(config('platform_billing.trial_options', []))),
            'billing_enabled' => 'sometimes|boolean',
            'billing_currency' => 'nullable|string|size:3',
            'billing_amount' => 'nullable|numeric|min:0',
            'billing_interval' => 'nullable|string|in:'.implode(',', array_keys(config('platform_billing.intervals', []))),
            'billing_due_date' => 'nullable|date',
            'billing_notes' => 'nullable|string|max:2000',
            
            // Admin user fields
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
            'create_default_branch' => 'nullable|boolean',
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
            // Company messages
            'name.required' => 'Company name is required.',
            'email.required' => 'Company email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'slug.unique' => 'This slug is already taken.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be active, suspended, or inactive.',
            'currency.size' => 'Currency must be a 3-character code (e.g., USD).',
            
            // Admin user messages
            'admin_name.required' => 'Admin name is required.',
            'admin_email.required' => 'Admin email is required.',
            'admin_email.email' => 'Please provide a valid email address for the admin.',
            'admin_email.unique' => 'This email is already registered by another user.',
            'admin_password.required' => 'Admin password is required.',
            'admin_password.min' => 'Admin password must be at least 8 characters.',
            'admin_password.confirmed' => 'Admin password confirmation does not match.',
        ];
    }
}

