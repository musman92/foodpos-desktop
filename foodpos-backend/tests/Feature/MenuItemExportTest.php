<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Support\MenuItemExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class MenuItemExportTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_backfill_missing_skus_assigns_codes_before_export(): void
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

        $withoutSku = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Burger',
            'slug' => 'burger',
            'type' => 'single',
            'price' => 10,
            'sku' => null,
            'is_available' => true,
            'track_inventory' => false,
            'sort_order' => 1,
        ]);

        MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Fries',
            'slug' => 'fries',
            'type' => 'single',
            'price' => 5,
            'sku' => 'MI01',
            'is_available' => true,
            'track_inventory' => false,
            'sort_order' => 2,
        ]);

        $this->assertNull($withoutSku->fresh()->sku);

        $updated = MenuItem::backfillMissingSkus();

        $this->assertSame(1, $updated);
        $this->assertSame('MI02', $withoutSku->fresh()->sku);

        $response = (new MenuItemExport)->download();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('MI02', $withoutSku->fresh()->sku);
    }
}
