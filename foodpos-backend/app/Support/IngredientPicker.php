<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Centralized ingredient lists for searchable dropdowns (recipes, purchases, adjustments).
 */
class IngredientPicker
{
    public const CONTEXT_RECIPE = 'recipe';

    public const CONTEXT_PURCHASE = 'purchase';

    public const CONTEXT_INVENTORY_ADJUSTMENT = 'inventory_adjustment';

    /**
     * @return Builder<Ingredient>
     */
    public static function query(string $context, ?User $user = null): Builder
    {
        $query = Ingredient::query()
            ->where('is_active', true)
            ->with(['purchaseUnit', 'consumptionUnit']);

        match ($context) {
            self::CONTEXT_RECIPE => $query
                ->orderByRaw('company_id IS NULL DESC')
                ->orderBy('name'),
            self::CONTEXT_PURCHASE => $query->orderBy('name'),
            self::CONTEXT_INVENTORY_ADJUSTMENT => $query
                ->where('track_stock', '!=', 'no')
                ->orderBy('name'),
            default => $query->orderBy('name'),
        };

        return $query;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function options(string $context, ?User $user = null): array
    {
        return self::mapOptions(self::query($context, $user)->get(), $context);
    }

    /**
     * @param  Collection<int, Ingredient>  $ingredients
     * @return list<array<string, mixed>>
     */
    public static function mapOptions(Collection $ingredients, string $context): array
    {
        return $ingredients
            ->map(fn (Ingredient $ingredient) => self::optionFromModel($ingredient, $context))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function optionFromModel(Ingredient $ingredient, string $context): array
    {
        $consumptionLabel = $ingredient->consumptionUnit?->name ?? $ingredient->unit_name;
        $purchaseLabel = $ingredient->purchaseUnit?->name;

        $label = $ingredient->displayLabel();
        if (in_array($context, [self::CONTEXT_INVENTORY_ADJUSTMENT, self::CONTEXT_RECIPE], true) && $consumptionLabel) {
            $label .= ' ('.$consumptionLabel.')';
        }

        return [
            'id' => (int) $ingredient->id,
            'name' => $ingredient->name,
            'code' => $ingredient->sku,
            'sku' => $ingredient->sku,
            'label' => $label,
            'base_unit_id' => $ingredient->base_unit_id,
            'consumption_unit_id' => $ingredient->consumption_unit_id,
            'consumption_unit_key' => (string) ($ingredient->consumption_unit_id ?? $ingredient->base_unit_id ?? ''),
            'consumption_unit_name' => $consumptionLabel ?? '—',
            'purchase_unit_key' => (string) ($ingredient->purchase_unit_id ?? ''),
            'purchase_unit_id' => $ingredient->purchase_unit_id,
            'purchase_unit_name' => $purchaseLabel,
            'purchase_price' => (float) $ingredient->purchase_price,
            'cost_per_unit' => (float) $ingredient->cost_per_unit,
            'conversion_rate' => (float) ($ingredient->conversion_rate ?: 1),
            'company_id' => $ingredient->company_id,
        ];
    }
}
