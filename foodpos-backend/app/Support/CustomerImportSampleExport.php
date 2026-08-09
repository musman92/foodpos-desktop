<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerImportSampleExport
{
    /** @var list<string> */
    public const HEADERS = [
        'code',
        'name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'balance',
        'notes',
        'is_active',
    ];

    /** @var list<array<string, mixed>> */
    public const SAMPLE_ROWS = [
        [
            'code' => 'CU01',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1 555 0100',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'balance' => '0',
            'notes' => 'Regular dine-in customer',
            'is_active' => 'yes',
        ],
        [
            'code' => 'CU02',
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+1 555 0101',
            'date_of_birth' => '',
            'gender' => 'female',
            'balance' => '25.50',
            'notes' => 'Delivery customer',
            'is_active' => 'yes',
        ],
        [
            'code' => '',
            'name' => 'Ahmed Khan',
            'email' => '',
            'phone' => '+1 555 0102',
            'date_of_birth' => '',
            'gender' => '',
            'balance' => '',
            'notes' => '',
            'is_active' => 'yes',
        ],
    ];

    public function download(string $format): StreamedResponse
    {
        return match (strtolower($format)) {
            'csv' => $this->downloadCsv(),
            default => $this->downloadXlsx(),
        };
    }

    private function downloadCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::HEADERS);

            foreach (self::SAMPLE_ROWS as $row) {
                fputcsv($handle, [
                    $row['code'],
                    $row['name'],
                    $row['email'],
                    $row['phone'],
                    $row['date_of_birth'],
                    $row['gender'],
                    $row['balance'],
                    $row['notes'],
                    $row['is_active'],
                ]);
            }

            fclose($handle);
        }, 'customer-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadXlsx(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customers');

        foreach (self::HEADERS as $columnIndex => $header) {
            $cell = $sheet->getCell([$columnIndex + 1, 1]);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
        }

        foreach (self::SAMPLE_ROWS as $rowIndex => $row) {
            $line = $rowIndex + 2;
            $sheet->setCellValue([1, $line], $row['code']);
            $sheet->setCellValue([2, $line], $row['name']);
            $sheet->setCellValue([3, $line], $row['email']);
            $sheet->setCellValue([4, $line], $row['phone']);
            $sheet->setCellValue([5, $line], $row['date_of_birth']);
            $sheet->setCellValue([6, $line], $row['gender']);
            $sheet->setCellValue([7, $line], $row['balance']);
            $sheet->setCellValue([8, $line], $row['notes']);
            $sheet->setCellValue([9, $line], $row['is_active']);
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'customer-import-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
