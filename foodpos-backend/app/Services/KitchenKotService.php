<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\KitchenKot;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderWorkflow;
use App\Services\PosAddonService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KitchenKotService
{
    /**
     * Build kitchen slips from cart vs last sent snapshot; persist KOTs and update order.
     *
     * @param  list<array<string, mixed>>  $cartItems  POS cart lines
     * @return list<KitchenKot>
     */
    public function sendToKitchen(Order $order, array $cartItems, User $user): array
    {
        $previous = $this->normalizeSnapshot($order->kitchen_cart_snapshot ?? []);
        $current = $this->snapshotFromCart($cartItems);

        $voidLines = [];
        $addLines = [];

        if ($previous === []) {
            $addLines = array_values($current);
        } else {
            [$voidLines, $addLines] = $this->diffSnapshots($previous, $current);
        }

        if ($voidLines === [] && $addLines === []) {
            return [];
        }

        $companyId = (int) $order->company_id;
        $branchId = (int) $order->branch_id;
        $kots = [];

        if ($voidLines !== []) {
            $kots[] = $this->createKot($order, $companyId, $branchId, $user, 'void', $voidLines);
        }
        if ($addLines !== []) {
            $type = ($order->kitchen_cart_snapshot === null || $order->kitchen_cart_snapshot === []) ? 'full' : 'add';
            $kots[] = $this->createKot($order, $companyId, $branchId, $user, $type, $addLines);
        }

        $order->update([
            'kitchen_cart_snapshot' => $current,
            'status' => $order->status === 'open' ? 'placed' : $order->status,
        ]);

        return $kots;
    }

    /**
     * Original kitchen slips to reprint (same token/KOT numbers — print only, no new ticket).
     *
     * @return \Illuminate\Support\Collection<int, KitchenKot>
     */
    public function kotsForReprint(Order $order): \Illuminate\Support\Collection
    {
        return KitchenKot::query()
            ->where('order_id', $order->id)
            ->where('is_reprint', false)
            ->orderBy('kot_number')
            ->get();
    }

    /**
     * Full VOID slip for order cancel — voids everything currently sent to kitchen.
     */
    public function createOrderCancelVoid(Order $order, User $user): ?KitchenKot
    {
        $lines = array_values($this->normalizeSnapshot($order->kitchen_cart_snapshot ?? []));
        if ($lines === []) {
            return null;
        }

        return DB::transaction(function () use ($order, $user, $lines) {
            $kot = $this->createKot(
                $order,
                (int) $order->company_id,
                (int) $order->branch_id,
                $user,
                'void',
                $lines
            );

            $order->update(['kitchen_cart_snapshot' => []]);

            return $kot;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function snapshotFromCart(array $cartItems): array
    {
        $lines = [];
        foreach ($cartItems as $item) {
            $normalized = $this->normalizeCartLine($item);
            $key = $normalized['line_key'];
            if (isset($lines[$key])) {
                $lines[$key]['quantity'] = round((float) $lines[$key]['quantity'] + (float) $normalized['quantity'], 2);
            } else {
                $lines[$key] = $normalized;
            }
        }

        return array_values($lines);
    }

    /**
     * @param  list<array<string, mixed>>|array<string, mixed>|null  $snapshot
     * @return array<string, array<string, mixed>>
     */
    public function normalizeSnapshot(array|null $snapshot): array
    {
        if (! is_array($snapshot) || $snapshot === []) {
            return [];
        }

        $map = [];
        foreach ($snapshot as $row) {
            if (! is_array($row) || empty($row['line_key'])) {
                continue;
            }
            $key = (string) $row['line_key'];
            $map[$key] = [
                'line_key' => $key,
                'item_name' => (string) ($row['item_name'] ?? 'Item'),
                'quantity' => round((float) ($row['quantity'] ?? 0), 2),
                'special_instructions' => (string) ($row['special_instructions'] ?? ''),
                'variants_label' => (string) ($row['variants_label'] ?? ''),
                'addons_label' => (string) ($row['addons_label'] ?? ''),
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $previous
     * @param  list<array<string, mixed>>  $currentList
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    public function diffSnapshots(array $previous, array $currentList): array
    {
        $current = [];
        foreach ($currentList as $row) {
            $key = (string) $row['line_key'];
            $current[$key] = $row;
        }

        $voidLines = [];
        $addLines = [];

        $allKeys = array_unique(array_merge(array_keys($previous), array_keys($current)));

        foreach ($allKeys as $key) {
            $prevQty = isset($previous[$key]) ? (float) $previous[$key]['quantity'] : 0.0;
            $curQty = isset($current[$key]) ? (float) $current[$key]['quantity'] : 0.0;

            if ($prevQty <= 0 && $curQty <= 0) {
                continue;
            }

            if ($curQty > $prevQty) {
                $addLines[] = $this->slipLine(
                    $current[$key],
                    round($curQty - $prevQty, 2)
                );
            } elseif ($curQty < $prevQty) {
                $voidLines[] = $this->slipLine(
                    $previous[$key],
                    round($prevQty - $curQty, 2)
                );
            }
        }

        return [$voidLines, $addLines];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function normalizeCartLine(array $item): array
    {
        $keyPayload = $this->lineKeyPayload($item);
        $menuItemId = $keyPayload['menu_item_id'];
        $dealId = $keyPayload['deal_id'];
        $variants = $item['variants'] ?? [];
        if (is_string($variants)) {
            $variants = json_decode($variants, true) ?? [];
        }
        $instructions = trim((string) ($item['special_instructions'] ?? ''));
        $variantsLabel = $this->variantsLabel(is_array($variants) ? $variants : []);
        $addons = $item['addons'] ?? [];
        if (is_string($addons)) {
            $addons = json_decode($addons, true) ?? [];
        }
        $addonsLabel = app(PosAddonService::class)->addonsLabel(is_array($addons) ? $addons : null);
        $itemName = $this->resolveItemName($item, $menuItemId, $dealId);

        return [
            'line_key' => sha1(json_encode($keyPayload, JSON_UNESCAPED_UNICODE)),
            'item_name' => $itemName,
            'quantity' => round((float) ($item['quantity'] ?? 0), 2),
            'special_instructions' => $instructions,
            'variants_label' => $variantsLabel,
            'addons_label' => $addonsLabel,
        ];
    }

    /**
     * Stable identity for a cart line (used for KOT diff + bill sync checks).
     *
     * @return array{
     *     menu_item_id: ?int,
     *     deal_id: ?int,
     *     variant_id: ?int,
     *     option_name: ?string,
     *     addons: list<array<string, mixed>>,
     *     special_instructions: string
     * }
     */
    public function lineKeyPayload(array $item): array
    {
        $menuItemId = ! empty($item['menu_item_id']) ? (int) $item['menu_item_id'] : null;
        $dealId = ! empty($item['deal_id']) ? (int) $item['deal_id'] : null;

        $variants = $item['variants'] ?? null;
        if (is_string($variants)) {
            $variants = json_decode($variants, true);
        }
        [$variantId, $optionName] = MenuItem::variantContextFromOrderSelection(
            is_array($variants) ? $variants : null
        );

        $addons = $item['addons'] ?? null;
        if (is_string($addons)) {
            $addons = json_decode($addons, true);
        }

        return [
            'menu_item_id' => $menuItemId,
            'deal_id' => $dealId,
            'variant_id' => $variantId,
            'option_name' => $optionName,
            'addons' => app(PosAddonService::class)->normalizeAddons(is_array($addons) ? $addons : null),
            'special_instructions' => trim((string) ($item['special_instructions'] ?? '')),
        ];
    }

    /**
     * Cart-shaped lines for sendToKitchen from persisted order items.
     *
     * @return list<array<string, mixed>>
     */
    public function cartItemsFromOrder(Order $order): array
    {
        $order->loadMissing('items');

        return $order->items->map(fn ($line) => [
            'menu_item_id' => $line->menu_item_id,
            'deal_id' => $line->deal_id,
            'item_name' => $line->item_name,
            'name' => $line->item_name,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'variants' => $line->variants ?? null,
            'addons' => $line->addons,
            'special_instructions' => $line->special_instructions ?? '',
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function snapshotFromOrderItems(iterable $items): array
    {
        $cartLines = [];
        foreach ($items as $line) {
            $cartLines[] = [
                'menu_item_id' => $line->menu_item_id,
                'deal_id' => $line->deal_id,
                'item_name' => $line->item_name,
                'quantity' => $line->quantity,
                'variants' => $line->variants ?? [],
                'addons' => $line->addons,
                'special_instructions' => $line->special_instructions ?? '',
            ];
        }

        return $this->snapshotFromCart($cartLines);
    }

    /**
     * Compare kitchen snapshot vs current bill snapshot (same line keys and quantities).
     *
     * @param  list<array<string, mixed>>|array<string, mixed>|null  $kitchenSnapshot
     * @param  list<array<string, mixed>>  $billSnapshot
     */
    public function kitchenInSyncWithBill(array|null $kitchenSnapshot, array $billSnapshot): bool
    {
        $kitchen = $this->normalizeSnapshot($kitchenSnapshot);
        $bill = $this->normalizeSnapshot($billSnapshot);

        if ($kitchen === [] && $bill === []) {
            return true;
        }

        $keys = array_unique(array_merge(array_keys($kitchen), array_keys($bill)));

        foreach ($keys as $key) {
            $kitchenQty = round((float) ($kitchen[$key]['quantity'] ?? 0), 2);
            $billQty = round((float) ($bill[$key]['quantity'] ?? 0), 2);

            if ($kitchenQty !== $billQty) {
                return false;
            }
        }

        return true;
    }

    /**
     * Human-readable summary of what the kitchen is currently working from.
     *
     * @param  list<array<string, mixed>>|array<string, mixed>|null  $snapshot
     * @return list<array{item_name: string, quantity: float}>
     */
    public function sentItemsSummary(array|null $snapshot): array
    {
        return array_values(array_map(
            fn (array $row) => [
                'item_name' => (string) ($row['item_name'] ?? 'Item'),
                'quantity' => round((float) ($row['quantity'] ?? 0), 2),
            ],
            $this->normalizeSnapshot($snapshot)
        ));
    }

    /**
     * @return array{sent_items: list<array{item_name: string, quantity: float}>, in_sync: bool, ticket_count: int}
     */
    public function buildKitchenSyncReport(Order $order): array
    {
        $order->loadMissing(['items', 'kitchenKots']);

        $billSnapshot = $this->snapshotFromOrderItems($order->items);
        $sentItems = $this->sentItemsSummary($order->kitchen_cart_snapshot);
        $ticketCount = $order->kitchenKots->where('is_reprint', false)->count();

        return [
            'sent_items' => $sentItems,
            'in_sync' => $this->kitchenInSyncWithBill($order->kitchen_cart_snapshot, $billSnapshot),
            'ticket_count' => $ticketCount,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function createKot(
        Order $order,
        int $companyId,
        int $branchId,
        User $user,
        string $type,
        array $lines
    ): KitchenKot {
        [$kotNumber, $tokenNumber] = $this->nextKotAndToken($branchId);

        return KitchenKot::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'order_id' => $order->id,
            'printed_by' => $user->id,
            'kot_number' => $kotNumber,
            'token_number' => $tokenNumber,
            'type' => $type,
            'lines' => $lines,
            'is_reprint' => false,
            'printed_at' => now(),
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function nextKotAndToken(int $branchId): array
    {
        $businessDate = Carbon::today()->toDateString();

        $counter = DB::table('branch_kitchen_counters')
            ->where('branch_id', $branchId)
            ->where('business_date', $businessDate)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            DB::table('branch_kitchen_counters')->insert([
                'branch_id' => $branchId,
                'business_date' => $businessDate,
                'last_kot_number' => 0,
                'last_token_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = DB::table('branch_kitchen_counters')
                ->where('branch_id', $branchId)
                ->where('business_date', $businessDate)
                ->lockForUpdate()
                ->first();
        }

        $kotNumber = (int) $counter->last_kot_number + 1;
        $tokenNumber = (int) $counter->last_token_number + 1;

        DB::table('branch_kitchen_counters')
            ->where('id', $counter->id)
            ->update([
                'last_kot_number' => $kotNumber,
                'last_token_number' => $tokenNumber,
                'updated_at' => now(),
            ]);

        return [$kotNumber, $tokenNumber];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function slipLine(array $source, float $quantity): array
    {
        return [
            'line_key' => $source['line_key'] ?? '',
            'item_name' => (string) ($source['item_name'] ?? 'Item'),
            'quantity' => $quantity,
            'special_instructions' => (string) ($source['special_instructions'] ?? ''),
            'variants_label' => (string) ($source['variants_label'] ?? ''),
            'addons_label' => (string) ($source['addons_label'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveItemName(array $item, mixed $menuItemId, mixed $dealId): string
    {
        $itemName = trim((string) ($item['item_name'] ?? $item['name'] ?? ''));

        if ($itemName !== '' && $itemName !== 'Item') {
            return $itemName;
        }

        if ($dealId) {
            $deal = Deal::query()->find($dealId);

            return $deal ? (string) $deal->title : ($itemName !== '' ? $itemName : 'Item');
        }

        if ($menuItemId) {
            $menuItem = MenuItem::query()->find($menuItemId);

            return $menuItem ? (string) $menuItem->name : ($itemName !== '' ? $itemName : 'Item');
        }

        return $itemName !== '' ? $itemName : 'Item';
    }

    /**
     * @param  array<int, mixed>  $variants
     */
    private function variantsLabel(array $variants): string
    {
        if ($variants === []) {
            return '';
        }

        if (isset($variants['option_name']) || isset($variants['variant_name'])) {
            $parts = array_filter([
                isset($variants['variant_name']) ? (string) $variants['variant_name'] : null,
                isset($variants['option_name']) ? (string) $variants['option_name'] : null,
            ]);

            return implode(' · ', $parts);
        }

        $parts = [];
        foreach ($variants as $variant) {
            if (is_array($variant)) {
                $name = $variant['name'] ?? $variant['option_name'] ?? $variant['label'] ?? null;
                if ($name) {
                    $parts[] = (string) $name;
                }
            } elseif (is_string($variant) && $variant !== '') {
                $parts[] = $variant;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * @return Collection<int, KitchenKot>
     */
    public function queueForBranch(int $branchId, ?string $businessDate = null): Collection
    {
        $businessDate = $businessDate ?? tz()->businessDate($branchId);

        $query = KitchenKot::query()
            ->withoutGlobalScopes(['tenant', 'branch'])
            ->where('branch_id', $branchId)
            ->where('is_reprint', false)
            ->whereHas('order', function ($orderQuery) {
                $orderQuery
                    ->withoutGlobalScopes(['tenant', 'branch'])
                    ->whereIn('status', OrderWorkflow::kitchenQueueOrderStatuses());
            })
            ->with([
                'order:id,order_number,type,status,payment_status,table_id,waiter_id,customer_name,branch_id',
                'order.table:id,name',
                'order.waiter:id,name',
                'printedBy:id,name',
            ])
            ->orderBy('kot_number');

        tz()->applyLocalDateColumn($query, 'kitchen_kots.created_at', $businessDate, $branchId);

        return $query->limit(200)->get();
    }

    /**
     * @return Collection<int, KitchenKot>
     */
    public function kotsForOrder(Order $order): Collection
    {
        return KitchenKot::query()
            ->where('order_id', $order->id)
            ->orderBy('kot_number')
            ->get();
    }
}
