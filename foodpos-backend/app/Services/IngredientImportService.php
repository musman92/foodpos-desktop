<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientUnit;
use App\Support\IngredientImportReferences;
use App\Support\IngredientImportSampleExport;
use App\Support\IngredientSku;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class IngredientImportService
{
    private const MAX_ROWS = 1000;

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'sku' => ['sku', 'code', 'ingredient_code', 'ingredient code'],
        'name' => ['name', 'ingredient_name', 'ingredient name', 'ingredient'],
        'category_code' => ['category_code', 'category code', 'category'],
        'category_name' => ['category_name', 'category name'],
        'purchase_unit_code' => ['purchase_unit_code', 'purchase unit code', 'purchase_unit', 'purchase unit'],
        'consumption_unit_code' => ['consumption_unit_code', 'consumption unit code', 'consumption_unit', 'consumption unit', 'unit_code', 'unit code'],
        'conversion_rate' => ['conversion_rate', 'conversion rate', 'conversion'],
        'purchase_price' => ['purchase_price', 'purchase price', 'price'],
        'min_stock_level' => ['min_stock_level', 'min stock level', 'low_qty', 'low qty', 'min_stock', 'min stock'],
        'max_stock_level' => ['max_stock_level', 'max stock level', 'max_stock', 'max stock'],
        'track_stock' => ['track_stock', 'track stock', 'track_inventory', 'track inventory'],
        'is_active' => ['is_active', 'active', 'status', 'enabled'],
        'description' => ['description', 'desc'],
    ];

    /**
     * @return array{created: int, updated: int, restored: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, int $companyId, int $userId): array
    {
        $parsed = $this->parseFile($file);
        $rows = $parsed['rows'];

        $result = [
            'created' => 0,
            'updated' => 0,
            'restored' => 0,
            'skipped' => 0,
            'errors' => $parsed['errors'],
        ];

        if ($rows === []) {
            if ($result['errors'] === []) {
                $result['errors'][] = ['row' => 1, 'message' => 'The file has no data rows to import.'];
            }

            return $result;
        }

        DB::transaction(function () use ($rows, $companyId, $userId, &$result) {
            foreach ($rows as $row) {
                $outcome = $this->importRow($row, $companyId, $userId);

                if ($outcome['status'] === 'created') {
                    $result['created']++;
                } elseif ($outcome['status'] === 'updated') {
                    $result['updated']++;
                    if (! empty($outcome['restored'])) {
                        $result['restored']++;
                    }
                } else {
                    $result['skipped']++;
                    $result['errors'][] = [
                        'row' => $row['_row'],
                        'message' => $outcome['message'] ?? 'Could not import row.',
                    ];
                }
            }
        });

        return $result;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<array{row: int, message: string}>}
     */
    public function parseFile(UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (\Throwable) {
            return [
                'rows' => [],
                'errors' => [['row' => 1, 'message' => 'Could not read the uploaded file. Please use a valid CSV or Excel file.']],
            ];
        }

        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if ($sheetRows === []) {
            return [
                'rows' => [],
                'errors' => [['row' => 1, 'message' => 'The file is empty.']],
            ];
        }

        $headerRow = array_shift($sheetRows);
        $columnMap = $this->mapHeaders($headerRow);

        if (! isset($columnMap['name'])) {
            return [
                'rows' => [],
                'errors' => [['row' => 1, 'message' => 'Missing required "name" column. Download the sample file for the expected format.']],
            ];
        }

        $rows = [];
        $errors = [];
        $skusInFile = [];

        foreach ($sheetRows as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $this->normalizeRow($rawRow, $columnMap, $rowNumber);

            if ($this->isBlankRow($row)) {
                continue;
            }

            if ($row['name'] === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Name is required.'];
                continue;
            }

            if ($row['sku'] !== '') {
                $skuKey = Str::lower($row['sku']);
                if (isset($skusInFile[$skuKey])) {
                    $errors[] = ['row' => $rowNumber, 'message' => "Duplicate sku \"{$row['sku']}\" also used on row {$skusInFile[$skuKey]}."];

                    continue;
                }
                $skusInFile[$skuKey] = $rowNumber;
            }

            if ($row['category_code'] === '' && $row['category_name'] === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Category code or category name is required.'];
                continue;
            }

            if ($row['purchase_unit_code'] === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Purchase unit code is required.'];
                continue;
            }

            if ($row['consumption_unit_code'] === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Consumption unit code is required.'];
                continue;
            }

            if ($row['conversion_rate'] <= 0) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Conversion rate must be greater than zero.'];
                continue;
            }

            if ($row['purchase_price'] < 0) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Purchase price cannot be negative.'];
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Import limit reached ('.self::MAX_ROWS.' rows per file).'];

                break;
            }

            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{status: string, restored?: bool, message?: string}
     */
    private function importRow(array $row, int $companyId, int $userId): array
    {
        $categoryId = $this->resolveCategoryId($companyId, $row['category_code'], $row['category_name']);
        if (! $categoryId) {
            $reference = $row['category_code'] !== '' ? $row['category_code'] : $row['category_name'];

            return ['status' => 'error', 'message' => "Ingredient category \"{$reference}\" was not found."];
        }

        $purchaseUnit = $this->resolveUnit($companyId, $row['purchase_unit_code']);
        if (! $purchaseUnit) {
            return ['status' => 'error', 'message' => "Purchase unit \"{$row['purchase_unit_code']}\" was not found."];
        }

        $consumptionUnit = $this->resolveUnit($companyId, $row['consumption_unit_code']);
        if (! $consumptionUnit) {
            return ['status' => 'error', 'message' => "Consumption unit \"{$row['consumption_unit_code']}\" was not found."];
        }

        $existing = null;
        if ($row['sku'] !== '') {
            $existing = Ingredient::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(sku) = ?', [Str::lower($row['sku'])])
                ->first();
        }

        $payload = [
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'name' => $row['name'],
            'purchase_unit_id' => $purchaseUnit->id,
            'consumption_unit_id' => $consumptionUnit->id,
            'conversion_rate' => $row['conversion_rate'],
            'purchase_price' => $row['purchase_price'],
            'base_unit_id' => (string) $consumptionUnit->id,
            'cost_per_unit' => Ingredient::calculateCostPerUnit($row['purchase_price'], $row['conversion_rate']),
            'min_stock_level' => $row['min_stock_level'],
            'max_stock_level' => $row['max_stock_level'],
            'track_stock' => $row['track_stock'],
            'is_active' => $row['is_active'],
            'description' => $row['description'] !== '' ? $row['description'] : null,
        ];

        if ($existing) {
            if ($row['sku'] !== '') {
                $payload['sku'] = $row['sku'];
            }

            $wasTrashed = $existing->trashed();
            if ($wasTrashed) {
                $existing->restore();
            }

            $existing->update($payload);

            return [
                'status' => 'updated',
                'restored' => $wasTrashed,
            ];
        }

        $payload['sku'] = IngredientSku::resolve($companyId, $row['sku']);
        $payload['created_by'] = $userId;

        Ingredient::withoutGlobalScopes()->create($payload);

        return ['status' => 'created'];
    }

    private function resolveCategoryId(int $companyId, string $code, string $name): ?int
    {
        foreach (IngredientImportReferences::codeCandidates($code) as $candidate) {
            $category = IngredientCategory::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [Str::lower($candidate)])
                ->first();

            if ($category) {
                IngredientImportReferences::restoreIfTrashed($category);

                return (int) $category->id;
            }
        }

        if ($name !== '') {
            $category = IngredientCategory::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                ->first();

            if ($category) {
                IngredientImportReferences::restoreIfTrashed($category);

                return (int) $category->id;
            }
        }

        return null;
    }

    private function resolveUnit(int $companyId, string $reference): ?IngredientUnit
    {
        $reference = IngredientImportReferences::normalizeCode($reference);
        if ($reference === '') {
            return null;
        }

        foreach (IngredientImportReferences::codeCandidates($reference) as $candidate) {
            $byCode = IngredientUnit::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [Str::lower($candidate)])
                ->first();

            if ($byCode) {
                IngredientImportReferences::restoreIfTrashed($byCode);

                return $byCode;
            }
        }

        $byName = IngredientUnit::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($reference)])
            ->first();

        if ($byName) {
            IngredientImportReferences::restoreIfTrashed($byName);

            return $byName;
        }

        return null;
    }

    /**
     * @param  list<mixed>  $headerRow
     * @return array<string, int>
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            if ($normalized === '') {
                continue;
            }

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<mixed>  $rawRow
     * @param  array<string, int>  $columnMap
     * @return array<string, mixed>
     */
    private function normalizeRow(array $rawRow, array $columnMap, int $rowNumber): array
    {
        return [
            '_row' => $rowNumber,
            'sku' => $this->cellValue($rawRow, $columnMap, 'sku'),
            'name' => $this->cellValue($rawRow, $columnMap, 'name'),
            'category_code' => IngredientImportReferences::normalizeCode($this->cellValue($rawRow, $columnMap, 'category_code')),
            'category_name' => $this->cellValue($rawRow, $columnMap, 'category_name'),
            'purchase_unit_code' => IngredientImportReferences::normalizeCode($this->cellValue($rawRow, $columnMap, 'purchase_unit_code')),
            'consumption_unit_code' => IngredientImportReferences::normalizeCode($this->cellValue($rawRow, $columnMap, 'consumption_unit_code')),
            'conversion_rate' => $this->parseFloat($this->cellValue($rawRow, $columnMap, 'conversion_rate')),
            'purchase_price' => $this->parseFloat($this->cellValue($rawRow, $columnMap, 'purchase_price')),
            'min_stock_level' => $this->parseFloat($this->cellValue($rawRow, $columnMap, 'min_stock_level'), 0),
            'max_stock_level' => $this->parseNullableFloat($this->cellValue($rawRow, $columnMap, 'max_stock_level')),
            'track_stock' => $this->parseTrackStock($this->cellValue($rawRow, $columnMap, 'track_stock')),
            'is_active' => $this->parseBoolean($this->cellValue($rawRow, $columnMap, 'is_active'), true),
            'description' => $this->cellValue($rawRow, $columnMap, 'description'),
        ];
    }

    /**
     * @param  list<mixed>  $rawRow
     * @param  array<string, int>  $columnMap
     */
    private function cellValue(array $rawRow, array $columnMap, string $field): string
    {
        if (! isset($columnMap[$field])) {
            return '';
        }

        $value = $rawRow[$columnMap[$field]] ?? '';

        return trim((string) $value);
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = ltrim($header, "\xEF\xBB\xBF");
        $header = Str::lower($header);
        $header = preg_replace('/[\s_\-]+/', ' ', $header) ?? $header;

        return trim($header);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        return $row['sku'] === ''
            && $row['name'] === ''
            && $row['category_code'] === ''
            && $row['category_name'] === ''
            && $row['purchase_unit_code'] === ''
            && $row['consumption_unit_code'] === ''
            && $row['conversion_rate'] === 0.0
            && $row['purchase_price'] === 0.0
            && $row['min_stock_level'] === 0.0
            && $row['max_stock_level'] === null
            && $row['track_stock'] === 'yes'
            && $row['is_active'] === true
            && $row['description'] === '';
    }

    private function parseFloat(string $value, ?float $default = null): float
    {
        if ($value === '') {
            return $default ?? 0.0;
        }

        if (! is_numeric($value)) {
            return $default ?? 0.0;
        }

        return (float) $value;
    }

    private function parseNullableFloat(string $value): ?float
    {
        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function parseTrackStock(string $value): string
    {
        if ($value === '') {
            return 'yes';
        }

        $normalized = Str::lower($value);

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'inactive', 'disabled'], true)) {
            return 'no';
        }

        return 'yes';
    }

    private function parseBoolean(string $value, bool $default): bool
    {
        if ($value === '') {
            return $default;
        }

        $normalized = Str::lower($value);

        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'active', 'enabled'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'inactive', 'disabled'], true)) {
            return false;
        }

        return $default;
    }

    /** @return list<string> */
    public static function expectedHeaders(): array
    {
        return IngredientImportSampleExport::HEADERS;
    }
}
