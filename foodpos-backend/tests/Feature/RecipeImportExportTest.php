<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\Recipe;
use App\Services\RecipeImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class RecipeImportExportTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_import_creates_recipe_from_long_format_rows(): void
    {
        $this->actingAsCompanyAdmin();

        Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Beef Patty',
            'sku' => 'BEEF01',
            'base_unit_id' => 'g',
            'is_active' => true,
        ]);
        Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Burger Bun',
            'sku' => 'BUN01',
            'base_unit_id' => 'pcs',
            'is_active' => true,
        ]);

        $path = $this->buildWorkbook([
            ['R10', 'Burger Default', 'Sample', 'yes', 'BEEF01', '', 100, 'g', 5, ''],
            ['R10', 'Burger Default', '', 'yes', 'BUN01', '', 1, 'pcs', 0, ''],
        ]);

        $file = new UploadedFile($path, 'recipes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $result = app(RecipeImportService::class)->import($file, $this->tenantCompany->id);

        $this->assertSame([], $result['errors'], implode('; ', array_column($result['errors'], 'message')));
        $this->assertSame(1, $result['created']);

        $recipe = Recipe::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('code', 'R10')
            ->with('items.ingredient')
            ->first();

        $this->assertNotNull($recipe);
        $this->assertSame('Burger Default', $recipe->name);
        $this->assertCount(2, $recipe->items);
    }

    public function test_import_updates_existing_recipe_by_code(): void
    {
        $this->actingAsCompanyAdmin();

        $ingredient = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Flour',
            'sku' => 'FL01',
            'base_unit_id' => 'g',
            'is_active' => true,
        ]);

        $recipe = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Old Name',
            'code' => 'R20',
            'is_active' => true,
        ]);
        $recipe->items()->create([
            'ingredient_id' => $ingredient->id,
            'quantity' => 10,
            'unit_id' => 'g',
            'waste_percentage' => 0,
        ]);

        $path = $this->buildWorkbook([
            ['R20', 'Updated Dough', 'Fresh', 'yes', 'FL01', '', 250, 'g', 2, ''],
        ]);

        $file = new UploadedFile($path, 'recipes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $result = app(RecipeImportService::class)->import($file, $this->tenantCompany->id);

        $this->assertSame(1, $result['updated']);
        $recipe->refresh()->load('items');
        $this->assertSame('Updated Dough', $recipe->name);
        $this->assertSame(250.0, (float) $recipe->items->first()->quantity);
    }

    public function test_import_accepts_excel_numeric_unit_code(): void
    {
        $this->actingAsCompanyAdmin();

        $gram = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Gram',
            'code' => 'C20',
        ]);

        Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Garlic Fresh',
            'sku' => 'GF01',
            'base_unit_id' => (string) $gram->id,
            'consumption_unit_id' => $gram->id,
            'purchase_unit_id' => $gram->id,
            'conversion_rate' => 1,
            'is_active' => true,
        ]);

        $path = $this->buildWorkbook([
            ['R30', 'Garlic Sauce', 'Sample', 'yes', 'GF01', '', 5, 20, 0, ''],
        ]);

        $file = new UploadedFile($path, 'recipes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $result = app(RecipeImportService::class)->import($file, $this->tenantCompany->id);

        $this->assertSame([], $result['errors'], implode('; ', array_column($result['errors'], 'message')));
        $this->assertSame(1, $result['created']);
    }

    public function test_export_downloads_workbook(): void
    {
        $this->actingAsCompanyAdmin();

        Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Export Me',
            'code' => 'RX1',
            'is_active' => true,
        ]);

        $this->get(route('recipes.export', ['format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    /**
     * @param  list<list<mixed>>  $dataRows
     */
    private function buildWorkbook(array $dataRows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $headers = RecipeImportService::expectedHeaders();

        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }

        foreach ($dataRows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 2], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'recipe-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
