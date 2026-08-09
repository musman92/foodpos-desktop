<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientImportSampleExport
{
    /** @var list<string> */
    public const HEADERS = [
        'sku',
        'name',
        'category_code',
        'purchase_unit_code',
        'consumption_unit_code',
        'conversion_rate',
        'purchase_price',
        'min_stock_level',
        'description',
        'is_active',
    ];

    /** @var list<array<string, mixed>> */
    public const SAMPLE_ROWS = [
        [
            'sku' => '101',
            'name' => 'Cooking Oil',
            'category_code' => 'C03',
            'purchase_unit_code' => 'C02',
            'consumption_unit_code' => 'C02',
            'conversion_rate' => 1000,
            'purchase_price' => 5000,
            'min_stock_level' => 500,
            'description' => 'Vegetable oil for frying',
            'is_active' => 'yes',
        ],
        [
            'sku' => '',
            'name' => 'All-Purpose Flour',
            'category_code' => 'C01',
            'purchase_unit_code' => 'C01',
            'consumption_unit_code' => 'C01',
            'conversion_rate' => 1,
            'purchase_price' => 1200,
            'min_stock_level' => 10,
            'description' => 'Leave sku blank to auto-assign',
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
                    $row['sku'],
                    $row['name'],
                    $row['category_code'],
                    $row['purchase_unit_code'],
                    $row['consumption_unit_code'],
                    $row['conversion_rate'],
                    $row['purchase_price'],
                    $row['min_stock_level'],
                    $row['description'],
                    $row['is_active'],
                ]);
            }

            fclose($handle);
        }, 'ingredient-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadXlsx(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ingredients');

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
            $sheet->setCellValue([1, $line], $row['sku']);
            $sheet->setCellValue([2, $line], $row['name']);
            $sheet->setCellValue([3, $line], $row['category_code']);
            $sheet->setCellValue([4, $line], $row['purchase_unit_code']);
            $sheet->setCellValue([5, $line], $row['consumption_unit_code']);
            $sheet->setCellValue([6, $line], $row['conversion_rate']);
            $sheet->setCellValue([7, $line], $row['purchase_price']);
            $sheet->setCellValue([8, $line], $row['min_stock_level']);
            $sheet->setCellValue([9, $line], $row['description']);
            $sheet->setCellValue([10, $line], $row['is_active']);
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'ingredient-import-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
