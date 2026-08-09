<?php

namespace App\Services;

use App\Models\Variant;
use App\Support\VariantImportSampleExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VariantImportService
{
    private const MAX_ROWS = 1000;

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'variant_code' => ['variant_code', 'variant code', 'v code', 'code'],
        'variant_name' => ['variant_name', 'variant name', 'name'],
        'option_name' => ['option_name', 'option name', 'option'],
        'option_code' => ['option_code', 'option code', 'o code'],
        'default_price' => ['default_price', 'default price', 'option_price', 'option price', 'price'],
        'option_sort_order' => ['option_sort_order', 'option sort order', 'option order', 'option_sort'],
        'description' => ['description', 'desc', 'variant_description', 'variant description'],
        'variant_sort_order' => ['variant_sort_order', 'variant sort order', 'sort_order', 'sort order', 'sort'],
        'is_active' => ['is_active', 'active', 'status', 'enabled'],
    ];

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, int $companyId): array
    {
        $parsed = $this->parseFile($file);

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => $parsed['errors'],
        ];

        if ($parsed['rows'] === []) {
            if ($result['errors'] === []) {
                $result['errors'][] = ['row' => 1, 'message' => 'The file has no data rows to import.'];
            }

            return $result;
        }

        $grouped = $this->groupRows($parsed['rows']);
        $result['errors'] = array_merge($result['errors'], $grouped['errors']);

        if ($grouped['groups'] === []) {
            return $result;
        }

        DB::transaction(function () use ($grouped, $companyId, &$result) {
            foreach ($grouped['groups'] as $group) {
                $outcome = $this->importGroup($group, $companyId);

                if ($outcome['status'] === 'created') {
                    $result['created']++;
                } elseif ($outcome['status'] === 'updated') {
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                    $result['errors'][] = [
                        'row' => $group['first_row'],
                        'message' => $outcome['message'] ?? 'Could not import variant group.',
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

        if (! isset($columnMap['option_name'])) {
            return [
                'rows' => [],
                'errors' => [['row' => 1, 'message' => 'Missing required "option_name" column. Download the sample file for the expected format.']],
            ];
        }

        $rows = [];
        $errors = [];

        foreach ($sheetRows as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $this->normalizeRow($rawRow, $columnMap, $rowNumber);

            if ($this->isBlankRow($row)) {
                continue;
            }

            if ($row['option_name'] === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Option name is required on every row.'];
                continue;
            }

            if ($row['variant_code'] === '' && $row['variant_name'] === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Provide variant_code or variant_name (or both).'];
                continue;
            }

            if ($row['default_price'] === false) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Default price must be a number.'];
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
     * @param  list<array<string, mixed>>  $rows
     * @return array{groups: list<array<string, mixed>>, errors: list<array{row: int, message: string}>}
     */
    private function groupRows(array $rows): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        $errors = [];

        foreach ($rows as $row) {
            $key = $this->groupKey($row);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'first_row' => $row['_row'],
                    'variant_code' => $row['variant_code'],
                    'variant_name' => $row['variant_name'],
                    'description' => $row['description'],
                    'variant_sort_order' => $row['variant_sort_order'],
                    'is_active' => $row['is_active'],
                    'option_names' => [],
                    'options' => [],
                ];
            }

            $group = &$groups[$key];

            if ($row['variant_code'] !== '' && $group['variant_code'] === '') {
                $group['variant_code'] = $row['variant_code'];
            }

            if ($row['variant_name'] !== '') {
                if ($group['variant_name'] !== '' && strcasecmp($group['variant_name'], $row['variant_name']) !== 0) {
                    $errors[] = [
                        'row' => $row['_row'],
                        'message' => "Variant name \"{$row['variant_name']}\" conflicts with \"{$group['variant_name']}\" for the same variant code/group (see row {$group['first_row']}).",
                    ];
                    continue;
                }
                $group['variant_name'] = $row['variant_name'];
            }

            if ($row['variant_code'] !== '' && $group['variant_code'] !== '' && strcasecmp($group['variant_code'], $row['variant_code']) !== 0) {
                $errors[] = [
                    'row' => $row['_row'],
                    'message' => "Variant code \"{$row['variant_code']}\" conflicts with \"{$group['variant_code']}\" in the same group (see row {$group['first_row']}).",
                ];
                continue;
            }

            if ($row['description'] !== '') {
                $group['description'] = $row['description'];
            }

            if ($row['variant_sort_order'] !== null) {
                $group['variant_sort_order'] = $row['variant_sort_order'];
            }

            $group['is_active'] = $row['is_active'];

            $optionKey = Str::lower($row['option_name']);
            if (isset($group['option_names'][$optionKey])) {
                $errors[] = [
                    'row' => $row['_row'],
                    'message' => "Duplicate option \"{$row['option_name']}\" for this variant (also on row {$group['option_names'][$optionKey]}).",
                ];
                continue;
            }

            $group['option_names'][$optionKey] = $row['_row'];
            $group['options'][] = [
                'name' => $row['option_name'],
                'code' => $row['option_code'],
                'sort_order' => $row['option_sort_order'] ?? 0,
                'price' => $row['default_price'],
            ];
        }

        return [
            'groups' => array_values($groups),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array{status: string, message?: string}
     */
    private function importGroup(array $group, int $companyId): array
    {
        if ($group['variant_name'] === '') {
            return ['status' => 'error', 'message' => 'Variant name is required for each variant group (first row of the group).'];
        }

        if ($group['options'] === []) {
            return ['status' => 'error', 'message' => 'Each variant must have at least one option row.'];
        }

        $options = Variant::resolveOptions($group['options']);
        if ($options === null || $options === []) {
            return ['status' => 'error', 'message' => 'Could not resolve variant options.'];
        }

        $existing = null;

        if ($group['variant_code'] !== '') {
            $existing = Variant::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [Str::lower($group['variant_code'])])
                ->first();
        } else {
            $existing = Variant::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [Str::lower($group['variant_name'])])
                ->first();
        }

        $payload = [
            'company_id' => $companyId,
            'name' => $group['variant_name'],
            'description' => $group['description'] !== '' ? $group['description'] : null,
            'options' => $options,
            'sort_order' => $group['variant_sort_order'] ?? 0,
            'is_active' => (bool) $group['is_active'],
        ];

        if ($existing) {
            if ($group['variant_code'] !== '') {
                $payload['code'] = $group['variant_code'];
            }

            $existing->update($payload);

            return ['status' => 'updated'];
        }

        $payload['code'] = Variant::resolveCode($companyId, $group['variant_code']);

        try {
            Variant::withoutGlobalScopes()->create($payload);
        } catch (\Throwable $exception) {
            return ['status' => 'error', 'message' => 'Could not create variant: '.$exception->getMessage()];
        }

        return ['status' => 'created'];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function groupKey(array $row): string
    {
        if ($row['variant_code'] !== '') {
            return 'code:'.Str::lower($row['variant_code']);
        }

        return 'name:'.Str::lower($row['variant_name']);
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
        $sortOrderRaw = $this->cellValue($rawRow, $columnMap, 'variant_sort_order');
        $optionSortRaw = $this->cellValue($rawRow, $columnMap, 'option_sort_order');
        $priceRaw = $this->cellValue($rawRow, $columnMap, 'default_price');

        return [
            '_row' => $rowNumber,
            'variant_code' => $this->cellValue($rawRow, $columnMap, 'variant_code'),
            'variant_name' => $this->cellValue($rawRow, $columnMap, 'variant_name'),
            'option_name' => $this->cellValue($rawRow, $columnMap, 'option_name'),
            'option_code' => $this->cellValue($rawRow, $columnMap, 'option_code'),
            'default_price' => $this->parseOptionalPrice($priceRaw),
            'option_sort_order' => $optionSortRaw !== '' ? $this->parseSortOrder($optionSortRaw) : null,
            'description' => $this->cellValue($rawRow, $columnMap, 'description'),
            'variant_sort_order' => $sortOrderRaw !== '' ? $this->parseSortOrder($sortOrderRaw) : null,
            'is_active' => $this->parseBoolean($this->cellValue($rawRow, $columnMap, 'is_active'), true),
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
        return $row['variant_code'] === ''
            && $row['variant_name'] === ''
            && $row['option_name'] === ''
            && $row['option_code'] === ''
            && ($row['default_price'] === null || $row['default_price'] === '')
            && $row['description'] === '';
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

    private function parseSortOrder(string $value): int
    {
        if ($value === '' || ! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    /**
     * @return null|string|false null = blank, false = invalid
     */
    private function parseOptionalPrice(string $value): null|string|false
    {
        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return false;
        }

        return (string) max(0, (float) $value);
    }

    /** @return list<string> */
    public static function expectedHeaders(): array
    {
        return VariantImportSampleExport::HEADERS;
    }
}
