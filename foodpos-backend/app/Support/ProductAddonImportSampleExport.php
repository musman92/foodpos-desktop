<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductAddonImportSampleExport
{
    /** @var list<string> */
    public const HEADERS = [
        'addon_code',
        'addon_name',
        'price',
        'inventory_type',
        'track_inventory',
        'menu_item_code',
        'ingredient_code',
        'ingredient_quantity',
        'unit',
        'waste_percentage',
    ];

    /** @var list<array<string, mixed>> */
    public const SAMPLE_ROWS = [
        [
            'addon_code' => 'PA01',
            'addon_name' => 'Extra Cheese',
            'price' => 100,
            'inventory_type' => 'recipe',
            'track_inventory' => 'yes',
            'menu_item_code' => '',
            'ingredient_code' => 'ING01',
            'ingredient_quantity' => 30,
            'unit' => 'g',
            'waste_percentage' => 5,
        ],
        [
            'addon_code' => 'PA02',
            'addon_name' => 'Coke Can',
            'price' => 150,
            'inventory_type' => 'single',
            'track_inventory' => 'yes',
            'menu_item_code' => 'MI99',
            'ingredient_code' => '',
            'ingredient_quantity' => '',
            'unit' => '',
            'waste_percentage' => '',
        ],
        [
            'addon_code' => 'PA03',
            'addon_name' => 'Extra Spicy',
            'price' => 0,
            'inventory_type' => 'none',
            'track_inventory' => 'no',
            'menu_item_code' => '',
            'ingredient_code' => '',
            'ingredient_quantity' => '',
            'unit' => '',
            'waste_percentage' => '',
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
                fputcsv($handle, array_map(fn ($header) => $row[$header] ?? '', self::HEADERS));
            }

            fclose($handle);
        }, 'product-addon-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadXlsx(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Product Addons');

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
            foreach (self::HEADERS as $columnIndex => $header) {
                $sheet->setCellValue([$columnIndex + 1, $line], $row[$header] ?? '');
            }
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'product-addon-import-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
