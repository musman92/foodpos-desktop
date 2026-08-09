<?php

namespace App\Support;

use App\Models\Recipe;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecipeExport
{
    public function download(string $format): StreamedResponse
    {
        $rows = [];

        Recipe::query()
            ->with(['items.ingredient'])
            ->orderBy('code')
            ->orderBy('name')
            ->get()
            ->each(function (Recipe $recipe) use (&$rows) {
                $items = $recipe->items->sortBy(fn ($item) => $item->ingredient?->sku ?? $item->ingredient?->name ?? '')->values();

                if ($items->isEmpty()) {
                    $rows[] = $this->recipeRow($recipe, null);

                    return;
                }

                foreach ($items as $item) {
                    $rows[] = $this->recipeRow($recipe, $item);
                }
            });

        return (new SpreadsheetTabularExport)->download(
            $format,
            'recipes',
            'Recipes',
            RecipeImportSampleExport::HEADERS,
            $rows,
        );
    }

    /**
     * @return list<mixed>
     */
    private function recipeRow(Recipe $recipe, mixed $item): array
    {
        return [
            $recipe->code ?? '',
            $recipe->name,
            $recipe->description ?? '',
            $recipe->is_active ? 'yes' : 'no',
            $item?->ingredient?->sku ?? '',
            $item?->ingredient?->name ?? '',
            $item ? $this->formatNumber($item->quantity) : '',
            $item ? ($item->recipeUnitId() ?? '') : '',
            $item ? $this->formatNumber($item->waste_percentage) : '',
            $item?->notes ?? '',
        ];
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
