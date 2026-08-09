<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\CustomerImportSampleExport;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class CustomerImportService
{
    private const MAX_ROWS = 1000;

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'code' => ['code', 'customer_code', 'customer code'],
        'name' => ['name', 'customer_name', 'customer name', 'customer', 'full name', 'full_name'],
        'email' => ['email', 'email address', 'email_address'],
        'phone' => ['phone', 'mobile', 'phone number', 'phone_number', 'contact'],
        'date_of_birth' => ['date_of_birth', 'date of birth', 'dob', 'birthday', 'birth date', 'birth_date'],
        'gender' => ['gender', 'sex'],
        'balance' => ['balance', 'opening_balance', 'opening balance', 'credit balance', 'amount owed'],
        'notes' => ['notes', 'note', 'remarks', 'comment'],
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
        $phonesInFile = [];

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

            if ($row['email'] !== '' && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Invalid email address.'];
                continue;
            }

            if ($row['gender'] !== '' && ! in_array($row['gender'], ['male', 'female', 'other'], true)) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Gender must be male, female, or other.'];
                continue;
            }

            if ($row['date_of_birth'] === false) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Invalid date of birth. Use YYYY-MM-DD format.'];
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

            if ($row['phone'] !== null && $row['phone'] !== '') {
                $phoneKey = Customer::phoneDigits($row['phone']);
                if ($phoneKey !== '') {
                    if (isset($phonesInFile[$phoneKey])) {
                        $errors[] = ['row' => $rowNumber, 'message' => "Duplicate phone number also used on row {$phonesInFile[$phoneKey]}."];
                        continue;
                    }
                    $phonesInFile[$phoneKey] = $rowNumber;
                }
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
            $existing = Customer::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [Str::lower($row['code'])])
                ->first();
        }

        if ($existing?->is_default) {
            return ['status' => 'error', 'message' => 'Default system customers cannot be updated via import.'];
        }

        $phone = Customer::normalizePhone($row['phone']);
        if (Customer::phoneIsTaken($companyId, $phone, $existing?->id)) {
            return ['status' => 'error', 'message' => 'This phone number is already assigned to another customer.'];
        }

        $payload = [
            'company_id' => $companyId,
            'name' => $row['name'],
            'email' => $row['email'] !== '' ? $row['email'] : null,
            'phone' => $phone,
            'date_of_birth' => $row['date_of_birth'],
            'gender' => $row['gender'] !== '' ? $row['gender'] : null,
            'notes' => $row['notes'] !== '' ? $row['notes'] : null,
            'is_active' => $row['is_active'],
        ];

        if ($existing) {
            if ($row['balance'] !== '') {
                $payload['balance'] = (float) $row['balance'];
            }

            $existing->update($payload);

            return ['status' => 'updated'];
        }

        $payload['code'] = Customer::resolveCode($companyId, $row['code']);
        $payload['balance'] = $row['balance'] !== '' ? (float) $row['balance'] : 0;

        Customer::withoutGlobalScopes()->create($payload);

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
        $dobRaw = $this->cellValue($rawRow, $columnMap, 'date_of_birth');

        return [
            '_row' => $rowNumber,
            'code' => $this->cellValue($rawRow, $columnMap, 'code'),
            'name' => $this->cellValue($rawRow, $columnMap, 'name'),
            'email' => Str::lower($this->cellValue($rawRow, $columnMap, 'email')),
            'phone' => Customer::normalizePhone($this->cellValue($rawRow, $columnMap, 'phone')),
            'date_of_birth' => $this->parseDate($dobRaw),
            'gender' => Str::lower($this->cellValue($rawRow, $columnMap, 'gender')),
            'balance' => $this->cellValue($rawRow, $columnMap, 'balance'),
            'notes' => $this->cellValue($rawRow, $columnMap, 'notes'),
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
            && $row['email'] === ''
            && ($row['phone'] === '' || $row['phone'] === null)
            && $row['date_of_birth'] === null
            && $row['gender'] === ''
            && $row['balance'] === ''
            && $row['notes'] === ''
            && $row['is_active'] === true;
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

    /**
     * @return string|null|false
     */
    private function parseDate(string $value): string|null|false
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            } catch (\Throwable) {
                return false;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    public static function expectedHeaders(): array
    {
        return CustomerImportSampleExport::HEADERS;
    }
}
