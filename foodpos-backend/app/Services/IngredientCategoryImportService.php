<?php

namespace App\Services;

use App\Models\IngredientCategory;
use App\Support\IngredientCategoryImportSampleExport;
use App\Support\IngredientImportReferences;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class IngredientCategoryImportService
{
    private const MAX_ROWS = 1000;

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'code' => ['code', 'category_code', 'category code'],
        'name' => ['name', 'category_name', 'category name', 'category'],
        'description' => ['description', 'desc'],
        'sort_order' => ['sort_order', 'sort order', 'sort', 'order'],
        'is_active' => ['is_active', 'active', 'status', 'enabled'],
    ];

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function import(UploadedFile $file, int $companyId): array
    {
        $parsed = $this->parseFile($file);
        $rows = $parsed['rows'];

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => $parsed['errors'],
        ];

        if ($rows === []) {
            if ($result['errors'] === []) {
                $result['errors'][] = ['row' => 1, 'message' => 'The file has no data rows to import.'];
            }

            return $result;
        }

        DB::transaction(function () use ($rows, $companyId, &$result) {
            foreach ($rows as $row) {
                $outcome = $this->importRow($row, $companyId);

                if ($outcome['status'] === 'created') {
                    $result['created']++;
                } elseif ($outcome['status'] === 'updated') {
                    $result['updated']++;
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
        $codesInFile = [];

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

            if ($row['code'] !== '') {
                $codeKey = Str::lower($row['code']);
                if (isset($codesInFile[$codeKey])) {
                    $errors[] = ['row' => $rowNumber, 'message' => "Duplicate code \"{$row['code']}\" also used on row {$codesInFile[$codeKey]}."];

                    continue;
                }
                $codesInFile[$codeKey] = $rowNumber;
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
     * @return array{status: string, message?: string}
     */
    private function importRow(array $row, int $companyId): array
    {
        $existing = $this->findExistingCategory($companyId, $row['code']);

        $payload = [
            'company_id' => $companyId,
            'name' => $row['name'],
            'description' => $row['description'] !== '' ? $row['description'] : null,
            'sort_order' => $row['sort_order'],
            'is_active' => $row['is_active'],
        ];

        if ($existing) {
            IngredientImportReferences::restoreIfTrashed($existing);

            if ($row['code'] !== '' && ! IngredientImportReferences::codesReferToSame($existing->code, $row['code'])) {
                $payload['code'] = $row['code'];
            }

            $existing->update($payload);

            return ['status' => 'updated'];
        }

        $payload['code'] = IngredientCategory::resolveCode($companyId, $row['code']);

        IngredientCategory::withoutGlobalScopes()->create($payload);

        return ['status' => 'created'];
    }

    private function findExistingCategory(int $companyId, string $code): ?IngredientCategory
    {
        foreach (IngredientImportReferences::codeCandidates($code) as $candidate) {
            $existing = IngredientCategory::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [Str::lower($candidate)])
                ->first();

            if ($existing) {
                return $existing;
            }
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
     * @return array{_row: int, code: string, name: string, description: string, sort_order: int, is_active: bool}
     */
    private function normalizeRow(array $rawRow, array $columnMap, int $rowNumber): array
    {
        return [
            '_row' => $rowNumber,
            'code' => IngredientImportReferences::normalizeCode($this->cellValue($rawRow, $columnMap, 'code')),
            'name' => $this->cellValue($rawRow, $columnMap, 'name'),
            'description' => $this->cellValue($rawRow, $columnMap, 'description'),
            'sort_order' => $this->parseSortOrder($this->cellValue($rawRow, $columnMap, 'sort_order')),
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
        return $row['code'] === ''
            && $row['name'] === ''
            && $row['description'] === ''
            && $row['sort_order'] === 0
            && $row['is_active'] === true;
    }

    private function parseSortOrder(string $value): int
    {
        if ($value === '' || ! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) $value);
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
        return IngredientCategoryImportSampleExport::HEADERS;
    }
}
