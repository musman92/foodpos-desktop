<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecipeImportSampleExport
{
    /** @var list<string> */
    public const HEADERS = [
        'recipe_code',
        'recipe_name',
        'description',
        'is_active',
        'ingredient_code',
        'ingredient_name',
        'quantity',
        'unit',
        'waste_percentage',
        'notes',
    ];

    /** @var list<array<string, mixed>> */
    public const SAMPLE_ROWS = [
        [
            'recipe_code' => 'R01',
            'recipe_name' => 'Burger — Default',
            'description' => 'Standard burger BOM',
            'is_active' => 'yes',
            'ingredient_code' => 'BEEF01',
            'ingredient_name' => 'Beef Patty',
            'quantity' => 100,
            'unit' => 'g',
            'waste_percentage' => 5,
            'notes' => '',
        ],
        [
            'recipe_code' => 'R01',
            'recipe_name' => 'Burger — Default',
            'description' => '',
            'is_active' => 'yes',
            'ingredient_code' => 'BUN01',
            'ingredient_name' => 'Burger Bun',
            'quantity' => 1,
            'unit' => 'pcs',
            'waste_percentage' => 0,
            'notes' => '',
        ],
        [
            'recipe_code' => 'R02',
            'recipe_name' => 'Burger — Large',
            'description' => 'Larger portion',
            'is_active' => 'yes',
            'ingredient_code' => 'BEEF01',
            'ingredient_name' => 'Beef Patty',
            'quantity' => 150,
            'unit' => 'g',
            'waste_percentage' => 5,
            'notes' => '',
        ],
        [
            'recipe_code' => '',
            'recipe_name' => 'Pizza Dough',
            'description' => 'Leave recipe_code blank to auto-assign',
            'is_active' => 'yes',
            'ingredient_code' => '',
            'ingredient_name' => 'Flour',
            'quantity' => 200,
            'unit' => 'g',
            'waste_percentage' => 0,
            'notes' => 'Resolve by ingredient name if code blank',
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
                $line = [];
                foreach (self::HEADERS as $header) {
                    $line[] = $row[$header] ?? '';
                }
                fputcsv($handle, $line);
            }

            fclose($handle);
        }, 'recipe-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadXlsx(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Recipes');

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
        }, 'recipe-import-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
