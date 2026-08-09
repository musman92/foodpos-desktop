<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngredientUnitRequest extends FormRequest
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
        $companyId = auth()->user()->company_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ingredient_units', 'name')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('ingredient_units', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'description' => ['nullable', 'string'],
        ];
    }
}
