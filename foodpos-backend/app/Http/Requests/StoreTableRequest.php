<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && ($user->isSuperAdmin() || $user->isCompanyAdmin());
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('code') === '' || $this->input('code') === null) {
            $this->merge(['code' => null]);
        }
        if ($this->input('floor_id') === '' || $this->input('floor_id') === null) {
            $this->merge(['floor_id' => null]);
        }
        if ($this->has('slug')) {
            $s = $this->input('slug');
            if ($s === '' || $s === null) {
                $this->merge(['slug' => null]);
            } else {
                $this->merge(['slug' => Str::slug((string) $s) ?: null]);
            }
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::user();
        $branchRule = $user->isSuperAdmin()
            ? ['required', 'exists:branches,id']
            : ['required', Rule::exists('branches', 'id')->where('company_id', $user->company_id)];

        $branchId = (int) $this->input('branch_id');
        $companyId = (int) Branch::withoutGlobalScope('tenant')->where('id', $branchId)->value('company_id');

        return [
            'branch_id' => $branchRule,
            'floor_id' => [
                'nullable',
                Rule::exists('floors', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('tables', 'slug')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('tables', 'code')->where('branch_id', $branchId),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'status' => ['required', 'in:available,occupied,reserved,dirty,out_of_service'],
            'section' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
