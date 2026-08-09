<?php

namespace App\Services;

use App\Models\Category;
use App\Support\CategoryImportSampleExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CategoryImportService
{
    private const MAX_ROWS = 1000;

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'code' => ['code', 'category_code', 'category code'],
        'name' => ['name', 'category_name', 'category name', 'category'],
        'parent_code' => ['parent_code', 'parent code', 'parent'],
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
            $pending = $rows;
            $attempts = 0;
            $maxAttempts = max(count($pending) + 1, 2);

            while ($pending !== [] && $attempts < $maxAttempts) {
                $attempts++;
                $nextPending = [];

                foreach ($pending as $row) {
                    $outcome = $this->importRow($row, $companyId);

                    if ($outcome['status'] === 'created') {
                        $result['created']++;
                    } elseif ($outcome['status'] === 'updated') {
                        $result['updated']++;
                    } elseif ($outcome['status'] === 'deferred') {
                        $nextPending[] = $row;
                    } else {
                        $result['skipped']++;
                        $result['errors'][] = [
                            'row' => $row['_row'],
                            'message' => $outcome['message'],
                        ];
                    }
                }

                if (count($nextPending) === count($pending)) {
                    foreach ($nextPending as $row) {
                        $result['skipped']++;
                        $result['errors'][] = [
                            'row' => $row['_row'],
                            'message' => $this->parentErrorMessage($row),
                        ];
                    }

                    break;
                }

                $pending = $nextPending;
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
        } catch (\Throwable $exception) {
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

            if ($row['parent_code'] !== '' && $row['code'] !== '' && Str::lower($row['parent_code']) === Str::lower($row['code'])) {
                $errors[] = ['row' => $rowNumber, 'message' => 'A category cannot be its own parent.'];

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
     * @return array{status: string, message?: string}
     */
    private function importRow(array $row, int $companyId): array
    {
        $parentId = null;
        if ($row['parent_code'] !== '') {
            $parent = Category::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [Str::lower($row['parent_code'])])
                ->first();

            if (! $parent) {
                return ['status' => 'deferred'];
            }

            if ($parent->parent_id !== null) {
                return ['status' => 'error', 'message' => "Parent \"{$row['parent_code']}\" must be a top-level category."];
            }

            $parentId = $parent->id;
        }

        $existing = null;
        if ($row['code'] !== '') {
            $existing = Category::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [Str::lower($row['code'])])
                ->first();
        }

        $payload = [
            'company_id' => $companyId,
            'parent_id' => $parentId,
            'name' => $row['name'],
            'description' => $row['description'] !== '' ? $row['description'] : null,
            'sort_order' => $row['sort_order'],
            'is_active' => $row['is_active'],
        ];

        if ($existing) {
            if ($existing->name !== $row['name']) {
                $payload['slug'] = $this->uniqueSlug($companyId, $row['name'], $existing->id);
            }

            if ($row['code'] !== '') {
                $payload['code'] = $row['code'];
            }

            $existing->update($payload);

            return ['status' => 'updated'];
        }

        $payload['code'] = Category::resolveCode($companyId, $row['code']);
        $payload['slug'] = $this->uniqueSlug($companyId, $row['name']);

        Category::withoutGlobalScopes()->create($payload);

        return ['status' => 'created'];
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
     * @return array{_row: int, code: string, name: string, parent_code: string, description: string, sort_order: int, is_active: bool}
     */
    private function normalizeRow(array $rawRow, array $columnMap, int $rowNumber): array
    {
        return [
            '_row' => $rowNumber,
            'code' => $this->cellValue($rawRow, $columnMap, 'code'),
            'name' => $this->cellValue($rawRow, $columnMap, 'name'),
            'parent_code' => $this->cellValue($rawRow, $columnMap, 'parent_code'),
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
            && $row['parent_code'] === ''
            && $row['description'] === ''
            && $row['sort_order'] === 0
            && $row['is_active'] === true;
    }

    private function parseSortOrder(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        if (! is_numeric($value)) {
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

    private function uniqueSlug(int $companyId, string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (Category::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function parentErrorMessage(array $row): string
    {
        return "Parent code \"{$row['parent_code']}\" was not found. Import parent categories first or check the code.";
    }

    /** @return list<string> */
    public static function expectedHeaders(): array
    {
        return CategoryImportSampleExport::HEADERS;
    }
}
