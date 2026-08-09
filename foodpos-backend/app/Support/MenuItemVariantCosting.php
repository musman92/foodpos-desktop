<?php

namespace App\Support;

use App\Models\MenuItem;
use App\Models\MenuItemRecipeLine;
use App\Models\RecipeItem;
use Illuminate\Support\Collection;

class MenuItemVariantCosting
{
    /**
     * @return array<int, array{
     *     scope: string,
     *     label: string,
     *     selling_price: float,
     *     recipe_cost: float,
     *     gross_margin: float,
     *     margin_percent: ?float,
     *     uses_fallback_recipe: bool,
     *     recipes: Collection<int, RecipeItem|MenuItemRecipeLine>
     * }>
     */
    public static function breakdown(MenuItem $menuItem): array
    {
        $menuItem->loadMissing([
            'defaultRecipe.items.ingredient',
            'variantRecipes.recipe.items.ingredient',
            'variants',
            'legacyRecipeLines.ingredient',
        ]);

        $basePrice = (float) $menuItem->price;
        $rows = [];

        if ($menuItem->variants->isEmpty()) {
            $lines = $menuItem->resolveRecipes(null, null);
            $cost = $lines->sum(fn ($line) => $line->lineCost());

            return [self::row(
                scope: '',
                label: 'Default',
                sellingPrice: $basePrice,
                recipeCost: $cost,
                recipes: $lines,
                usesFallback: false,
            )];
        }

        foreach ($menuItem->variants as $variant) {
            $optionPrices = self::parseOptionPrices($variant->pivot->option_prices ?? null);
            $options = self::variantOptions($variant, $optionPrices, $basePrice);

            foreach ($options as $option) {
                $optionName = $option['name'];
                $scope = MenuItem::recipeScopeKey((int) $variant->id, $optionName);
                $hasOwn = $menuItem->variantRecipes
                    ->contains(fn ($link) => (int) $link->variant_id === (int) $variant->id
                        && (string) $link->option_name === (string) $optionName);
                $usesFallback = ! $hasOwn && $menuItem->default_recipe_id;
                // Also true if using legacy default scope
                if (! $hasOwn && ! $menuItem->default_recipe_id) {
                    $scopedLegacy = $menuItem->legacyRecipeLines->where('recipe_scope', $scope);
                    $usesFallback = $scopedLegacy->isEmpty();
                }

                $effectiveRecipes = $menuItem->resolveRecipes((int) $variant->id, $optionName);
                $cost = $effectiveRecipes->sum(fn ($line) => $line->lineCost());
                $sellingPrice = (float) $option['price'];

                $rows[] = self::row(
                    scope: $scope,
                    label: $variant->name.': '.$optionName,
                    sellingPrice: $sellingPrice,
                    recipeCost: $cost,
                    recipes: $effectiveRecipes,
                    usesFallback: (bool) $usesFallback && ! $hasOwn,
                );
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, RecipeItem|MenuItemRecipeLine>  $recipes
     * @return array{
     *     scope: string,
     *     label: string,
     *     selling_price: float,
     *     recipe_cost: float,
     *     gross_margin: float,
     *     margin_percent: ?float,
     *     uses_fallback_recipe: bool,
     *     recipes: Collection<int, RecipeItem|MenuItemRecipeLine>
     * }
     */
    protected static function row(
        string $scope,
        string $label,
        float $sellingPrice,
        float $recipeCost,
        Collection $recipes,
        bool $usesFallback
    ): array {
        $grossMargin = $sellingPrice - $recipeCost;
        $marginPercent = $sellingPrice > 0
            ? ($grossMargin / $sellingPrice) * 100
            : null;

        return [
            'scope' => $scope,
            'label' => $label,
            'selling_price' => $sellingPrice,
            'recipe_cost' => $recipeCost,
            'gross_margin' => $grossMargin,
            'margin_percent' => $marginPercent,
            'uses_fallback_recipe' => $usesFallback,
            'recipes' => $recipes,
        ];
    }

    /**
     * @return array<string, float>
     */
    protected static function parseOptionPrices(mixed $raw): array
    {
        if (is_array($raw)) {
            $prices = $raw;
        } elseif (is_string($raw)) {
            $prices = json_decode($raw, true) ?? [];
        } else {
            $prices = [];
        }

        if (! is_array($prices)) {
            return [];
        }

        $normalized = [];
        foreach ($prices as $key => $value) {
            if (is_array($value) && isset($value['name'], $value['price'])) {
                $normalized[(string) $value['name']] = (float) $value['price'];
            } elseif (is_string($key) && is_numeric($value)) {
                $normalized[$key] = (float) $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, float>  $optionPrices
     * @return array<int, array{name: string, price: float}>
     */
    protected static function variantOptions($variant, array $optionPrices, float $menuItemPrice): array
    {
        $pivotPrice = $variant->pivot->price !== null ? (float) $variant->pivot->price : $menuItemPrice;
        $options = [];

        if ($variant->options && is_array($variant->options)) {
            foreach ($variant->options as $opt) {
                $name = is_array($opt) ? ($opt['name'] ?? '') : '';
                if ($name === '') {
                    continue;
                }
                $options[] = [
                    'name' => $name,
                    'price' => $optionPrices[$name] ?? $pivotPrice,
                ];
            }
        }

        if ($options === [] && $optionPrices !== []) {
            foreach ($optionPrices as $name => $price) {
                $options[] = [
                    'name' => (string) $name,
                    'price' => (float) $price,
                ];
            }
        }

        return $options;
    }
}
