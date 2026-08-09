<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VariantImportSampleExport
{
    /** @var list<string> */
    public const HEADERS = [
        'variant_code',
        'variant_name',
        'option_name',
        'option_code',
        'default_price',
        'option_sort_order',
        'description',
        'variant_sort_order',
        'is_active',
    ];

    /** @var list<array<string, mixed>> */
    public const SAMPLE_ROWS = [
        [
            'variant_code' => 'V01',
            'variant_name' => 'Size',
            'option_name' => 'Small',
            'option_code' => 'O01',
            'default_price' => 500,
            'option_sort_order' => 0,
            'description' => 'Pizza and burger sizes',
            'variant_sort_order' => 1,
            'is_active' => 'yes',
        ],
        [
            'variant_code' => 'V01',
            'variant_name' => 'Size',
            'option_name' => 'Medium',
            'option_code' => 'O02',
            'default_price' => 800,
            'option_sort_order' => 1,
            'description' => '',
            'variant_sort_order' => 1,
            'is_active' => 'yes',
        ],
        [
            'variant_code' => 'V01',
            'variant_name' => 'Size',
            'option_name' => 'Large',
            'option_code' => 'O03',
            'default_price' => 1200,
            'option_sort_order' => 2,
            'description' => '',
            'variant_sort_order' => 1,
            'is_active' => 'yes',
        ],
        [
            'variant_code' => 'V02',
            'variant_name' => 'Crust',
            'option_name' => 'Thin',
            'option_code' => '',
            'default_price' => 0,
            'option_sort_order' => 0,
            'description' => 'Pizza crust types',
            'variant_sort_order' => 2,
            'is_active' => 'yes',
        ],
        [
            'variant_code' => '',
            'variant_name' => 'Spice Level',
            'option_name' => 'Mild',
            'option_code' => '',
            'default_price' => '',
            'option_sort_order' => 0,
            'description' => 'Leave variant_code blank to auto-assign V03',
            'variant_sort_order' => 3,
            'is_active' => 'yes',
        ],
        [
            'variant_code' => '',
            'variant_name' => 'Spice Level',
            'option_name' => 'Hot',
            'option_code' => '',
            'default_price' => '',
            'option_sort_order' => 1,
            'description' => '',
            'variant_sort_order' => 3,
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
                    $row['variant_code'],
                    $row['variant_name'],
                    $row['option_name'],
                    $row['option_code'],
                    $row['default_price'],
                    $row['option_sort_order'],
                    $row['description'],
                    $row['variant_sort_order'],
                    $row['is_active'],
                ]);
            }

            fclose($handle);
        }, 'variant-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadXlsx(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Variants');

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

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'variant-import-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
