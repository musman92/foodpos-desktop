<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\UnitOfMeasure;
use App\Support\MenuItemCatalogRecipeBuilder;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = \App\Models\Company::first();
        
        if (!$company) {
            $this->command->error('Please run CompanySeeder first!');
            return;
        }

        // Create Units of Measure
        $kg = UnitOfMeasure::create([
            'company_id' => $company->id,
            'name' => 'Kilogram',
            'abbreviation' => 'kg',
            'type' => 'weight',
            'is_base_unit' => true,
        ]);

        $g = UnitOfMeasure::create([
            'company_id' => $company->id,
            'name' => 'Gram',
            'abbreviation' => 'g',
            'type' => 'weight',
            'is_base_unit' => false,
        ]);

        $liter = UnitOfMeasure::create([
            'company_id' => $company->id,
            'name' => 'Liter',
            'abbreviation' => 'L',
            'type' => 'volume',
            'is_base_unit' => true,
        ]);

        $piece = UnitOfMeasure::create([
            'company_id' => $company->id,
            'name' => 'Piece',
            'abbreviation' => 'pcs',
            'type' => 'count',
            'is_base_unit' => true,
        ]);

        // Create Ingredients
        $cheese = Ingredient::create([
            'company_id' => $company->id,
            'name' => 'Cheddar Cheese',
            'sku' => 'ING-CHEESE-001',
            'base_unit_id' => $g->id,
            'cost_per_unit' => 0.05, // $0.05 per gram
            'min_stock_level' => 5000, // 5kg minimum
            'max_stock_level' => 20000, // 20kg maximum
            'track_stock' => 'yes',
        ]);

        $beef = Ingredient::create([
            'company_id' => $company->id,
            'name' => 'Ground Beef',
            'sku' => 'ING-BEEF-001',
            'base_unit_id' => $g->id,
            'cost_per_unit' => 0.08, // $0.08 per gram
            'min_stock_level' => 10000, // 10kg minimum
            'max_stock_level' => 50000, // 50kg maximum
            'track_stock' => 'yes',
        ]);

        $bun = Ingredient::create([
            'company_id' => $company->id,
            'name' => 'Burger Bun',
            'sku' => 'ING-BUN-001',
            'base_unit_id' => $piece->id,
            'cost_per_unit' => 0.50, // $0.50 per bun
            'min_stock_level' => 100,
            'max_stock_level' => 500,
            'track_stock' => 'yes',
        ]);

        $lettuce = Ingredient::create([
            'company_id' => $company->id,
            'name' => 'Lettuce',
            'sku' => 'ING-LETTUCE-001',
            'base_unit_id' => $g->id,
            'cost_per_unit' => 0.02, // $0.02 per gram
            'min_stock_level' => 2000, // 2kg minimum
            'max_stock_level' => 10000, // 10kg maximum
            'track_stock' => 'yes',
        ]);

        $tomato = Ingredient::create([
            'company_id' => $company->id,
            'name' => 'Tomato',
            'sku' => 'ING-TOMATO-001',
            'base_unit_id' => $g->id,
            'cost_per_unit' => 0.03, // $0.03 per gram
            'min_stock_level' => 3000, // 3kg minimum
            'max_stock_level' => 15000, // 15kg maximum
            'track_stock' => 'yes',
        ]);

        // Create Categories (use withoutTenantScope since we're in seeder)
        $burgers = Category::withoutTenantScope()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'burgers'],
            [
                'name' => 'Burgers',
                'code' => 'C01',
                'description' => 'Delicious burgers made fresh',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $beverages = Category::withoutTenantScope()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'beverages'],
            [
                'name' => 'Beverages',
                'code' => 'C02',
                'description' => 'Refreshing drinks',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        // Create Menu Items
        $classicBurger = MenuItem::withoutTenantScope()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'classic-burger'],
            [
                'category_id' => $burgers->id,
                'name' => 'Classic Burger',
                'description' => 'Juicy beef patty with cheese, lettuce, and tomato',
                'price' => 12.99,
                'cost' => 0, // Will be calculated from recipe
                'sku' => 'MENU-BURGER-001',
                'is_available' => true,
                'track_inventory' => true,
                'preparation_time' => 15,
                'sort_order' => 1,
            ]
        );

        // Create Recipe for Classic Burger (catalog)
        \App\Support\MenuItemCatalogRecipeBuilder::setDefaultFromLines(
            $classicBurger,
            'Classic Burger — Default',
            [
                ['ingredient_id' => $cheese->id, 'quantity' => 50, 'unit_id' => $g->id, 'waste_percentage' => 5],
                ['ingredient_id' => $beef->id, 'quantity' => 100, 'unit_id' => $g->id, 'waste_percentage' => 10],
                ['ingredient_id' => $bun->id, 'quantity' => 1, 'unit_id' => $piece->id, 'waste_percentage' => 0],
                ['ingredient_id' => $lettuce->id, 'quantity' => 20, 'unit_id' => $g->id, 'waste_percentage' => 15],
                ['ingredient_id' => $tomato->id, 'quantity' => 30, 'unit_id' => $g->id, 'waste_percentage' => 10],
            ]
        );
        // Create a non-inventory item (beverage)
        MenuItem::withoutTenantScope()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'cola'],
            [
                'category_id' => $beverages->id,
                'name' => 'Cola',
                'description' => 'Refreshing cola drink',
                'price' => 2.99,
                'cost' => 0.50,
                'sku' => 'MENU-COLA-001',
                'is_available' => true,
                'track_inventory' => false, // No inventory tracking
                'preparation_time' => 0,
                'sort_order' => 1,
            ]
        );

        $this->command->info('Created sample menu with ingredients and recipes!');
    }
}
