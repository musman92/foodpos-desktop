<?php

namespace App\Http\Requests\Concerns;

use App\Models\Ingredient;
use App\Support\IngredientQuantity;

trait ValidatesRecipeUnits
{
    protected function validateRecipeUnits($validator): void
    {
        if ($this->input('type') !== 'recipe') {
            return;
        }

        $recipes = $this->input('recipes', []);
        if (! is_array($recipes)) {
            return;
        }

        foreach ($recipes as $index => $recipeData) {
            if (empty($recipeData['ingredient_id']) || empty($recipeData['quantity'])) {
                continue;
            }

            $ingredient = Ingredient::with(['consumptionUnit', 'purchaseUnit'])->find($recipeData['ingredient_id']);
            if (! $ingredient) {
                continue;
            }

            $unitId = $recipeData['unit_id'] ?? null;

            if (! IngredientQuantity::isValidRecipeUnit($ingredient, $unitId)) {
                $validator->errors()->add(
                    "recipes.{$index}.unit_id",
                    IngredientQuantity::conversionErrorMessage($ingredient, $unitId)
                );
            }
        }
    }
}
