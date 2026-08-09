<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class SalesBreakdownReportsTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected Category $food;

    protected Category $drinks;

    protected MenuItem $burger;

    protected MenuItem $fries;

    protected MenuItem $cola;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->actingAsCompanyAdmin();

        $this->food = $this->category('Food', 'food');
        $this->drinks = $this->category('Drinks', 'drinks');
        $this->burger = $this->menuItem($this->food, 'Burger', 'burger', 100);
        $this->fries = $this->menuItem($this->food, 'Fries', 'fries', 100);
        $this->cola = $this->menuItem($this->drinks, 'Cola', 'cola', 100);
    }

    public function test_sales_report_shows_summary_and_category_breakdown(): void
    {
        $matchingOrder = $this->order('ORD-CATEGORY-1', 350);
        $this->line($matchingOrder, $this->burger, 2, 0, 200);
        $this->line($matchingOrder, $this->fries, 1, 0.5, 50);
        $this->line($matchingOrder, $this->cola, 1, 0, 100);

        $otherOrder = $this->order('ORD-DRINK-1', 100);
        $this->line($otherOrder, $this->cola, 1, 0, 100);

        $response = $this->getJson(route('reports.panel', [
            'report' => 'sales',
            'branch_id' => $this->tenantBranch->id,
            'from' => local_today($this->tenantBranch->id),
            'to' => local_today($this->tenantBranch->id),
        ]));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Sales by category', $html);
        $this->assertMatchesRegularExpression('/450([.,]00)?/', $html);
        $this->assertStringContainsString('Food', $html);
        $this->assertStringContainsString('Drinks', $html);
    }

    public function test_sales_report_category_filter_lists_each_order_once_and_sums_matching_lines(): void
    {
        $matchingOrder = $this->order('ORD-CATEGORY-1', 350);
        $this->line($matchingOrder, $this->burger, 2, 0, 200);
        $this->line($matchingOrder, $this->fries, 1, 0.5, 50);
        $this->line($matchingOrder, $this->cola, 1, 0, 100);

        $otherOrder = $this->order('ORD-DRINK-1', 100);
        $this->line($otherOrder, $this->cola, 1, 0, 100);

        $response = $this->getJson(route('reports.panel', [
            'report' => 'sales',
            'branch_id' => $this->tenantBranch->id,
            'category_id' => $this->food->id,
            'from' => local_today($this->tenantBranch->id),
            'to' => local_today($this->tenantBranch->id),
        ]));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('ORD-CATEGORY-1', $html);
        $this->assertStringNotContainsString('ORD-DRINK-1', $html);
        $this->assertMatchesRegularExpression('/250([.,]00)?/', $html);
    }

    public function test_legacy_sales_by_category_url_redirects_to_sales_report(): void
    {
        $response = $this->get(route('reports.sales-by-category', [
            'generate' => 1,
            'branch_id' => $this->tenantBranch->id,
            'category_id' => $this->food->id,
            'from' => local_today($this->tenantBranch->id),
            'to' => local_today($this->tenantBranch->id),
        ]));

        $response->assertRedirect(route('reports.index', [
            'branch_id' => $this->tenantBranch->id,
            'category_id' => $this->food->id,
            'from' => local_today($this->tenantBranch->id),
            'to' => local_today($this->tenantBranch->id),
            'report' => 'sales',
        ]));
    }

    public function test_sales_by_item_page_redirects_to_hub_without_ajax(): void
    {
        $response = $this->get(route('reports.sales-by-item', [
            'generate' => 1,
            'branch_id' => $this->tenantBranch->id,
            'menu_item_ids' => [$this->burger->id],
            'from' => local_today($this->tenantBranch->id),
            'to' => local_today($this->tenantBranch->id),
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('report=sales-by-item', $response->headers->get('Location'));
    }

    public function test_sales_by_item_ajax_only_lists_orders_containing_selected_item(): void
    {
        $burgerOrder = $this->order('ORD-BURGER-1', 200);
        $this->line($burgerOrder, $this->burger, 2, 0, 200);

        $friesOrder = $this->order('ORD-FRIES-1', 100);
        $this->line($friesOrder, $this->fries, 1, 0, 100);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.sales-by-item', [
                'generate' => 1,
                'branch_id' => $this->tenantBranch->id,
                'menu_item_ids' => [$this->burger->id],
                'from' => local_today($this->tenantBranch->id),
                'to' => local_today($this->tenantBranch->id),
            ]));

        $response->assertOk();
        $response->assertSee('ORD-BURGER-1');
        $response->assertDontSee('ORD-FRIES-1');
        $this->assertSame(1, $response->viewData('summary')['order_count']);
        $this->assertSame(2.0, $response->viewData('summary')['matched_quantity']);
        $this->assertSame(200.0, $response->viewData('summary')['matched_sales']);
    }

    public function test_sales_by_item_ajax_supports_multiple_items_and_legacy_single_param(): void
    {
        $burgerOrder = $this->order('ORD-BURGER-1', 200);
        $this->line($burgerOrder, $this->burger, 2, 0, 200);

        $friesOrder = $this->order('ORD-FRIES-1', 100);
        $this->line($friesOrder, $this->fries, 1, 0, 100);

        $colaOrder = $this->order('ORD-COLA-1', 100);
        $this->line($colaOrder, $this->cola, 1, 0, 100);

        $multi = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.sales-by-item', [
                'generate' => 1,
                'branch_id' => $this->tenantBranch->id,
                'menu_item_ids' => [$this->burger->id, $this->fries->id],
                'from' => local_today($this->tenantBranch->id),
                'to' => local_today($this->tenantBranch->id),
            ]));

        $multi->assertOk();
        $multi->assertSee('ORD-BURGER-1');
        $multi->assertSee('ORD-FRIES-1');
        $multi->assertDontSee('ORD-COLA-1');
        $this->assertSame(2, $multi->viewData('summary')['order_count']);
        $this->assertSame(300.0, $multi->viewData('summary')['matched_sales']);

        $legacy = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.sales-by-item', [
                'generate' => 1,
                'branch_id' => $this->tenantBranch->id,
                'menu_item_id' => $this->burger->id,
                'from' => local_today($this->tenantBranch->id),
                'to' => local_today($this->tenantBranch->id),
            ]));

        $legacy->assertOk();
        $legacy->assertSee('ORD-BURGER-1');
        $legacy->assertDontSee('ORD-FRIES-1');
    }

    public function test_sales_by_item_ajax_category_only_includes_all_items_in_category(): void
    {
        $foodOrder = $this->order('ORD-FOOD-1', 300);
        $this->line($foodOrder, $this->burger, 2, 0, 200);
        $this->line($foodOrder, $this->fries, 1, 0, 100);

        $drinkOrder = $this->order('ORD-DRINK-1', 100);
        $this->line($drinkOrder, $this->cola, 1, 0, 100);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.sales-by-item', [
                'generate' => 1,
                'branch_id' => $this->tenantBranch->id,
                'category_ids' => [$this->food->id],
                'from' => local_today($this->tenantBranch->id),
                'to' => local_today($this->tenantBranch->id),
            ]));

        $response->assertOk();
        $response->assertSee('ORD-FOOD-1');
        $response->assertDontSee('ORD-DRINK-1');
        $this->assertSame(1, $response->viewData('summary')['order_count']);
        $this->assertSame(3.0, $response->viewData('summary')['matched_quantity']);
        $this->assertSame(300.0, $response->viewData('summary')['matched_sales']);
    }

    protected function category(string $name, string $slug): Category
    {
        return Category::create([
            'company_id' => $this->tenantCompany->id,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    protected function menuItem(
        Category $category,
        string $name,
        string $slug,
        float $price
    ): MenuItem {
        return MenuItem::create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'type' => 'single',
            'price' => $price,
            'cost' => 0,
            'is_available' => true,
            'track_inventory' => false,
        ]);
    }

    protected function order(string $number, float $total): Order
    {
        return Order::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => $number,
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => $total,
            'total_amount' => $total,
            'paid_amount' => $total,
            'completed_at' => now(),
            'business_date' => local_today($this->tenantBranch->id),
        ]);
    }

    protected function line(
        Order $order,
        MenuItem $item,
        float $quantity,
        float $refunded,
        float $total
    ): OrderItem {
        return OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'quantity_refunded' => $refunded,
            'unit_price' => $item->price,
            'total_price' => $total,
            'status' => 'served',
        ]);
    }
}
