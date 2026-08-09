<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = auth()->user()->company_id;
        $unitRule = Rule::exists('ingredient_units', 'id')->where(fn ($q) => $q->where('company_id', $companyId));
        /** @var \App\Models\Ingredient|null $ingredient */
        $ingredient = $this->route('ingredient');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('ingredients', 'sku')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($ingredient?->id),
            ],
            'category_id' => [
                'required',
                Rule::exists('ingredient_categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'purchase_unit_id' => ['required', $unitRule],
            'consumption_unit_id' => ['required', $unitRule],
            'conversion_rate' => ['required', 'numeric', 'gt:0'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'min_stock_level' => ['required', 'numeric', 'min:0'],
            'max_stock_level' => ['nullable', 'numeric', 'min:0'],
            'track_stock' => ['nullable', 'in:yes,no'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
