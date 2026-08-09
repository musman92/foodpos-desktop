<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\KitchenKot;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Services\PosPrintReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class CheckoutKitchenDirectPrintTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private MoneySource $cashSource;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestTenant();
        $this->openTenantShift();
        $this->actingAsCompanyAdmin();

        $this->mock(PosPrintReadinessService::class, function ($mock) {
            $mock->shouldReceive('readinessErrorResponse')->andReturn(null);
            $mock->shouldReceive('check')->andReturn([
                'ok' => true,
                'errors' => [],
                'warnings' => [],
            ]);
        });

        $this->cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'active' => true,
            'opening_balance' => 0,
        ]);
        $this->cashSource->branches()->attach($this->tenantBranch->id);

        $category = Category::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'name' => 'Mains',
            'code' => 'MN',
            'slug' => 'mains-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->menuItem = MenuItem::withoutGlobalScopes()->create([
            'company_id' => $this->tenantCompany->id,
            'category_id' => $category->id,
            'name' => 'Burger',
            'slug' => 'burger-'.uniqid(),
            'type' => 'simple',
            'price' => 500,
            'cost' => 0,
            'is_available' => true,
            'track_inventory' => false,
        ]);
    }

    public function test_checkout_queues_kot_when_kitchen_is_direct_print(): void
    {
        // Create the open tab before enabling direct kitchen print so Save does not auto-KOT.
        $order = $this->createOpenTab();
        $this->setupDirectKitchenPrinter();

        $response = $this->postJson(route('pos.orders.checkout', $order), [
            'money_source_id' => $this->cashSource->id,
            'paid_amount' => 500,
            'payment_status' => 'paid',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('kitchen_desktop_jobs', 1);
        $this->assertCount(1, $response->json('kots'));

        $this->assertSame(1, KitchenKot::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, PrintJob::query()
            ->where('document_type', 'kitchen_kot')
            ->where('branch_id', $this->tenantBranch->id)
            ->count());
    }

    public function test_checkout_with_auto_bill_false_skips_receipt_and_kitchen_print(): void
    {
        $order = $this->createOpenTab();
        $this->setupDirectKitchenPrinter();
        $this->setupDirectReceiptPrinter();

        $response = $this->postJson(route('pos.orders.checkout', $order), [
            'money_source_id' => $this->cashSource->id,
            'paid_amount' => 500,
            'payment_status' => 'paid',
            'auto_bill' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('auto_bill', false);
        $response->assertJsonPath('browser_print', false);
        $response->assertJsonPath('desktop_jobs', 0);
        $response->assertJsonPath('kitchen_desktop_jobs', 0);
        $response->assertJsonPath('kots', []);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame(0, KitchenKot::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, PrintJob::query()->where('branch_id', $this->tenantBranch->id)->count());
    }

    public function test_save_tab_queues_kot_when_kitchen_is_direct_print(): void
    {
        $this->setupDirectKitchenPrinter();

        $response = $this->postJson(route('pos.store'), [
            'mode' => 'tab',
            'type' => 'takeaway',
            'branch_id' => $this->tenantBranch->id,
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'item_name' => $this->menuItem->name,
                'name' => $this->menuItem->name,
                'quantity' => 1,
                'unit_price' => 500,
                'variants' => null,
                'addons' => null,
                'special_instructions' => '',
            ]],
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_type' => null,
            'discount_value' => null,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500,
            'payment_status' => 'unpaid',
            'notes' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('kitchen_desktop_jobs', 1);
        $this->assertCount(1, $response->json('kots'));

        $orderId = (int) $response->json('order.id');
        $this->assertSame(1, KitchenKot::query()->where('order_id', $orderId)->count());
        $this->assertSame(1, PrintJob::query()
            ->where('document_type', 'kitchen_kot')
            ->where('branch_id', $this->tenantBranch->id)
            ->count());
    }

    public function test_save_tab_does_not_queue_kot_when_kitchen_is_browser_only(): void
    {
        Printer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'title' => 'Kitchen Browser',
            'role' => 'kitchen',
            'printing_mode' => Printer::MODE_BROWSER_POPUP,
            'device_name' => null,
            'is_default' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('pos.store'), [
            'mode' => 'tab',
            'type' => 'takeaway',
            'branch_id' => $this->tenantBranch->id,
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'item_name' => $this->menuItem->name,
                'name' => $this->menuItem->name,
                'quantity' => 1,
                'unit_price' => 500,
                'variants' => null,
                'addons' => null,
                'special_instructions' => '',
            ]],
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_type' => null,
            'discount_value' => null,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500,
            'payment_status' => 'unpaid',
            'notes' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('kitchen_desktop_jobs', 0);
        $this->assertSame(0, KitchenKot::query()->where('order_id', $response->json('order.id'))->count());
        $this->assertSame(0, PrintJob::query()->where('document_type', 'kitchen_kot')->count());
    }

    public function test_checkout_does_not_queue_kot_when_kitchen_is_browser_only(): void
    {
        Printer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'title' => 'Kitchen Browser',
            'role' => 'kitchen',
            'printing_mode' => Printer::MODE_BROWSER_POPUP,
            'device_name' => null,
            'is_default' => true,
            'is_active' => true,
        ]);

        $order = $this->createOpenTab();

        $response = $this->postJson(route('pos.orders.checkout', $order), [
            'money_source_id' => $this->cashSource->id,
            'paid_amount' => 500,
            'payment_status' => 'paid',
        ]);

        $response->assertOk();
        $response->assertJsonPath('kitchen_desktop_jobs', 0);
        $this->assertSame(0, KitchenKot::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, PrintJob::query()->where('document_type', 'kitchen_kot')->count());
    }

    public function test_checkout_does_not_double_print_kot_when_already_sent(): void
    {
        $this->setupDirectKitchenPrinter();

        $order = $this->createOpenTab();

        $this->postJson(route('pos.orders.send-to-kitchen', $order), [
            'type' => 'takeaway',
            'table_id' => null,
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'item_name' => $this->menuItem->name,
                'name' => $this->menuItem->name,
                'quantity' => 1,
                'unit_price' => 500,
                'variants' => null,
                'addons' => null,
                'special_instructions' => '',
            ]],
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_type' => null,
            'discount_value' => null,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500,
            'notes' => null,
        ])->assertOk();

        $this->assertSame(1, KitchenKot::query()->where('order_id', $order->id)->count());
        $jobsAfterKot = PrintJob::query()->where('document_type', 'kitchen_kot')->count();
        $this->assertSame(1, $jobsAfterKot);

        $response = $this->postJson(route('pos.orders.checkout', $order), [
            'money_source_id' => $this->cashSource->id,
            'paid_amount' => 500,
            'payment_status' => 'paid',
        ]);

        $response->assertOk();
        $response->assertJsonPath('kitchen_desktop_jobs', 0);
        $this->assertSame(1, KitchenKot::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, PrintJob::query()->where('document_type', 'kitchen_kot')->count());
    }

    private function setupDirectKitchenPrinter(): void
    {
        Printer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'title' => 'Kitchen Direct',
            'role' => 'kitchen',
            'printing_mode' => Printer::MODE_DIRECT,
            'device_name' => 'Kitchen-80mm',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function setupDirectReceiptPrinter(): void
    {
        Printer::withoutTenantScope()->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'title' => 'Receipt Direct',
            'role' => 'receipt',
            'printing_mode' => Printer::MODE_DIRECT,
            'device_name' => 'Receipt-80mm',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function createOpenTab(): Order
    {
        $response = $this->postJson(route('pos.store'), [
            'mode' => 'tab',
            'type' => 'takeaway',
            'branch_id' => $this->tenantBranch->id,
            'items' => [[
                'menu_item_id' => $this->menuItem->id,
                'item_name' => $this->menuItem->name,
                'name' => $this->menuItem->name,
                'quantity' => 1,
                'unit_price' => 500,
                'variants' => null,
                'addons' => null,
                'special_instructions' => '',
            ]],
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_type' => null,
            'discount_value' => null,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => 500,
            'payment_status' => 'unpaid',
            'notes' => null,
        ]);

        $response->assertOk();

        return Order::withoutGlobalScopes()->findOrFail($response->json('order.id'));
    }
}
