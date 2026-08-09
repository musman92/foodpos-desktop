<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Deal;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\ReceiptSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class DealInvoiceItemsTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();
    }

    public function test_invoice_shows_deal_menu_items_when_section_enabled(): void
    {
        $this->actingAsCompanyAdmin();
        [$deal, $burger, $drink] = $this->createDealWithItems();

        $order = $this->createDealOrder($deal);

        $html = view('pos._receipt-body', [
            'order' => $order->load([
                'items.deal.menuItems' => fn ($q) => $q->withoutGlobalScopes(),
                'cashier',
                'table',
                'branch',
                'company',
            ]),
            'sections' => ReceiptSections::defaults(),
            'receiptLayout' => receipt_layout_settings([
                'receipt_sections' => ReceiptSections::defaults(),
            ]),
        ])->render();

        $this->assertStringContainsString($deal->title, $html);
        $this->assertStringContainsString($burger->name, $html);
        $this->assertStringContainsString($drink->name, $html);
        $this->assertStringContainsString('· '.$drink->name.' x2', $html);
    }

    public function test_invoice_hides_deal_menu_items_when_section_disabled(): void
    {
        $this->actingAsCompanyAdmin();
        [$deal, $burger, $drink] = $this->createDealWithItems();

        $order = $this->createDealOrder($deal);
        $sections = ReceiptSections::normalize(['deal_items' => false]);

        $html = view('pos._receipt-body', [
            'order' => $order->load([
                'items.deal.menuItems' => fn ($q) => $q->withoutGlobalScopes(),
                'cashier',
                'table',
                'branch',
                'company',
            ]),
            'sections' => $sections,
            'receiptLayout' => receipt_layout_settings([
                'receipt_sections' => $sections,
            ]),
        ])->render();

        $this->assertStringContainsString($deal->title, $html);
        $this->assertStringNotContainsString('· '.$burger->name, $html);
        $this->assertStringNotContainsString('· '.$drink->name, $html);
    }

    public function test_invoice_json_includes_deal_menu_items(): void
    {
        [$deal] = $this->createDealWithItems();
        $order = $this->createDealOrder($deal);

        $response = $this->actingAsCompanyAdmin()
            ->getJson(route('pos.invoice', $order->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.items.0.deal_id', $deal->id);

        $menuItems = $response->json('order.items.0.deal.menu_items')
            ?? $response->json('order.items.0.deal.menuItems');

        $this->assertIsArray($menuItems);
        $this->assertCount(2, $menuItems);
    }

    /**
     * @return array{0: Deal, 1: MenuItem, 2: MenuItem}
     */
    private function createDealWithItems(): array
    {
        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Mains',
            'code' => 'MAIN',
            'slug' => 'mains-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $burger = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'single',
            'name' => 'Classic Burger',
            'slug' => 'burger-'.uniqid(),
            'sku' => 'BRG-'.uniqid(),
            'price' => 500,
            'is_available' => true,
            'track_inventory' => false,
        ]);

        $drink = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'type' => 'single',
            'name' => 'Cola',
            'slug' => 'cola-'.uniqid(),
            'sku' => 'COLA-'.uniqid(),
            'price' => 100,
            'is_available' => true,
            'track_inventory' => false,
        ]);

        $deal = Deal::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'title' => 'Burger Combo',
            'price' => 650,
            'is_active' => true,
        ]);
        $deal->menuItems()->attach([
            $burger->id => ['quantity' => 1, 'unit_price' => 500],
            $drink->id => ['quantity' => 2, 'unit_price' => 75],
        ]);

        return [$deal, $burger, $drink];
    }

    private function createDealOrder(Deal $deal): Order
    {
        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'cashier_id' => $this->companyAdmin->id,
            'order_number' => 'INV-DEAL-'.uniqid(),
            'type' => 'takeaway',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => 650,
            'total_amount' => 650,
            'paid_amount' => 650,
            'completed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'deal_id' => $deal->id,
            'menu_item_id' => null,
            'item_name' => $deal->title,
            'quantity' => 1,
            'unit_price' => 650,
            'total_price' => 650,
            'status' => 'served',
        ]);

        return $order;
    }
}
