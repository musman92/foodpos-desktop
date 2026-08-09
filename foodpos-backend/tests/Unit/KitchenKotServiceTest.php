<?php

namespace Tests\Unit;

use App\Services\KitchenKotService;
use PHPUnit\Framework\TestCase;

class KitchenKotServiceTest extends TestCase
{
    public function test_diff_detects_add_and_void_lines(): void
    {
        $service = new KitchenKotService();

        $previous = $service->normalizeSnapshot([
            [
                'line_key' => 'burger',
                'item_name' => 'Zinger Burger',
                'quantity' => 2,
                'special_instructions' => '',
                'variants_label' => '',
            ],
        ]);

        $current = $service->snapshotFromCart([
            [
                'menu_item_id' => 1,
                'item_name' => 'Zinger Burger',
                'quantity' => 1,
                'variants' => [],
                'special_instructions' => '',
            ],
            [
                'menu_item_id' => 2,
                'item_name' => 'Fries',
                'quantity' => 1,
                'variants' => [],
                'special_instructions' => '',
            ],
        ]);

        // Force stable keys for test
        $previous = ['burger' => $previous['burger']];
        $current[0]['line_key'] = 'burger';
        $current[1]['line_key'] = 'fries';

        [$voidLines, $addLines] = $service->diffSnapshots($previous, $current);

        $this->assertCount(1, $voidLines);
        $this->assertSame(1.0, $voidLines[0]['quantity']);
        $this->assertSame('Zinger Burger', $voidLines[0]['item_name']);

        $this->assertCount(1, $addLines);
        $this->assertSame('Fries', $addLines[0]['item_name']);
    }

    public function test_multi_kot_edit_session_ends_in_sync_with_final_bill(): void
    {
        $service = new KitchenKotService();

        $line = fn (string $key, string $name, float $qty) => [
            'line_key' => $key,
            'item_name' => $name,
            'quantity' => $qty,
            'special_instructions' => '',
            'variants_label' => '',
            'addons_label' => '',
        ];

        $snapshot = [];

        $applySend = function (array $cartLines) use ($service, &$snapshot, $line): array {
            $previous = $service->normalizeSnapshot($snapshot);
            $current = array_values(array_map(fn (array $row) => $line(
                (string) $row['line_key'],
                (string) $row['item_name'],
                (float) $row['quantity']
            ), $cartLines));

            if ($previous === []) {
                $voidLines = [];
                $addLines = $current;
            } else {
                [$voidLines, $addLines] = $service->diffSnapshots($previous, $current);
            }

            $snapshot = $current;

            return [$voidLines, $addLines];
        };

        // KOT #3 — first send: 1 BBQ, 1 Beef, 1 Chicken Patty
        [$void, $add] = $applySend([
            ['line_key' => 'bbq', 'item_name' => 'B.B.Q Burger', 'quantity' => 1],
            ['line_key' => 'beef', 'item_name' => 'Beef Burger', 'quantity' => 1],
            ['line_key' => 'chicken', 'item_name' => 'Chicken Patty Burger', 'quantity' => 1],
        ]);
        $this->assertSame([], $void);
        $this->assertCount(3, $add);

        // KOT #4 — add more items
        [$void, $add] = $applySend([
            ['line_key' => 'bbq', 'item_name' => 'B.B.Q Burger', 'quantity' => 2],
            ['line_key' => 'beef', 'item_name' => 'Beef Burger', 'quantity' => 2],
            ['line_key' => 'chicken', 'item_name' => 'Chicken Patty Burger', 'quantity' => 2],
            ['line_key' => 'grill', 'item_name' => 'Grill Burger', 'quantity' => 1],
            ['line_key' => 'empire', 'item_name' => 'Empire Burger', 'quantity' => 1],
        ]);
        $this->assertSame([], $void);
        $this->assertCount(5, $add);

        // KOT #5 — void the KOT #4 additions
        [$void, $add] = $applySend([
            ['line_key' => 'bbq', 'item_name' => 'B.B.Q Burger', 'quantity' => 1],
            ['line_key' => 'beef', 'item_name' => 'Beef Burger', 'quantity' => 1],
            ['line_key' => 'chicken', 'item_name' => 'Chicken Patty Burger', 'quantity' => 1],
        ]);
        $this->assertCount(5, $void);
        $this->assertSame([], $add);

        // KOT #6 — void beef and chicken patty
        [$void, $add] = $applySend([
            ['line_key' => 'bbq', 'item_name' => 'B.B.Q Burger', 'quantity' => 1],
        ]);
        $this->assertCount(2, $void);
        $this->assertSame([], $add);

        // KOT #7 — add 2 more BBQ burgers (final bill: 3 BBQ)
        [$void, $add] = $applySend([
            ['line_key' => 'bbq', 'item_name' => 'B.B.Q Burger', 'quantity' => 3],
        ]);
        $this->assertSame([], $void);
        $this->assertCount(1, $add);
        $this->assertSame(2.0, $add[0]['quantity']);

        $finalBill = [
            $line('bbq', 'B.B.Q Burger', 3),
        ];

        $this->assertTrue($service->kitchenInSyncWithBill($snapshot, $finalBill));
        $this->assertSame([
            ['item_name' => 'B.B.Q Burger', 'quantity' => 3.0],
        ], $service->sentItemsSummary($snapshot));
    }

    public function test_deal_line_key_is_stable_for_string_and_int_ids(): void
    {
        $service = new KitchenKotService();

        $fromInt = $service->normalizeCartLine([
            'deal_id' => 5,
            'item_name' => 'Chow-Special Deal',
            'quantity' => 1,
        ]);

        $fromString = $service->normalizeCartLine([
            'deal_id' => '5',
            'item_name' => 'Chow-Special Deal',
            'quantity' => 1,
        ]);

        $this->assertSame($fromInt['line_key'], $fromString['line_key']);
    }

    public function test_deal_swap_voids_old_and_adds_new_deal(): void
    {
        $service = new KitchenKotService();

        $dealA = $service->normalizeCartLine([
            'deal_id' => 1,
            'item_name' => 'Deal No. 2',
            'quantity' => 1,
        ]);

        $dealB = $service->normalizeCartLine([
            'deal_id' => 2,
            'item_name' => 'Chow-Special Deal',
            'quantity' => 1,
        ]);

        $previous = $service->normalizeSnapshot([$dealA]);
        [$voidLines, $addLines] = $service->diffSnapshots($previous, [$dealB]);

        $this->assertCount(1, $voidLines);
        $this->assertSame('Deal No. 2', $voidLines[0]['item_name']);
        $this->assertCount(1, $addLines);
        $this->assertSame('Chow-Special Deal', $addLines[0]['item_name']);
    }

    public function test_bill_and_kitchen_snapshots_match_after_send_payload(): void
    {
        $service = new KitchenKotService();

        $cartLine = [
            'deal_id' => '3',
            'item_name' => 'Chow-Special Deal',
            'quantity' => 1,
            'variants' => null,
            'addons' => null,
            'special_instructions' => '',
        ];

        $kitchenSnapshot = $service->snapshotFromCart([$cartLine]);
        $billSnapshot = $service->snapshotFromOrderItems([
            (object) [
                'menu_item_id' => null,
                'deal_id' => 3,
                'item_name' => 'Chow-Special Deal',
                'quantity' => 1,
                'variants' => [],
                'addons' => null,
                'special_instructions' => '',
            ],
        ]);

        $this->assertTrue($service->kitchenInSyncWithBill($kitchenSnapshot, $billSnapshot));
    }
}
