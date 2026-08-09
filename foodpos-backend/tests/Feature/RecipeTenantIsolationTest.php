<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeImportService;
use App\Services\TenantRoleBootstrapService;
use App\Support\IngredientQuantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class RecipeTenantIsolationTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private Company $otherCompany;

    private User $otherCompanyAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();

        $this->otherCompany = Company::create([
            'name' => 'Other Cafe',
            'slug' => 'other-cafe-'.Str::random(8),
            'email' => 'other-'.Str::random(8).'@example.com',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $otherBranch = \App\Models\Branch::withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Branch',
            'code' => 'OB01',
            'timezone' => 'Asia/Karachi',
            'status' => 'active',
        ]);

        $this->otherCompanyAdmin = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'branch_id' => $otherBranch->id,
            'type' => 'company_admin',
            'status' => 'active',
            'can_login' => true,
        ]);

        app(TenantRoleBootstrapService::class)->bootstrapNewCompany(
            $this->otherCompany,
            $this->otherCompanyAdmin
        );
    }

    public function test_import_does_not_resolve_other_tenant_ingredient_with_same_sku(): void
    {
        Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Secret Spice',
            'sku' => 'SPICE01',
            'base_unit_id' => 'g',
            'is_active' => true,
        ]);

        $path = $this->buildWorkbook([
            ['R50', 'Tenant A Recipe', 'Test', 'yes', 'SPICE01', '', 10, 'g', 0, ''],
        ]);

        $file = new UploadedFile($path, 'recipes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $result = app(RecipeImportService::class)->import($file, $this->tenantCompany->id);

        $this->assertSame(0, $result['created']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('not found', strtolower($result['errors'][0]['message']));

        $this->assertNull(
            Recipe::withoutGlobalScopes()
                ->where('company_id', $this->tenantCompany->id)
                ->where('code', 'R50')
                ->first()
        );
    }

    public function test_store_rejects_other_tenant_ingredient_id(): void
    {
        $this->actingAsCompanyAdmin();

        $otherIngredient = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Other Flour',
            'sku' => 'OF01',
            'base_unit_id' => 'g',
            'is_active' => true,
        ]);

        $response = $this->from(route('recipes.create'))->post(route('recipes.store'), [
            'name' => 'Cross Tenant Recipe',
            'code' => 'R88',
            'is_active' => '1',
            'items' => [
                [
                    'ingredient_id' => $otherIngredient->id,
                    'quantity' => 100,
                    'unit_id' => 'g',
                    'waste_percentage' => 0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.ingredient_id');

        $this->assertNull(
            Recipe::withoutGlobalScopes()
                ->where('company_id', $this->tenantCompany->id)
                ->where('code', 'R88')
                ->first()
        );
    }

    public function test_unit_matching_ignores_foreign_tenant_unit_on_ingredient(): void
    {
        $foreignGram = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'name' => 'Gram',
            'code' => 'C20',
        ]);

        $ingredient = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Local Herb',
            'sku' => 'HERB01',
            'base_unit_id' => (string) $foreignGram->id,
            'consumption_unit_id' => $foreignGram->id,
            'purchase_unit_id' => $foreignGram->id,
            'conversion_rate' => 1,
            'is_active' => true,
        ]);

        $this->assertFalse(IngredientQuantity::isValidRecipeUnit($ingredient, 'C20'));
        $this->assertFalse(IngredientQuantity::isValidRecipeUnit($ingredient, 'g'));
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

        $path = tempnam(sys_get_temp_dir(), 'recipe-tenant-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
