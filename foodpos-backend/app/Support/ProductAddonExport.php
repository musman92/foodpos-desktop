<?php

namespace App\Support;

use App\Models\ProductAddon;
use App\Models\ProductAddonRecipe;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductAddonExport
{
    public function download(string $format): StreamedResponse
    {
        $rows = [];

        ProductAddon::query()
            ->with(['menuItem:id,sku', 'recipes.ingredient:id,sku'])
            ->orderBy('name')
            ->get()
            ->each(function (ProductAddon $addon) use (&$rows) {
                if ($addon->type === ProductAddon::TYPE_RECIPE && $addon->recipes->isNotEmpty()) {
                    foreach ($addon->recipes as $recipe) {
                        $rows[] = $this->addonRow($addon, $recipe);
                    }

                    return;
                }

                $rows[] = $this->addonRow($addon, null);
            });

        return (new SpreadsheetTabularExport)->download(
            $format,
            'product-addons',
            'Product Addons',
            ProductAddonImportSampleExport::HEADERS,
            $rows,
        );
    }

    /**
     * @return list<mixed>
     */
    private function addonRow(ProductAddon $addon, ?ProductAddonRecipe $recipe): array
    {
        return [
            $addon->code ?? '',
            $addon->name,
            $this->formatNumber($addon->price),
            $this->inventoryTypeLabel($addon->type),
            $addon->track_inventory ? 'yes' : 'no',
            $addon->menuItem?->sku ?? '',
            $recipe?->ingredient?->sku ?? '',
            $recipe ? $this->formatNumber($recipe->quantity) : '',
            $recipe ? ($recipe->recipeUnitId() ?? '') : '',
            $recipe ? $this->formatNumber($recipe->waste_percentage) : '',
        ];
    }

    private function inventoryTypeLabel(string $type): string
    {
        return match ($type) {
            ProductAddon::TYPE_RECIPE => 'recipe',
            ProductAddon::TYPE_SINGLE => 'single',
            default => 'none',
        };
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
