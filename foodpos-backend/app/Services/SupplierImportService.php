<?php

namespace App\Services;

use App\Models\Supplier;
use App\Support\SupplierImportSampleExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SupplierImportService
{
    private const MAX_ROWS = 1000;

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'code' => ['code', 'supplier_code', 'supplier code'],
        'name' => ['name', 'company_name', 'company name', 'supplier_name', 'supplier name', 'supplier'],
        'contact_person' => ['contact_person', 'contact person', 'contact', 'contact name', 'contact_name'],
        'email' => ['email', 'email address', 'email_address'],
        'phone' => ['phone', 'mobile', 'phone number', 'phone_number', 'telephone'],
        'whatsapp' => ['whatsapp', 'whats app', 'wa'],
        'address' => ['address', 'street', 'location'],
        'tax_id' => ['tax_id', 'tax id', 'tax number', 'tax_number', 'vat', 'gst'],
        'balance' => ['balance', 'opening_balance', 'opening balance', 'amount owed'],
        'notes' => ['notes', 'note', 'remarks', 'comment'],
        'status' => ['status', 'active', 'is_active', 'is active'],
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
        $namesInFile = [];
        $phonesInFile = [];
        $emailsInFile = [];

        foreach ($sheetRows as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $this->normalizeRow($rawRow, $columnMap, $rowNumber);

            if ($this->isBlankRow($row)) {
                continue;
            }

            if ($row['name'] === '') {
                $errors[] = ['row' => $rowNumber, 'message' => 'Company name is required.'];
                continue;
            }

            if ($row['email'] !== null && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Invalid email address.'];
                continue;
            }

            if ($row['status'] === false) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Status must be active or inactive.'];
                continue;
            }

            if ($row['balance'] !== '' && ! is_numeric($row['balance'])) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Balance must be a number.'];
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

            $nameKey = strtolower($row['name']);
            if (isset($namesInFile[$nameKey])) {
                $errors[] = ['row' => $rowNumber, 'message' => "Duplicate company name also used on row {$namesInFile[$nameKey]}."];
                continue;
            }
            $namesInFile[$nameKey] = $rowNumber;

            if ($row['phone'] !== null) {
                $phoneKey = Supplier::phoneDigits($row['phone']);
                if ($phoneKey !== '') {
                    if (isset($phonesInFile[$phoneKey])) {
                        $errors[] = ['row' => $rowNumber, 'message' => "Duplicate phone number also used on row {$phonesInFile[$phoneKey]}."];
                        continue;
                    }
                    $phonesInFile[$phoneKey] = $rowNumber;
                }
            }

            if ($row['email'] !== null) {
                if (isset($emailsInFile[$row['email']])) {
                    $errors[] = ['row' => $rowNumber, 'message' => "Duplicate email also used on row {$emailsInFile[$row['email']]}."];
                    continue;
                }
                $emailsInFile[$row['email']] = $rowNumber;
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
        $existing = null;
        if ($row['code'] !== '') {
            $existing = Supplier::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [Str::lower($row['code'])])
                ->first();
        }

        $name = Supplier::normalizeName($row['name']);
        $phone = Supplier::normalizePhone($row['phone']);
        $email = Supplier::normalizeEmail($row['email']);
        $ignoreId = $existing?->id;

        if (Supplier::nameIsTaken($companyId, $name, $ignoreId)) {
            return ['status' => 'error', 'message' => 'This company name is already assigned to another supplier.'];
        }

        if (Supplier::phoneIsTaken($companyId, $phone, $ignoreId)) {
            return ['status' => 'error', 'message' => 'This phone number is already assigned to another supplier.'];
        }

        if (Supplier::emailIsTaken($companyId, $email, $ignoreId)) {
            return ['status' => 'error', 'message' => 'This email is already assigned to another supplier.'];
        }

        $payload = [
            'company_id' => $companyId,
            'name' => $name,
            'contact_person' => $row['contact_person'] !== '' ? $row['contact_person'] : null,
            'email' => $email,
            'phone' => $phone,
            'whatsapp' => Supplier::normalizePhone($row['whatsapp']),
            'address' => $row['address'] !== '' ? $row['address'] : null,
            'tax_id' => $row['tax_id'] !== '' ? $row['tax_id'] : null,
            'status' => $row['status'],
            'notes' => $row['notes'] !== '' ? $row['notes'] : null,
        ];

        if ($existing) {
            if ($row['balance'] !== '') {
                $payload['balance'] = (float) $row['balance'];
            }

            $existing->update($payload);

            return ['status' => 'updated'];
        }

        $payload['code'] = Supplier::resolveCode($companyId, $row['code']);
        $payload['balance'] = $row['balance'] !== '' ? (float) $row['balance'] : 0;

        Supplier::withoutGlobalScopes()->create($payload);

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
     * @return array<string, mixed>
     */
    private function normalizeRow(array $rawRow, array $columnMap, int $rowNumber): array
    {
        return [
            '_row' => $rowNumber,
            'code' => $this->cellValue($rawRow, $columnMap, 'code'),
            'name' => Supplier::normalizeName($this->cellValue($rawRow, $columnMap, 'name')),
            'contact_person' => $this->cellValue($rawRow, $columnMap, 'contact_person'),
            'email' => Supplier::normalizeEmail($this->cellValue($rawRow, $columnMap, 'email')),
            'phone' => Supplier::normalizePhone($this->cellValue($rawRow, $columnMap, 'phone')),
            'whatsapp' => $this->cellValue($rawRow, $columnMap, 'whatsapp'),
            'address' => $this->cellValue($rawRow, $columnMap, 'address'),
            'tax_id' => $this->cellValue($rawRow, $columnMap, 'tax_id'),
            'balance' => $this->cellValue($rawRow, $columnMap, 'balance'),
            'notes' => $this->cellValue($rawRow, $columnMap, 'notes'),
            'status' => $this->parseStatus($this->cellValue($rawRow, $columnMap, 'status')),
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
            && $row['contact_person'] === ''
            && $row['email'] === null
            && $row['phone'] === null
            && $row['whatsapp'] === ''
            && $row['address'] === ''
            && $row['tax_id'] === ''
            && $row['balance'] === ''
            && $row['notes'] === ''
            && $row['status'] === 'active';
    }

    /**
     * @return string|false
     */
    private function parseStatus(string $value): string|false
    {
        if ($value === '') {
            return 'active';
        }

        $normalized = Str::lower($value);

        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'active', 'enabled'], true)) {
            return 'active';
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'inactive', 'disabled'], true)) {
            return 'inactive';
        }

        return false;
    }

    /** @return list<string> */
    public static function expectedHeaders(): array
    {
        return SupplierImportSampleExport::HEADERS;
    }
}
