<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Floor;
use App\Models\KitchenKot;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTenant;
use Tests\TestCase;

class KitchenKotPosTest extends TestCase
{
    use CreatesTestTenant;
    use RefreshDatabase;

    private Table $table;

    private Deal $dealA;

    private Deal $dealB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestTenant();
        $this->openTenantShift();

        $floor = Floor::create([
            'branch_id' => $this->tenantBranch->id,
            'name' => 'Ground',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->table = Table::withoutGlobalScope('branch')->create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'floor_id' => $floor->id,
            'name' => 'Table 1',
            'slug' => 'table-1',
            'code' => 'T1',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->dealA = Deal::create([
            'company_id' => $this->tenantCompany->id,
            'title' => 'Deal No. 2',
            'price' => 4500,
            'is_active' => true,
        ]);

        $this->dealB = Deal::create([
            'company_id' => $this->tenantCompany->id,
            'title' => 'Chow-Special Deal',
            'price' => 4500,
            'is_active' => true,
        ]);
    }

    public function test_save_after_deal_swap_reports_kitchen_out_of_sync(): void
    {
        $order = $this->createTabOrderWithDeal($this->dealA);
        $this->sendOrderToKitchen($order, [$this->dealLine($this->dealA)]);

        $response = $this->patchOrder($order, [$this->dealLine($this->dealB)]);

        $response->assertOk();
        $response->assertJsonPath('order.kitchen_sync.in_sync', false);
        $response->assertJsonPath('order.kitchen_sync.sent_items.0.item_name', 'Deal No. 2');
        $response->assertJsonPath('order.items.0.item_name', 'Chow-Special Deal');
    }

    public function test_resending_kot_after_deal_swap_voids_old_and_adds_new(): void
    {
        $order = $this->createTabOrderWithDeal($this->dealA);
        $this->sendOrderToKitchen($order, [$this->dealLine($this->dealA)]);
        $this->patchOrder($order, [$this->dealLine($this->dealB)]);

        $response = $this->sendOrderToKitchen($order->fresh(), [$this->dealLine($this->dealB)]);

        $response->assertOk();
        $response->assertJsonPath('order.kitchen_sync.in_sync', true);
        $response->assertJsonCount(2, 'kots');

        $kots = KitchenKot::query()->where('order_id', $order->id)->orderBy('kot_number')->get();
        $this->assertSame('full', $kots[0]->type);
        $this->assertSame('void', $kots[1]->type);
        $this->assertSame('add', $kots[2]->type);
        $this->assertSame('Deal No. 2', $kots[1]->lines[0]['item_name']);
        $this->assertSame('Chow-Special Deal', $kots[2]->lines[0]['item_name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function dealLine(Deal $deal): array
    {
        return [
            'deal_id' => $deal->id,
            'menu_item_id' => null,
            'item_name' => $deal->title,
            'name' => $deal->title,
            'quantity' => 1,
            'unit_price' => (float) $deal->price,
            'variants' => null,
            'addons' => null,
            'special_instructions' => '',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function orderPayload(array $items): array
    {
        $subtotal = collect($items)->sum(fn (array $item) => (float) $item['unit_price'] * (float) $item['quantity']);

        return [
            'type' => 'dine_in',
            'table_id' => $this->table->id,
            'waiter_id' => $this->companyAdmin->id,
            'customer_name' => '',
            'customer_phone' => '',
            'customer_email' => null,
            'customer_address' => '',
            'items' => $items,
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'discount_type' => null,
            'discount_value' => null,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'total_amount' => $subtotal,
            'notes' => null,
        ];
    }

    private function createTabOrderWithDeal(Deal $deal): Order
    {
        $response = $this->actingAsCompanyAdmin()
            ->postJson(route('pos.store'), array_merge([
                'mode' => 'tab',
                'branch_id' => $this->tenantBranch->id,
            ], $this->orderPayload([$this->dealLine($deal)])));

        $response->assertOk();

        return Order::query()->findOrFail($response->json('order.id'));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function patchOrder(Order $order, array $items): \Illuminate\Testing\TestResponse
    {
        return $this->actingAsCompanyAdmin()
            ->patchJson(route('pos.orders.update', $order), $this->orderPayload($items));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function sendOrderToKitchen(Order $order, array $items): \Illuminate\Testing\TestResponse
    {
        return $this->actingAsCompanyAdmin()
            ->postJson(route('pos.orders.send-to-kitchen', $order), $this->orderPayload($items));
    }
}
