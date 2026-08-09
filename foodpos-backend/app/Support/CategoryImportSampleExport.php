<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryImportSampleExport
{
    /** @var list<string> */
    public const HEADERS = [
        'code',
        'name',
        'parent_code',
        'description',
        'sort_order',
        'is_active',
    ];

    /** @var list<array<string, mixed>> */
    public const SAMPLE_ROWS = [
        [
            'code' => 'C01',
            'name' => 'Burgers',
            'parent_code' => '',
            'description' => 'Main burger menu',
            'sort_order' => 1,
            'is_active' => 'yes',
        ],
        [
            'code' => 'C02',
            'name' => 'Beverages',
            'parent_code' => '',
            'description' => 'Drinks and refreshments',
            'sort_order' => 2,
            'is_active' => 'yes',
        ],
        [
            'code' => 'C03',
            'name' => 'Soft Drinks',
            'parent_code' => 'C02',
            'description' => 'Cola, juice, and similar drinks',
            'sort_order' => 1,
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
                    $row['parent_code'],
                    $row['description'],
                    $row['sort_order'],
                    $row['is_active'],
                ]);
            }

            fclose($handle);
        }, 'category-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadXlsx(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Categories');

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
            $sheet->setCellValue([3, $line], $row['parent_code']);
            $sheet->setCellValue([4, $line], $row['description']);
            $sheet->setCellValue([5, $line], $row['sort_order']);
            $sheet->setCellValue([6, $line], $row['is_active']);
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'category-import-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
