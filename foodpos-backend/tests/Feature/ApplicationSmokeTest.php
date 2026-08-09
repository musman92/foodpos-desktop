<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

/**
 * Smoke tests: hit real routes against the full migrated schema before production deploy.
 */
class ApplicationSmokeTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestTenant();
    }

    public function test_full_application_schema_migrates_on_sqlite(): void
    {
        $this->assertTrue(Schema::hasTable('companies'));
        $this->assertTrue(Schema::hasTable('branches'));
        $this->assertTrue(Schema::hasTable('menu_items'));
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasTable('shifts'));
        $this->assertTrue(Schema::hasTable('recipes'));
        $this->assertTrue(Schema::hasTable('recipe_items'));
        $this->assertTrue(Schema::hasTable('menu_item_variant_recipes'));
        $this->assertTrue(Schema::hasColumn('menu_items', 'default_recipe_id'));
        $this->assertTrue(Schema::hasTable('menu_item_recipe_lines'));
        $this->assertTrue(Schema::hasTable('permissions'));
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[DataProvider('coreAdminPages')]
    public function test_company_admin_can_open_core_pages(string $routeName, array $parameters = []): void
    {
        $this->actingAsCompanyAdmin()
            ->get(route($routeName, $parameters))
            ->assertOk();
    }

    #[DataProvider('shiftGatedPages')]
    public function test_company_admin_can_open_shift_gated_pages_with_active_shift(string $routeName, array $parameters = []): void
    {
        $this->openTenantShift();

        $this->actingAsCompanyAdmin()
            ->get(route($routeName, $parameters))
            ->assertOk();
    }

    #[DataProvider('exportEndpoints')]
    public function test_company_admin_can_download_exports(string $routeName, array $parameters = []): void
    {
        $response = $this->actingAsCompanyAdmin()
            ->get(route($routeName, $parameters));

        $response->assertOk();
        $this->assertNotEmpty($response->headers->get('content-disposition'));
    }

    public static function coreAdminPages(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'menu items' => ['menu-items.index'],
            'menu items import' => ['menu-items.import'],
            'categories' => ['categories.index'],
            'categories import' => ['categories.import'],
            'ingredients' => ['ingredients.index'],
            'ingredients import' => ['ingredients.import'],
            'ingredient categories' => ['ingredient-categories.index'],
            'ingredient units' => ['ingredient-units.index'],
            'customers' => ['customers.index'],
            'customers import' => ['customers.import'],
            'suppliers' => ['suppliers.index'],
            'suppliers import' => ['suppliers.import'],
            'variants' => ['variants.index'],
            'variants import' => ['variants.import'],
            'recipes' => ['recipes.index'],
            'recipes import' => ['recipes.import'],
            'product addons' => ['product-addons.index'],
            'product addons import' => ['product-addons.import'],
            'deals' => ['deals.index'],
            'shifts' => ['shifts.index'],
            'money sources' => ['money-sources.index'],
            'inventory' => ['inventory.index'],
            'order management' => ['order-management.index'],
            'refunds' => ['order-management.refunds.index'],
            'reports hub' => ['reports.index'],
            'sales report' => ['reports.index', ['report' => 'sales']],
            'sales by item report' => ['reports.index', ['report' => 'sales-by-item']],
            'consumption report' => ['reports.index', ['report' => 'consumption']],
            'ingredient ledger report' => ['reports.index', ['report' => 'ingredient-ledger']],
            'daily report' => ['reports.index', ['report' => 'daily']],
            'z report' => ['reports.index', ['report' => 'z-report']],
            'company settings' => ['company-settings.index'],
            'printer settings' => ['printer-settings.index'],
            'account statements' => ['reports.index', ['report' => 'account-statement']],
            'hr employees' => ['hr.employees.index'],
            'hr attendance' => ['hr.attendance.index'],
            'hr leave' => ['hr.leaves.index'],
            'hr payroll' => ['hr.payroll.index'],
            'hr adjustments' => ['hr.adjustments.index'],
        ];
    }

    public static function shiftGatedPages(): array
    {
        return [
            'pos' => ['pos.index'],
            'purchases' => ['purchases.index'],
            'transactions' => ['transactions.index'],
            'supplier payments' => ['supplier-payments.index'],
            'customer payments' => ['customer-payments.index'],
            'employee payments' => ['employee-payments.index'],
        ];
    }

    public static function exportEndpoints(): array
    {
        return [
            'categories xlsx' => ['categories.export', ['format' => 'xlsx']],
            'customers xlsx' => ['customers.export', ['format' => 'xlsx']],
            'suppliers xlsx' => ['suppliers.export', ['format' => 'xlsx']],
            'ingredients xlsx' => ['ingredients.export', ['format' => 'xlsx']],
            'variants xlsx' => ['variants.export', ['format' => 'xlsx']],
            'recipes xlsx' => ['recipes.export', ['format' => 'xlsx']],
            'product addons xlsx' => ['product-addons.export', ['format' => 'xlsx']],
            'menu items xlsx' => ['menu-items.export'],
        ];
    }
}
