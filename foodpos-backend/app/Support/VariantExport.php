<?php

namespace App\Support;

use App\Models\ProductAddon;
use App\Models\ProductAddonRecipe;
use App\Models\Variant;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VariantExport
{
    public function download(string $format): StreamedResponse
    {
        $rows = [];

        Variant::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function (Variant $variant) use (&$rows) {
                $options = collect($variant->options ?? [])
                    ->sortBy(fn ($option) => (int) ($option['sort_order'] ?? 0))
                    ->values();

                if ($options->isEmpty()) {
                    $rows[] = $this->variantRow($variant, null);

                    return;
                }

                foreach ($options as $option) {
                    $rows[] = $this->variantRow($variant, $option);
                }
            });

        return (new SpreadsheetTabularExport)->download(
            $format,
            'variants',
            'Variants',
            VariantImportSampleExport::HEADERS,
            $rows,
        );
    }

    /**
     * @param  array<string, mixed>|null  $option
     * @return list<mixed>
     */
    private function variantRow(Variant $variant, ?array $option): array
    {
        return [
            $variant->code ?? '',
            $variant->name,
            $option['name'] ?? '',
            $option['code'] ?? '',
            $this->formatOptionalNumber($option['price'] ?? null),
            isset($option['sort_order']) ? (int) $option['sort_order'] : '',
            $variant->description ?? '',
            (int) $variant->sort_order,
            $variant->is_active ? 'yes' : 'no',
        ];
    }

    private function formatOptionalNumber(mixed $value): float|int|string
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
