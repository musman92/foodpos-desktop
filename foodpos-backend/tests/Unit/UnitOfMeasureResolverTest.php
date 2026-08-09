<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\IngredientUnit;
use App\Models\UnitOfMeasure;
use App\Support\UnitOfMeasureResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnitOfMeasureResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_ingredient_unit_id_to_units_of_measure_id(): void
    {
        $company = Company::create([
            'name' => 'Test Cafe',
            'slug' => 'test-cafe-'.Str::random(8),
            'email' => 'cafe-'.Str::random(8).'@example.com',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $ingredientUnit = IngredientUnit::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Gram',
            'code' => 'C01',
        ]);

        $resolver = app(UnitOfMeasureResolver::class);
        $unitId = $resolver->resolveId((string) $ingredientUnit->id, (int) $company->id);

        $this->assertNotNull($unitId);
        $this->assertDatabaseHas('units_of_measure', [
            'id' => $unitId,
            'company_id' => $company->id,
            'abbreviation' => 'C01',
        ]);
    }

    public function test_returns_existing_numeric_unit_id(): void
    {
        $company = Company::create([
            'name' => 'Test Cafe 2',
            'slug' => 'test-cafe-2-'.Str::random(8),
            'email' => 'cafe2-'.Str::random(8).'@example.com',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $unit = UnitOfMeasure::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Gram',
            'abbreviation' => 'g',
            'type' => 'weight',
            'is_base_unit' => true,
        ]);

        $resolver = app(UnitOfMeasureResolver::class);

        $this->assertSame((int) $unit->id, $resolver->resolveId((string) $unit->id, (int) $company->id));
    }
}
