<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreUserRequest extends FormRequest
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
            'can_login' => $this->boolean('can_login'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = auth()->user();

        $companyId = $user->company_id;
        $linkedEmployeeUserId = null;
        if ($this->filled('employee_profile_id')) {
            $linkedEmployeeUserId = \App\Models\EmployeeProfile::query()
                ->whereKey($this->input('employee_profile_id'))
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->value('user_id');
        }

        $employeeExistsRule = Rule::exists('employee_profiles', 'id');
        if ($companyId) {
            $employeeExistsRule->where('company_id', $companyId);
        }

        $rules = [
            'employee_profile_id' => ['nullable', $employeeExistsRule],
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($linkedEmployeeUserId),
            ],
            'can_login' => ['required', 'boolean'],
            'password' => [
                Rule::requiredIf(fn () => $this->boolean('can_login') || $this->filled('employee_profile_id')),
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
            'phone' => 'nullable|string|max:255',
            'type' => ['required', 'string', Rule::in(User::ACCOUNT_TYPES)],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];

        // Super admin can assign company
        if ($user->isSuperAdmin()) {
            $rules['company_id'] = 'nullable|exists:companies,id';
        }

        // Company admin and super admin can assign branches (multiple)
        if ($user->isSuperAdmin() || $user->isCompanyAdmin()) {
            $rules['branches'] = 'nullable|array';
            $rules['branches.*'] = 'exists:branches,id';
            $rules['primary_branch_id'] = 'nullable|exists:branches,id';
        }

        // Role: must be one of the company-scoped roles (tenant + global)
        $allowedRoleNames = Role::when($companyId !== null, function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        }, function ($q) {
            $q->whereNull('company_id');
        })->pluck('name')->toArray();
        $rules['role'] = [
            Rule::requiredIf(function () {
                $type = $this->input('type');
                if ($type === 'staff') {
                    return true;
                }

                return $this->boolean('can_login')
                    && in_array($type, User::FLOOR_ACCOUNT_TYPES, true);
            }),
            'nullable',
            'string',
            Rule::in($allowedRoleNames),
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'A password is required when the user can sign in.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
            'type.required' => 'The account level field is required.',
            'role.required' => 'Select a role when this account can sign in (e.g. Cashier).',
            'status.required' => 'The status field is required.',
        ];
    }
}
