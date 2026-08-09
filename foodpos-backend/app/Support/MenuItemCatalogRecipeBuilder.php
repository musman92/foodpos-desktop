<?php

namespace App\Support;

use App\Models\MenuItem;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

class MenuItemCatalogRecipeBuilder
{
    /**
     * Create (or reuse by name) a catalog recipe from ingredient lines and attach as default.
     *
     * @param  list<array{ingredient_id:int, quantity:float|int|string, unit_id?:?string, waste_percentage?:float|int|string, notes?:?string}>  $lines
     */
    public static function setDefaultFromLines(MenuItem $menuItem, string $recipeName, array $lines, ?string $code = null): Recipe
    {
        return DB::transaction(function () use ($menuItem, $recipeName, $lines, $code) {
            $recipe = Recipe::withoutGlobalScopes()
                ->where('company_id', $menuItem->company_id)
                ->where('name', $recipeName)
                ->first();

            if (! $recipe) {
                $recipe = Recipe::create([
                    'company_id' => $menuItem->company_id,
                    'name' => $recipeName,
                    'code' => Recipe::resolveCode((int) $menuItem->company_id, $code),
                    'is_active' => true,
                ]);
            }

            $recipe->syncItems($lines);
            $menuItem->default_recipe_id = $recipe->id;
            $menuItem->type = 'recipe';
            $menuItem->save();
            $menuItem->cost = $menuItem->calculateCost();
            $menuItem->save();

            return $recipe;
        });
    }

    /**
     * Create catalog recipe for a variant option and attach.
     *
     * @param  list<array{ingredient_id:int, quantity:float|int|string, unit_id?:?string, waste_percentage?:float|int|string, notes?:?string}>  $lines
     */
    public static function setOptionFromLines(
        MenuItem $menuItem,
        int $variantId,
        string $optionName,
        string $recipeName,
        array $lines,
        ?string $code = null
    ): Recipe {
        return DB::transaction(function () use ($menuItem, $variantId, $optionName, $recipeName, $lines, $code) {
            $recipe = Recipe::create([
                'company_id' => $menuItem->company_id,
                'name' => $recipeName,
                'code' => Recipe::resolveCode((int) $menuItem->company_id, $code),
                'is_active' => true,
            ]);
            $recipe->syncItems($lines);

            $menuItem->variantRecipes()->updateOrCreate(
                [
                    'variant_id' => $variantId,
                    'option_name' => $optionName,
                ],
                ['recipe_id' => $recipe->id]
            );

            $menuItem->type = 'recipe';
            $menuItem->save();
            $menuItem->unsetRelation('defaultRecipe');
            $menuItem->unsetRelation('variantRecipes');
            $menuItem->cost = $menuItem->calculateCost();
            $menuItem->save();

            return $recipe;
        });
    }
}
