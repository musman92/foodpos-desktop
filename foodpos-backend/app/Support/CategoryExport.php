<?php

namespace App\Support;

use App\Models\Category;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryExport
{
    public function download(string $format): StreamedResponse
    {
        $rows = Category::query()
            ->with('parent:id,code')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                $category->code ?? '',
                $category->name,
                $category->parent?->code ?? '',
                $category->description ?? '',
                (int) $category->sort_order,
                $category->is_active ? 'yes' : 'no',
            ])
            ->all();

        return (new SpreadsheetTabularExport)->download(
            $format,
            'categories',
            'Categories',
            CategoryImportSampleExport::HEADERS,
            $rows,
        );
    }
}
