<?php

namespace App\Http\Requests;

use App\Models\ProductAddon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = auth()->user()?->company_id;

        return [
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('product_addons', 'code')
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'type' => ['required', Rule::in([ProductAddon::TYPE_NONE, ProductAddon::TYPE_SINGLE, ProductAddon::TYPE_RECIPE])],
            'track_inventory' => ['nullable', 'boolean'],
            'menu_item_id' => ['nullable', 'required_if:type,single', 'exists:menu_items,id'],
            'recipes' => ['nullable', 'array'],
            'recipes.*.ingredient_id' => ['nullable', 'exists:ingredients,id'],
            'recipes.*.quantity' => ['nullable', 'numeric', 'min:0.0001'],
            'recipes.*.unit_id' => ['nullable', 'string', 'max:50'],
            'recipes.*.waste_percentage' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
