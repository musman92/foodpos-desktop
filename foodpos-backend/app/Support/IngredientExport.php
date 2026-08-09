<?php

namespace App\Support;

use App\Models\Ingredient;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientExport
{
    public function download(string $format): StreamedResponse
    {
        $rows = Ingredient::query()
            ->with([
                'category:id,code',
                'purchaseUnit:id,code',
                'consumptionUnit:id,code',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Ingredient $ingredient) => [
                $ingredient->sku ?? '',
                $ingredient->name,
                $ingredient->category?->code ?? '',
                $ingredient->purchaseUnit?->code ?? '',
                $ingredient->consumptionUnit?->code ?? '',
                $this->formatNumber($ingredient->conversion_rate),
                $this->formatNumber($ingredient->purchase_price),
                $this->formatNumber($ingredient->min_stock_level),
                $ingredient->description ?? '',
                $ingredient->is_active ? 'yes' : 'no',
            ])
            ->all();

        return (new SpreadsheetTabularExport)->download(
            $format,
            'ingredients',
            'Ingredients',
            IngredientImportSampleExport::HEADERS,
            $rows,
        );
    }

    private function formatNumber(mixed $value): float|int|string
    {
        $number = (float) $value;

        if (fmod($number, 1.0) === 0.0) {
            return (int) $number;
        }

        return $number;
    }
}
