<?php

namespace App\Support;

use App\Models\IngredientUnit;
use App\Models\MenuItem;

final class PurchaseCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function options(?\App\Models\User $user = null): array
    {
        $ingredients = self::ingredientEntries(
            IngredientPicker::query(IngredientPicker::CONTEXT_PURCHASE, $user)
                ->select([
                    'id',
                    'company_id',
                    'name',
                    'sku',
                    'purchase_unit_id',
                    'conversion_rate',
                    'purchase_price',
                ])
                ->with(['purchaseUnit:id,name,code'])
                ->get()
        );

        $menuItems = self::menuItemEntries(
            MenuItem::query()
                ->where('is_available', true)
                ->where('type', 'single')
                ->where('track_inventory', true)
                ->select([
                    'id',
                    'company_id',
                    'name',
                    'sku',
                    'cost',
                    'purchase_unit_id',
                    'consumption_unit_id',
                    'conversion_rate',
                    'purchase_price',
                ])
                ->with(['purchaseUnit:id,name,code'])
                ->orderBy('name')
                ->get()
        );

        return collect($ingredients)
            ->concat($menuItems)
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Ingredient>  $ingredients
     * @return list<array<string, mixed>>
     */
    private static function ingredientEntries(\Illuminate\Support\Collection $ingredients): array
    {
        return $ingredients->map(function (\App\Models\Ingredient $ingredient) {
            $label = $ingredient->name;
            if ($ingredient->sku) {
                $label .= ' ('.$ingredient->sku.')';
            }

            return [
                'id' => 'ingredient-'.$ingredient->id,
                'item_type' => 'ingredient',
                'item_id' => (int) $ingredient->id,
                'name' => $ingredient->name,
                'label' => $label,
                'company_id' => $ingredient->company_id,
                'purchase_unit_name' => $ingredient->purchaseUnit?->displayLabel() ?? '—',
                'purchase_unit_key' => (string) ($ingredient->purchase_unit_id ?? ''),
                'purchase_price' => (float) $ingredient->purchase_price,
                'conversion_rate' => (float) ($ingredient->conversion_rate ?: 1),
            ];
        })->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MenuItem>  $menuItems
     * @return list<array<string, mixed>>
     */
    private static function menuItemEntries(\Illuminate\Support\Collection $menuItems): array
    {
        $costService = app(\App\Services\MenuItemCostService::class);

        return $menuItems->map(function (MenuItem $item) use ($costService) {
            $label = $item->name;
            if ($item->sku) {
                $label .= ' ('.$item->sku.')';
            }

            if (! $item->purchase_unit_id) {
                $defaults = MenuItem::defaultUnitAttributes((int) $item->company_id);
                $purchaseUnitId = $defaults['purchase_unit_id'];
                $conversionRate = $defaults['conversion_rate'];
            } else {
                $purchaseUnitId = $item->purchase_unit_id;
                $conversionRate = (float) ($item->conversion_rate ?: 1);
            }

            return [
                'id' => 'menu_item-'.$item->id,
                'item_type' => 'menu_item',
                'item_id' => (int) $item->id,
                'name' => $item->name,
                'label' => $label,
                'company_id' => $item->company_id,
                'purchase_unit_name' => $item->purchaseUnit?->displayLabel()
                    ?? UnitLabel::forIngredientUnitId($purchaseUnitId, (int) $item->company_id),
                'purchase_unit_key' => (string) $purchaseUnitId,
                'purchase_price' => (float) ($item->purchase_price ?: $costService->preferredPurchasePrice($item)),
                'conversion_rate' => $conversionRate,
            ];
        })->all();
    }
}
