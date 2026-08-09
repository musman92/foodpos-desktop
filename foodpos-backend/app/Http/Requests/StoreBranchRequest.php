<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user->isSuperAdmin() || $user->isCompanyAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::user();
        $companyId = $user->isSuperAdmin() 
            ? $this->input('company_id')
            : $user->company_id;

        return [
            'company_id' => $user->isSuperAdmin() 
                ? 'required|exists:companies,id'
                : 'sometimes',
            'name' => 'required|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:50',
                'unique:branches,code,NULL,id,company_id,' . $companyId,
            ],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'timezone' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'company_id.required' => 'Please select a company.',
            'company_id.exists' => 'The selected company does not exist.',
            'name.required' => 'Branch name is required.',
            'code.unique' => 'This branch code already exists for this company.',
            'timezone.required' => 'Timezone is required.',
            'status.required' => 'Status is required.',
        ];
    }
}
