<?php

namespace App\Http\Requests;

use App\Models\Ingredient;
use App\Support\IngredientQuantity;
use App\Support\TenantIngredientAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = auth()->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('recipes', 'code')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => [
                'required',
                Rule::exists('ingredients', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_id' => ['nullable', 'string', 'max:50'],
            'items.*.waste_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $companyId = (int) auth()->user()->company_id;
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            $seen = [];

            foreach ($items as $index => $row) {
                $ingredientId = $row['ingredient_id'] ?? null;
                if (! $ingredientId) {
                    continue;
                }

                if (isset($seen[$ingredientId])) {
                    $validator->errors()->add("items.{$index}.ingredient_id", 'Duplicate ingredient in this recipe.');

                    continue;
                }
                $seen[$ingredientId] = true;

                $ingredient = Ingredient::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->find($ingredientId);
                if (! $ingredient || ! TenantIngredientAccess::isUsableByCompany($ingredient, $companyId)) {
                    $validator->errors()->add("items.{$index}.ingredient_id", 'Invalid ingredient selected.');

                    continue;
                }

                $ingredient->load(['consumptionUnit', 'purchaseUnit']);

                $unitId = $row['unit_id'] ?? null;
                if (! IngredientQuantity::isValidRecipeUnit($ingredient, $unitId)) {
                    $validator->errors()->add(
                        "items.{$index}.unit_id",
                        IngredientQuantity::conversionErrorMessage($ingredient, $unitId)
                    );
                }
            }
        });
    }
}
