<?php

namespace Tests\Feature;

use App\Helpers\TenantDefaultRoles;
use App\Models\User;
use App\Services\TenantRoleBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class ReportHubTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
    }

    public function test_company_admin_can_load_reports_hub(): void
    {
        $this->actingAsCompanyAdmin()
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Operational Reporting', false);
    }

    public function test_panel_returns_json_with_html_for_daily_top_selling_and_payment_methods(): void
    {
        foreach (['daily', 'top-selling', 'payment-methods'] as $report) {
            $response = $this->actingAsCompanyAdmin()
                ->getJson(route('reports.panel', [
                    'report' => $report,
                    'branch_id' => $this->tenantBranch->id,
                    'from' => now()->subDays(7)->toDateString(),
                    'to' => now()->toDateString(),
                ]));

            $response->assertOk()
                ->assertJsonStructure(['key', 'title', 'html', 'exports' => ['pdf', 'excel']])
                ->assertJsonPath('key', $report);
        }
    }

    public function test_panel_forbidden_for_foc_without_permission(): void
    {
        $cashier = User::factory()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'type' => 'staff',
            'status' => 'active',
            'can_login' => true,
        ]);
        $cashier->branches()->attach($this->tenantBranch->id, ['is_primary' => true]);

        app(TenantRoleBootstrapService::class)->syncDefaultRolesForCompany($this->tenantCompany);
        setPermissionsTeamId($this->tenantCompany->id);
        $cashier->assignRole(TenantDefaultRoles::CASHIER);

        $this->actingAs($cashier)
            ->withSession(['current_branch_id' => $this->tenantBranch->id])
            ->getJson(route('reports.panel', ['report' => 'foc']))
            ->assertForbidden();
    }

    public function test_panel_not_found_for_unknown_report(): void
    {
        $this->actingAsCompanyAdmin()
            ->getJson(route('reports.panel', ['report' => 'does-not-exist']))
            ->assertNotFound();
    }

    public function test_daily_pdf_and_excel_exports_return_success(): void
    {
        $params = [
            'branch_id' => $this->tenantBranch->id,
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ];

        $this->actingAsCompanyAdmin()
            ->get(route('reports.daily.pdf', $params))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAsCompanyAdmin()
            ->get(route('reports.daily.excel', $params))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
    }

    public function test_legacy_daily_route_redirects_to_hub(): void
    {
        $this->actingAsCompanyAdmin()
            ->get(route('reports.daily'))
            ->assertRedirect(route('reports.index', ['report' => 'daily']));
    }

    public function test_panel_returns_exports_for_all_catalog_keys(): void
    {
        $this->actingAsCompanyAdmin();

        foreach (\App\Support\ReportHubCatalog::keys() as $key) {
            $params = [
                'report' => $key,
                'branch_id' => $this->tenantBranch->id,
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
            ];

            if ($key === 'weekly-closing') {
                $params['week_of'] = now()->startOfWeek()->toDateString();
                $params['week_count'] = 1;
            }
            if ($key === 'monthly-closing') {
                $params['month'] = now()->format('Y-m');
            }

            $response = $this->getJson(route('reports.panel', $params));
            $response->assertOk()
                ->assertJsonPath('key', $key)
                ->assertJsonStructure(['exports' => ['pdf', 'excel']]);
            $this->assertNotEmpty($response->json('exports.pdf'));
            if ($key !== 'account-statement') {
                $this->assertNotEmpty($response->json('exports.excel'));
            }
        }
    }

    public function test_profit_loss_and_order_history_excel_routes_exist(): void
    {
        $params = [
            'branch_id' => $this->tenantBranch->id,
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'generate' => 1,
        ];

        $this->actingAsCompanyAdmin()
            ->get(route('reports.profit-loss.excel', $params))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );

        $this->actingAsCompanyAdmin()
            ->get(route('reports.order-history.excel', $params))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
    }

    public function test_order_history_panel_paginates(): void
    {
        $today = now()->toDateString();
        $perPage = \App\Support\OrderHistoryReport::WEB_PER_PAGE;

        for ($i = 1; $i <= $perPage + 1; $i++) {
            \App\Models\Order::withoutGlobalScopes()->create([
                'company_id' => $this->tenantCompany->id,
                'branch_id' => $this->tenantBranch->id,
                'cashier_id' => $this->companyAdmin->id,
                'order_number' => 'OH-PAGE-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'type' => 'takeaway',
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal' => 100,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 100,
                'paid_amount' => 100,
                'business_date' => $today,
                'completed_at' => now()->subMinutes($i),
            ]);
        }

        $baseParams = [
            'report' => 'order-history',
            'branch_id' => $this->tenantBranch->id,
            'from' => $today,
            'to' => $today,
        ];

        $page1 = $this->actingAsCompanyAdmin()
            ->getJson(route('reports.panel', $baseParams))
            ->assertOk()
            ->json('html');

        preg_match_all('/OH-PAGE-\d+/', $page1, $matches);
        $this->assertNotEmpty($matches[0], 'Expected order numbers in HTML. Snippet: '.substr(strip_tags($page1), 0, 500));
        $this->assertStringContainsString('report-hub-pagination', $page1);
        $this->assertStringContainsString('page=2', $page1);

        $page1Orders = $matches[0];
        $this->assertCount($perPage, array_unique($page1Orders));

        $page2 = $this->actingAsCompanyAdmin()
            ->getJson(route('reports.panel', $baseParams + ['page' => 2]))
            ->assertOk()
            ->json('html');

        preg_match_all('/OH-PAGE-\d+/', $page2, $matches2);
        $page2Orders = array_values(array_unique($matches2[0]));
        $this->assertNotEmpty($page2Orders);
        $this->assertEmpty(array_intersect($page1Orders, $page2Orders));
    }
}
