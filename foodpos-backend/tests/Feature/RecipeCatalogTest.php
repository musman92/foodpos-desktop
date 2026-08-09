<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Recipe;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class RecipeCatalogTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_admin_can_create_catalog_recipe_and_attach_to_menu_item(): void
    {
        $this->actingAsCompanyAdmin();

        $ingredient = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Flour',
            'sku' => 'FL01',
            'base_unit_id' => 'g',
            'cost_per_unit' => 0.01,
            'is_active' => true,
        ]);

        $response = $this->post(route('recipes.store'), [
            'name' => 'Pizza Base',
            'code' => 'R99',
            'is_active' => '1',
            'items' => [
                [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => 100,
                    'unit_id' => 'g',
                    'waste_percentage' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('recipes.index'));

        $recipe = Recipe::withoutGlobalScopes()
            ->where('company_id', $this->tenantCompany->id)
            ->where('code', 'R99')
            ->with('items')
            ->first();

        $this->assertNotNull($recipe);
        $this->assertCount(1, $recipe->items);

        $category = \App\Models\Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Mains',
            'code' => 'MAINS',
            'slug' => 'mains',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'recipe',
            'name' => 'Margherita',
            'slug' => 'margherita',
            'price' => 10,
            'cost' => 0,
            'sku' => 'MI90',
            'is_available' => true,
            'track_inventory' => true,
        ]);

        $menuItem->syncCatalogRecipes((int) $recipe->id, []);
        $menuItem->refresh();

        $this->assertSame((int) $recipe->id, (int) $menuItem->default_recipe_id);
        $lines = $menuItem->resolveRecipes(null, null);
        $this->assertCount(1, $lines);
        $this->assertSame($ingredient->id, (int) $lines->first()->ingredient_id);
    }

    public function test_variant_option_recipe_is_preferred_over_default(): void
    {
        $this->actingAsCompanyAdmin();

        $ingredientA = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Base Flour',
            'sku' => 'FLA',
            'base_unit_id' => 'g',
            'cost_per_unit' => 0.01,
            'is_active' => true,
        ]);
        $ingredientB = Ingredient::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Extra Flour',
            'sku' => 'FLB',
            'base_unit_id' => 'g',
            'cost_per_unit' => 0.01,
            'is_active' => true,
        ]);

        $default = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Default BOM',
            'code' => 'RD1',
            'is_active' => true,
        ]);
        $default->items()->create([
            'ingredient_id' => $ingredientA->id,
            'quantity' => 50,
            'unit_id' => 'g',
            'waste_percentage' => 0,
        ]);

        $large = Recipe::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Large BOM',
            'code' => 'RL1',
            'is_active' => true,
        ]);
        $large->items()->create([
            'ingredient_id' => $ingredientB->id,
            'quantity' => 120,
            'unit_id' => 'g',
            'waste_percentage' => 0,
        ]);

        $variant = Variant::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Size',
            'code' => 'VSZ',
            'options' => [['name' => 'Large', 'sort_order' => 1]],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $category = \App\Models\Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Pizza',
            'code' => 'PZ',
            'slug' => 'pizza',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'recipe',
            'default_recipe_id' => null,
            'name' => 'Party Pizza',
            'slug' => 'party-pizza',
            'price' => 20,
            'sku' => 'MI91',
            'is_available' => true,
            'track_inventory' => true,
        ]);

        $menuItem->variants()->attach($variant->id, [
            'price' => 0,
            'option_prices' => json_encode(['Large' => 20]),
            'is_default' => true,
        ]);

        $menuItem->syncCatalogRecipes(null, [
            [
                'variant_id' => $variant->id,
                'option_name' => 'Large',
                'recipe_id' => $large->id,
            ],
        ]);

        $menuItem->refresh();
        $resolved = $menuItem->resolveRecipes((int) $variant->id, 'Large');
        $this->assertCount(1, $resolved);
        $this->assertSame($ingredientB->id, (int) $resolved->first()->ingredient_id);

        $missing = $menuItem->resolveRecipes((int) $variant->id, 'Missing');
        $this->assertTrue($missing->isEmpty());
    }
}
