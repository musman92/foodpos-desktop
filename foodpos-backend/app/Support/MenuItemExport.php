<?php

namespace App\Support;

use App\Models\MenuItem;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MenuItemExport
{
    public function download(): StreamedResponse
    {
        MenuItem::backfillMissingSkus();

        $menuItemRows = [];
        $variantRows = [];
        $addonRows = [];
        $recipeRows = [];

        MenuItem::query()
            ->with([
                'category:id,code',
                'variants' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
                'productAddons:id,code',
                'defaultRecipe:id,code,name',
                'variantRecipes.recipe:id,code,name',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (MenuItem $menuItem) use (&$menuItemRows, &$variantRows, &$addonRows, &$recipeRows) {
                $menuItemRows[] = $this->menuItemRow($menuItem);

                foreach ($this->variantPriceRows($menuItem) as $row) {
                    $variantRows[] = $row;
                }

                foreach ($this->addonLinkRows($menuItem) as $row) {
                    $addonRows[] = $row;
                }

                foreach ($this->recipeLinkRows($menuItem) as $row) {
                    $recipeRows[] = $row;
                }
            });

        return MenuItemImportSampleExport::downloadWorkbook(
            'menu-items.xlsx',
            $menuItemRows,
            $variantRows,
            $addonRows,
            $recipeRows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function menuItemRow(MenuItem $menuItem): array
    {
        return [
            'menu_item_code' => (string) $menuItem->sku,
            'name' => $menuItem->name,
            'category_code' => $menuItem->category?->code ?? '',
            'price' => $this->formatNumber($menuItem->price),
            'type' => $menuItem->type,
            'track_inventory' => $menuItem->track_inventory ? 'yes' : 'no',
            'is_available' => $menuItem->is_available ? 'yes' : 'no',
            'description' => $menuItem->description ?? '',
            'preparation_time' => $menuItem->preparation_time ?? '',
            'sort_order' => (int) $menuItem->sort_order,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function variantPriceRows(MenuItem $menuItem): array
    {
        $rows = [];
        $menuItemCode = (string) $menuItem->sku;

        foreach ($menuItem->variants as $variant) {
            $optionPrices = json_decode($variant->pivot->option_prices ?? '[]', true);
            if (! is_array($optionPrices)) {
                $optionPrices = [];
            }

            $optionPrices = $this->sortedOptionPrices($variant, $optionPrices);
            $isDefaultVariant = (bool) $variant->pivot->is_default;
            $firstOption = true;

            foreach ($optionPrices as $optionName => $optionPrice) {
                $rows[] = [
                    'menu_item_code' => $menuItemCode,
                    'variant_code' => $variant->code ?? '',
                    'option_name' => (string) $optionName,
                    'option_price' => $this->formatNumber($optionPrice),
                    'is_default' => ($isDefaultVariant && $firstOption) ? 'yes' : 'no',
                ];
                $firstOption = false;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function addonLinkRows(MenuItem $menuItem): array
    {
        $menuItemCode = (string) $menuItem->sku;

        return $menuItem->productAddons
            ->sortBy('name')
            ->map(fn ($addon) => [
                'menu_item_code' => $menuItemCode,
                'addon_code' => $addon->code ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * One row per catalog recipe link (not ingredient BOM lines).
     *
     * @return list<array<string, mixed>>
     */
    private function recipeLinkRows(MenuItem $menuItem): array
    {
        $menuItemCode = (string) $menuItem->sku;
        $variantsById = $menuItem->variants->keyBy('id');
        $rows = [];
        $hasVariants = $menuItem->variants->isNotEmpty();

        // With variants, default_recipe_id is unused in the product UI — export per-option links only.
        if (! $hasVariants && $menuItem->defaultRecipe?->code) {
            $rows[] = [
                'menu_item_code' => $menuItemCode,
                'variant_code' => '',
                'option_name' => '',
                'recipe_code' => (string) $menuItem->defaultRecipe->code,
            ];
        }

        foreach ($menuItem->variantRecipes as $link) {
            $code = $link->recipe?->code;
            if (! $code) {
                continue;
            }
            $variant = $variantsById->get($link->variant_id);
            $rows[] = [
                'menu_item_code' => $menuItemCode,
                'variant_code' => (string) ($variant?->code ?? ''),
                'option_name' => (string) $link->option_name,
                'recipe_code' => (string) $code,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $optionPrices
     * @return array<string, mixed>
     */
    private function sortedOptionPrices(\App\Models\Variant $variant, array $optionPrices): array
    {
        $order = [];
        foreach ($variant->options ?? [] as $option) {
            if (is_array($option) && isset($option['name'])) {
                $order[(string) $option['name']] = (int) ($option['sort_order'] ?? 0);
            }
        }

        uksort($optionPrices, function (string $a, string $b) use ($order): int {
            $sortA = $order[$a] ?? PHP_INT_MAX;
            $sortB = $order[$b] ?? PHP_INT_MAX;
            if ($sortA !== $sortB) {
                return $sortA <=> $sortB;
            }

            return strcasecmp($a, $b);
        });

        return $optionPrices;
    }

    private function formatNumber(mixed $value): float|int|string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;

        if (fmod($number, 1.0) === 0.0) {
            return (int) $number;
        }

        return $number;
    }
}
