<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MenuItemImportSampleExport
{
    /** @var list<string> */
    public const MENU_ITEM_HEADERS = [
        'menu_item_code',
        'name',
        'category_code',
        'price',
        'type',
        'track_inventory',
        'is_available',
        'description',
        'preparation_time',
        'sort_order',
    ];

    /** @var list<array<string, mixed>> */
    public const MENU_ITEM_ROWS = [
        [
            'menu_item_code' => 'MI01',
            'name' => 'Classic Burger',
            'category_code' => 'CAT01',
            'price' => 800,
            'type' => 'recipe',
            'track_inventory' => 'yes',
            'is_available' => 'yes',
            'description' => 'Beef patty with bun',
            'preparation_time' => 15,
            'sort_order' => 1,
        ],
        [
            'menu_item_code' => 'MI02',
            'name' => 'Mineral Water',
            'category_code' => 'CAT02',
            'price' => 100,
            'type' => 'single',
            'track_inventory' => 'no',
            'is_available' => 'yes',
            'description' => '',
            'preparation_time' => '',
            'sort_order' => 2,
        ],
        [
            'menu_item_code' => 'MI03',
            'name' => 'House Salad',
            'category_code' => 'CAT01',
            'price' => 450,
            'type' => 'recipe',
            'track_inventory' => 'yes',
            'is_available' => 'yes',
            'description' => 'No variants — one catalog recipe',
            'preparation_time' => 10,
            'sort_order' => 3,
        ],
    ];

    /** @var list<string> */
    public const VARIANT_HEADERS = [
        'menu_item_code',
        'variant_code',
        'option_name',
        'option_price',
        'is_default',
    ];

    /** @var list<array<string, mixed>> */
    public const VARIANT_ROWS = [
        [
            'menu_item_code' => 'MI01',
            'variant_code' => 'V01',
            'option_name' => 'Small',
            'option_price' => 800,
            'is_default' => 'yes',
        ],
        [
            'menu_item_code' => 'MI01',
            'variant_code' => 'V01',
            'option_name' => 'Large',
            'option_price' => 1200,
            'is_default' => 'no',
        ],
    ];

    /** @var list<string> */
    public const ADDON_HEADERS = [
        'menu_item_code',
        'addon_code',
    ];

    /** @var list<array<string, mixed>> */
    public const ADDON_ROWS = [
        [
            'menu_item_code' => 'MI01',
            'addon_code' => 'PA01',
        ],
        [
            'menu_item_code' => 'MI01',
            'addon_code' => 'PA02',
        ],
    ];

    /**
     * Link menu items to existing catalog recipes (import recipes first under Menu → Recipes).
     *
     * @var list<string>
     */
    public const RECIPE_HEADERS = [
        'menu_item_code',
        'variant_code',
        'option_name',
        'recipe_code',
    ];

    /** @var list<array<string, mixed>> */
    public const RECIPE_ROWS = [
        [
            'menu_item_code' => 'MI01',
            'variant_code' => 'V01',
            'option_name' => 'Small',
            'recipe_code' => 'R01',
        ],
        [
            'menu_item_code' => '',
            'variant_code' => '',
            'option_name' => 'Large',
            'recipe_code' => 'R02',
        ],
        [
            'menu_item_code' => 'MI03',
            'variant_code' => '',
            'option_name' => '',
            'recipe_code' => 'R03',
        ],
    ];

    public function download(): StreamedResponse
    {
        return self::downloadWorkbook(
            'menu-item-import-sample.xlsx',
            self::MENU_ITEM_ROWS,
            self::VARIANT_ROWS,
            self::ADDON_ROWS,
            self::RECIPE_ROWS,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $menuItemRows
     * @param  list<array<string, mixed>>  $variantRows
     * @param  list<array<string, mixed>>  $addonRows
     * @param  list<array<string, mixed>>  $recipeRows
     */
    public static function downloadWorkbook(
        string $filename,
        array $menuItemRows,
        array $variantRows,
        array $addonRows,
        array $recipeRows,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        $writer = new self;

        $writer->fillSheet(
            $spreadsheet->getActiveSheet(),
            'menu_items',
            self::MENU_ITEM_HEADERS,
            $menuItemRows
        );

        $writer->fillSheet(
            $spreadsheet->createSheet(),
            'variant_prices',
            self::VARIANT_HEADERS,
            $variantRows
        );

        $writer->fillSheet(
            $spreadsheet->createSheet(),
            'addons',
            self::ADDON_HEADERS,
            $addonRows
        );

        $writer->fillSheet(
            $spreadsheet->createSheet(),
            'recipes',
            self::RECIPE_HEADERS,
            $recipeRows
        );

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function fillSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $title, array $headers, array $rows): void
    {
        $sheet->setTitle($title);

        foreach ($headers as $columnIndex => $header) {
            $cell = $sheet->getCell([$columnIndex + 1, 1]);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
        }

        foreach ($rows as $rowIndex => $row) {
            $line = $rowIndex + 2;
            foreach ($headers as $columnIndex => $header) {
                $sheet->setCellValue([$columnIndex + 1, $line], $row[$header] ?? '');
            }
        }

        $lastColumn = chr(ord('A') + max(count($headers) - 1, 0));
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
