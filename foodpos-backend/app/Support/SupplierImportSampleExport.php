<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierImportSampleExport
{
    /** @var list<string> */
    public const HEADERS = [
        'code',
        'name',
        'contact_person',
        'email',
        'phone',
        'whatsapp',
        'address',
        'tax_id',
        'balance',
        'notes',
        'status',
    ];

    /** @var list<array<string, mixed>> */
    public const SAMPLE_ROWS = [
        [
            'code' => 'SU01',
            'name' => 'Fresh Foods Wholesale',
            'contact_person' => 'John Smith',
            'email' => 'orders@freshfoods.com',
            'phone' => '+1 555 1000',
            'whatsapp' => '+1 555 1001',
            'address' => '100 Market St, Demo City',
            'tax_id' => 'TAX-10001',
            'balance' => '0',
            'notes' => 'Primary produce vendor',
            'status' => 'active',
        ],
        [
            'code' => 'SU02',
            'name' => 'Metro Packaging Ltd',
            'contact_person' => 'Sarah Lee',
            'email' => 'sales@metropack.com',
            'phone' => '+1 555 1002',
            'whatsapp' => '',
            'address' => '22 Industrial Ave',
            'tax_id' => '',
            'balance' => '150.00',
            'notes' => '',
            'status' => 'active',
        ],
        [
            'code' => '',
            'name' => 'Quick Supply Co',
            'contact_person' => '',
            'email' => '',
            'phone' => '+1 555 1003',
            'whatsapp' => '',
            'address' => '',
            'tax_id' => '',
            'balance' => '',
            'notes' => '',
            'status' => 'active',
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
                    $row['contact_person'],
                    $row['email'],
                    $row['phone'],
                    $row['whatsapp'],
                    $row['address'],
                    $row['tax_id'],
                    $row['balance'],
                    $row['notes'],
                    $row['status'],
                ]);
            }

            fclose($handle);
        }, 'supplier-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadXlsx(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Suppliers');

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
            $sheet->setCellValue([3, $line], $row['contact_person']);
            $sheet->setCellValue([4, $line], $row['email']);
            $sheet->setCellValue([5, $line], $row['phone']);
            $sheet->setCellValue([6, $line], $row['whatsapp']);
            $sheet->setCellValue([7, $line], $row['address']);
            $sheet->setCellValue([8, $line], $row['tax_id']);
            $sheet->setCellValue([9, $line], $row['balance']);
            $sheet->setCellValue([10, $line], $row['notes']);
            $sheet->setCellValue([11, $line], $row['status']);
        }

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'supplier-import-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
