<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Support\IngredientQuantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IngredientQuantityTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Test Cafe',
            'slug' => 'test-cafe-'.Str::random(8),
            'email' => 'cafe-'.Str::random(8).'@example.com',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'status' => 'active',
        ]);
    }

    public function test_recipe_unit_gram_matches_consumption_unit_named_gram(): void
    {
        $ingredient = $this->makeIngredient('Black Paper', '37', 1000);

        $this->assertSame(
            IngredientQuantity::UNIT_CONSUMPTION,
            IngredientQuantity::matchRecipeUnit($ingredient, 'Gram')
        );
        $this->assertSame(2.1, IngredientQuantity::toConsumptionQuantity($ingredient, 2.1, 'Gram'));
    }

    public function test_recipe_in_purchase_unit_uses_ingredient_conversion_rate(): void
    {
        $ingredient = $this->makeIngredient('Black Paper', '37', 1000);

        $this->assertSame(
            IngredientQuantity::UNIT_PURCHASE,
            IngredientQuantity::matchRecipeUnit($ingredient, 'Kilogram')
        );
        $this->assertSame(2000.0, IngredientQuantity::toConsumptionQuantity($ingredient, 2, 'Kilogram'));
    }

    public function test_legacy_abbreviation_g_matches_gram_consumption_unit(): void
    {
        $ingredient = $this->makeIngredient('Garlic Fresh', 'GF01', 1000);

        $this->assertSame(
            IngredientQuantity::UNIT_CONSUMPTION,
            IngredientQuantity::matchRecipeUnit($ingredient, 'g')
        );
        $this->assertSame(50.0, IngredientQuantity::toConsumptionQuantity($ingredient, 50, 'g'));
    }

    public function test_legacy_abbreviation_kg_matches_kilogram_purchase_unit(): void
    {
        $ingredient = $this->makeIngredient('Garlic Fresh', 'GF01', 1000);

        $this->assertSame(
            IngredientQuantity::UNIT_PURCHASE,
            IngredientQuantity::matchRecipeUnit($ingredient, 'kg')
        );
        $this->assertSame(2000.0, IngredientQuantity::toConsumptionQuantity($ingredient, 2, 'kg'));
    }

    public function test_legacy_abbreviation_pcs_matches_piece_unit(): void
    {
        $piece = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Piece',
            'code' => 'C25',
        ]);

        $ingredient = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Noodles',
            'sku' => 'NDL01',
            'base_unit_id' => (string) $piece->id,
            'consumption_unit_id' => $piece->id,
            'purchase_unit_id' => $piece->id,
            'conversion_rate' => 1,
            'purchase_price' => 10,
            'cost_per_unit' => 10,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);

        $this->assertSame(
            IngredientQuantity::UNIT_CONSUMPTION,
            IngredientQuantity::matchRecipeUnit($ingredient, 'pcs')
        );
    }

    public function test_excel_numeric_unit_code_matches_ingredient_unit(): void
    {
        $ingredient = $this->makeIngredient('Garlic Fresh', 'GF01', 1000);

        $this->assertSame(
            IngredientQuantity::UNIT_CONSUMPTION,
            IngredientQuantity::matchRecipeUnit($ingredient, '20')
        );
        $this->assertSame(
            IngredientQuantity::UNIT_CONSUMPTION,
            IngredientQuantity::matchRecipeUnit($ingredient, 'C20 — Gram')
        );
    }

    public function test_unknown_recipe_unit_returns_null(): void
    {
        $ingredient = $this->makeIngredient('Black Paper', '37', 1000);

        $this->assertSame(IngredientQuantity::UNIT_UNKNOWN, IngredientQuantity::matchRecipeUnit($ingredient, 'liter'));
        $this->assertNull(IngredientQuantity::toConsumptionQuantity($ingredient, 1, 'liter'));
    }

    private function makeIngredient(string $name, string $sku, float $conversionRate): Ingredient
    {
        $gram = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Gram',
            'code' => 'C20',
        ]);
        $kg = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Kilogram',
            'code' => 'C21',
        ]);

        return Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'sku' => $sku,
            'base_unit_id' => 'g',
            'consumption_unit_id' => $gram->id,
            'purchase_unit_id' => $kg->id,
            'conversion_rate' => $conversionRate,
            'purchase_price' => 10,
            'cost_per_unit' => 0.01,
            'track_stock' => 'yes',
            'is_active' => true,
        ]);
    }
}
