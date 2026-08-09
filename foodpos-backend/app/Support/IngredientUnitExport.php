<?php

namespace App\Support;

use App\Models\IngredientUnit;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientUnitExport
{
    public function download(string $format): StreamedResponse
    {
        $rows = IngredientUnit::query()
            ->orderBy('name')
            ->get()
            ->map(fn (IngredientUnit $unit) => [
                $unit->code ?? '',
                $unit->name,
                $unit->description ?? '',
            ])
            ->all();

        return (new SpreadsheetTabularExport)->download(
            $format,
            'ingredient-units',
            'Ingredient Units',
            IngredientUnitImportSampleExport::HEADERS,
            $rows,
        );
    }
}
