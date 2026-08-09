<?php

namespace App\Support;

use App\Models\IngredientCategory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientCategoryExport
{
    public function download(string $format): StreamedResponse
    {
        $rows = IngredientCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (IngredientCategory $category) => [
                $category->code ?? '',
                $category->name,
                $category->description ?? '',
                $category->sort_order,
                $category->is_active ? 'yes' : 'no',
            ])
            ->all();

        return (new SpreadsheetTabularExport)->download(
            $format,
            'ingredient-categories',
            'Ingredient Categories',
            IngredientCategoryImportSampleExport::HEADERS,
            $rows,
        );
    }
}
