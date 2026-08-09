<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\Variant;
use App\Services\MenuItemImportService;
use App\Support\MenuItemExport;
use App\Support\MenuItemImportSampleExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class MenuItemImportTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_recipe_import_links_catalog_recipe_by_code_without_variants(): void
    {
        $this->actingAsCompanyAdmin();

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Mains',
            'code' => 'MAINS',
            'slug' => 'mains',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $ingredient = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Tomato Sauce',
            'sku' => 'SAUCE01',
            'base_unit_id' => 'g',
            'is_active' => true,
        ]);

        $recipe = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Pasta BOM',
            'code' => 'R10',
            'is_active' => true,
        ]);
        $recipe->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 50,
            'unit_id' => 'g',
            'waste_percentage' => 0,
        ]);

        $path = $this->buildImportWorkbook([
            [
                'menu_item_code' => 'MI01',
                'name' => 'Pasta',
                'category_code' => 'MAINS',
                'price' => 12,
                'type' => 'recipe',
                'track_inventory' => 'no',
                'is_available' => 'yes',
                'description' => '',
                'preparation_time' => '',
                'sort_order' => 1,
            ],
        ], [], [], [
            [
                'menu_item_code' => 'MI01',
                'variant_code' => '',
                'option_name' => '',
                'recipe_code' => 'R10',
            ],
        ]);

        $file = new UploadedFile($path, 'menu-items.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $result = app(MenuItemImportService::class)->import($file, $this->tenantCompany->id);

        $this->assertSame([], $result['errors'], implode('; ', array_column($result['errors'], 'message')));
        $this->assertSame(1, $result['created']);

        $menuItem = MenuItem::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('sku', 'MI01')
            ->with('defaultRecipe')
            ->first();

        $this->assertNotNull($menuItem);
        $this->assertSame((int) $recipe->id, (int) $menuItem->default_recipe_id);
        $this->assertSame('R10', $menuItem->defaultRecipe->code);
    }

    public function test_recipe_import_links_per_option_and_clears_default_when_variants_exist(): void
    {
        $this->actingAsCompanyAdmin();

        Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Pizza',
            'code' => 'PIZZA',
            'slug' => 'pizza',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Variant::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Size',
            'code' => 'V01',
            'options' => [
                ['name' => 'Small', 'sort_order' => 1],
                ['name' => 'Large', 'sort_order' => 2],
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $ingredient = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Flour',
            'sku' => 'ING01',
            'base_unit_id' => 'g',
            'is_active' => true,
        ]);

        $small = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Small pizza BOM',
            'code' => 'R01',
            'is_active' => true,
        ]);
        $small->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
            'unit_id' => 'g',
            'waste_percentage' => 0,
        ]);

        $large = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Large pizza BOM',
            'code' => 'R02',
            'is_active' => true,
        ]);
        $large->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 200,
            'unit_id' => 'g',
            'waste_percentage' => 0,
        ]);

        $path = $this->buildImportWorkbook([
            [
                'menu_item_code' => 'MI19',
                'name' => 'Small Pizza',
                'category_code' => 'PIZZA',
                'price' => 500,
                'type' => 'recipe',
                'track_inventory' => 'no',
                'is_available' => 'yes',
                'description' => '',
                'preparation_time' => '',
                'sort_order' => 1,
            ],
        ], [
            [
                'menu_item_code' => 'MI19',
                'variant_code' => 'V01',
                'option_name' => 'Small',
                'option_price' => 500,
                'is_default' => 'yes',
            ],
            [
                'menu_item_code' => '',
                'variant_code' => '',
                'option_name' => 'Large',
                'option_price' => 700,
                'is_default' => 'no',
            ],
        ], [], [
            [
                'menu_item_code' => 'MI19',
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
        ]);

        $file = new UploadedFile($path, 'menu-items.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $result = app(MenuItemImportService::class)->import($file, $this->tenantCompany->id);

        $this->assertSame([], $result['errors'], implode('; ', array_column($result['errors'], 'message')));
        $this->assertSame(1, $result['created']);

        $menuItem = MenuItem::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('sku', 'MI19')
            ->with(['variantRecipes.recipe', 'defaultRecipe'])
            ->first();

        $this->assertNotNull($menuItem);
        $this->assertNull($menuItem->default_recipe_id);
        $this->assertCount(2, $menuItem->variantRecipes);

        $byOption = $menuItem->variantRecipes->keyBy('option_name');
        $this->assertSame('R01', $byOption['Small']->recipe->code);
        $this->assertSame('R02', $byOption['Large']->recipe->code);
    }

    public function test_legacy_ingredient_recipe_rows_still_import_without_variants(): void
    {
        $this->actingAsCompanyAdmin();

        Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Mains',
            'code' => 'MAINS',
            'slug' => 'mains',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Tomato Sauce',
            'sku' => 'SAUCE01',
            'base_unit_id' => 'g',
            'is_active' => true,
        ]);

        $legacyHeaders = [
            'menu_item_code',
            'variant_code',
            'option_name',
            'ingredient_code',
            'ingredient_name',
            'quantity',
            'unit',
            'waste_percentage',
            'notes',
        ];

        $path = $this->buildImportWorkbookWithCustomRecipeHeaders([
            [
                'menu_item_code' => 'MI01',
                'name' => 'Pasta',
                'category_code' => 'MAINS',
                'price' => 12,
                'type' => 'recipe',
                'track_inventory' => 'no',
                'is_available' => 'yes',
                'description' => '',
                'preparation_time' => '',
                'sort_order' => 1,
            ],
        ], [], [], $legacyHeaders, [
            [
                'menu_item_code' => 'MI01',
                'variant_code' => '',
                'option_name' => '',
                'ingredient_code' => '',
                'ingredient_name' => 'Tomato Sauce',
                'quantity' => 50,
                'unit' => 'g',
                'waste_percentage' => 0,
                'notes' => '',
            ],
        ]);

        $file = new UploadedFile($path, 'menu-items.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $result = app(MenuItemImportService::class)->import($file, $this->tenantCompany->id);

        $this->assertSame([], $result['errors'], implode('; ', array_column($result['errors'], 'message')));

        $menuItem = MenuItem::withoutGlobalScopes()
            ->where('sku', 'MI01')
            ->with('defaultRecipe.items.ingredient')
            ->first();

        $this->assertNotNull($menuItem->defaultRecipe);
        $this->assertSame('Tomato Sauce', $menuItem->defaultRecipe->items->first()->ingredient->name);
    }

    public function test_export_writes_recipe_code_links(): void
    {
        $this->actingAsCompanyAdmin();

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Mains',
            'code' => 'MAINS',
            'slug' => 'mains',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $recipe = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Burger BOM',
            'code' => 'RB01',
            'is_active' => true,
        ]);

        MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Burger',
            'slug' => 'burger',
            'type' => 'recipe',
            'price' => 10,
            'sku' => 'MI50',
            'default_recipe_id' => $recipe->id,
            'is_available' => true,
            'track_inventory' => false,
            'sort_order' => 1,
        ]);

        $response = (new MenuItemExport)->download();
        ob_start();
        $response->sendContent();
        $binary = ob_get_clean();

        $tmp = tempnam(sys_get_temp_dir(), 'export-').'.xlsx';
        file_put_contents($tmp, $binary);

        $spreadsheet = IOFactory::load($tmp);
        $sheet = $spreadsheet->getSheetByName('recipes');
        $this->assertNotNull($sheet);
        $this->assertSame('recipe_code', $sheet->getCell([4, 1])->getValue());
        $this->assertSame('MI50', $sheet->getCell([1, 2])->getValue());
        $this->assertSame('RB01', $sheet->getCell([4, 2])->getValue());

        @unlink($tmp);
    }

    public function test_variant_prices_import_carries_forward_menu_item_and_variant_codes(): void
    {
        $this->actingAsCompanyAdmin();

        Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Pizza',
            'code' => 'PIZZA',
            'slug' => 'pizza',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Variant::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Size',
            'code' => 'V01',
            'options' => [
                ['name' => 'Small', 'sort_order' => 1],
                ['name' => 'Large', 'sort_order' => 2],
            ],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $path = $this->buildImportWorkbook([
            [
                'menu_item_code' => 'MI19',
                'name' => 'Pizza',
                'category_code' => 'PIZZA',
                'price' => 500,
                'type' => 'single',
                'track_inventory' => 'no',
                'is_available' => 'yes',
                'description' => '',
                'preparation_time' => '',
                'sort_order' => 1,
            ],
        ], [
            [
                'menu_item_code' => 'MI19',
                'variant_code' => 'V01',
                'option_name' => 'Small',
                'option_price' => 500,
                'is_default' => 'yes',
            ],
            [
                'menu_item_code' => '',
                'variant_code' => '',
                'option_name' => 'Large',
                'option_price' => 700,
                'is_default' => 'no',
            ],
        ]);

        $file = new UploadedFile($path, 'menu-items.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $result = app(MenuItemImportService::class)->import($file, $this->tenantCompany->id);

        $this->assertSame([], $result['errors'], implode('; ', array_column($result['errors'], 'message')));
        $this->assertSame(1, $result['created']);

        $menuItem = MenuItem::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('sku', 'MI19')
            ->with('variants')
            ->first();

        $this->assertNotNull($menuItem);
        $this->assertCount(1, $menuItem->variants);

        $optionPrices = json_decode($menuItem->variants->first()->pivot->option_prices, true);
        $this->assertEquals(500, $optionPrices['Small']);
        $this->assertEquals(700, $optionPrices['Large']);
    }

    /**
     * @param  list<array<string, mixed>>  $menuItemRows
     * @param  list<array<string, mixed>>  $variantRows
     * @param  list<array<string, mixed>>  $addonRows
     * @param  list<array<string, mixed>>  $recipeRows
     */
    private function buildImportWorkbook(array $menuItemRows, array $variantRows = [], array $addonRows = [], array $recipeRows = []): string
    {
        return $this->buildImportWorkbookWithCustomRecipeHeaders(
            $menuItemRows,
            $variantRows,
            $addonRows,
            MenuItemImportSampleExport::RECIPE_HEADERS,
            $recipeRows
        );
    }

    /**
     * @param  list<array<string, mixed>>  $menuItemRows
     * @param  list<array<string, mixed>>  $variantRows
     * @param  list<array<string, mixed>>  $addonRows
     * @param  list<string>  $recipeHeaders
     * @param  list<array<string, mixed>>  $recipeRows
     */
    private function buildImportWorkbookWithCustomRecipeHeaders(
        array $menuItemRows,
        array $variantRows,
        array $addonRows,
        array $recipeHeaders,
        array $recipeRows
    ): string {
        $spreadsheet = new Spreadsheet;
        $this->fillImportSheet($spreadsheet->getActiveSheet(), 'menu_items', MenuItemImportSampleExport::MENU_ITEM_HEADERS, $menuItemRows);
        $this->fillImportSheet($spreadsheet->createSheet(), 'variant_prices', MenuItemImportSampleExport::VARIANT_HEADERS, $variantRows);
        $this->fillImportSheet($spreadsheet->createSheet(), 'addons', MenuItemImportSampleExport::ADDON_HEADERS, $addonRows);
        $this->fillImportSheet($spreadsheet->createSheet(), 'recipes', $recipeHeaders, $recipeRows);
        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'menu-item-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function fillImportSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $title, array $headers, array $rows): void
    {
        $sheet->setTitle($title);

        foreach ($headers as $columnIndex => $header) {
            $sheet->setCellValue([$columnIndex + 1, 1], $header);
        }

        foreach ($rows as $rowIndex => $row) {
            $line = $rowIndex + 2;
            foreach ($headers as $columnIndex => $header) {
                $sheet->setCellValue([$columnIndex + 1, $line], $row[$header] ?? '');
            }
        }
    }
}
