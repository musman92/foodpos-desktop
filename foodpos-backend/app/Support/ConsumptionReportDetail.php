<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Collection;

class ConsumptionReportDetail
{
    /**
     * Build an audit trail from the same stock movements used by ConsumptionReport.
     *
     * @return array<string, mixed>|null
     */
    public static function build(
        User $user,
        ?int $branchId,
        string $from,
        string $to,
        string $itemType,
        int $itemId
    ): ?array {
        $movements = ConsumptionReport::movementsQuery($user, $branchId, $from, $to)
            ->when(
                $itemType === 'ingredient',
                fn ($query) => $query->where('ingredient_id', $itemId),
                fn ($query) => $query->whereNull('ingredient_id')->where('menu_item_id', $itemId)
            )
            ->with([
                'branch',
                'creator',
                'ingredient.consumptionUnit',
                'menuItem',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        if ($movements->isEmpty()) {
            return null;
        }

        $orderItemMorph = (new OrderItem)->getMorphClass();
        $orderItemIds = $movements
            ->filter(fn ($movement) => in_array($movement->reference_type, [
                OrderItem::class,
                $orderItemMorph,
            ], true))
            ->pluck('reference_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $orderItems = OrderItem::query()
            ->whereIn('id', $orderItemIds)
            ->get()
            ->keyBy('id');
        $orders = Order::withoutGlobalScopes()
            ->withTrashed()
            ->whereIn('id', $orderItems->pluck('order_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $sales = $movements
            ->where('type', 'sale')
            ->groupBy(function ($movement) use ($orderItems) {
                return $orderItems->has((int) $movement->reference_id)
                    ? 'order-item:'.$movement->reference_id
                    : 'movement:'.$movement->id;
            })
            ->map(function (Collection $group) use ($orderItems, $orders) {
                $first = $group->first();
                $orderItem = $orderItems->get((int) $first->reference_id);
                $order = $orderItem ? $orders->get((int) $orderItem->order_id) : null;
                $quantity = round((float) $group->sum(fn ($movement) => (float) $movement->quantity), 2);
                $cost = round((float) $group->sum(
                    fn ($movement) => (float) $movement->quantity * (float) $movement->unit_cost
                ), 2);

                return [
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number ?? self::orderNumberFromNotes($first->notes),
                    'menu_item_name' => $orderItem?->item_name ?? 'Unlinked sale',
                    'quantity' => $quantity,
                    'unit' => $first->ingredient?->consumptionUnit?->name
                        ?: $first->ingredient?->unit_name
                        ?: $first->unit_name
                        ?: ($first->ingredient_id ? '' : 'pcs'),
                    'cost' => $cost,
                    'occurred_at' => $group->max('created_at'),
                    'branch' => $first->branch?->name,
                    'notes' => $group->pluck('notes')->filter()->unique()->implode(' · '),
                    'movement_count' => $group->count(),
                ];
            })
            ->sortByDesc('occurred_at')
            ->values();

        $adjustments = $movements
            ->reject(fn ($movement) => $movement->type === 'sale')
            ->map(function ($movement) use ($orderItems, $orders) {
                $orderItem = $orderItems->get((int) $movement->reference_id);
                $order = $orderItem ? $orders->get((int) $orderItem->order_id) : null;

                return [
                    'movement_id' => $movement->id,
                    'type' => $movement->type,
                    'order_id' => $order?->id,
                    'order_number' => $order?->order_number ?? self::orderNumberFromNotes($movement->notes),
                    'menu_item_name' => $orderItem?->item_name,
                    'quantity' => round((float) $movement->quantity, 2),
                    'unit' => $movement->ingredient?->consumptionUnit?->name
                        ?: $movement->ingredient?->unit_name
                        ?: $movement->unit_name
                        ?: ($movement->ingredient_id ? '' : 'pcs'),
                    'cost' => round((float) $movement->quantity * (float) $movement->unit_cost, 2),
                    'occurred_at' => $movement->created_at,
                    'branch' => $movement->branch?->name,
                    'created_by' => $movement->creator?->name,
                    'notes' => $movement->notes,
                ];
            })
            ->values();

        $first = $movements->first();
        $item = $itemType === 'ingredient' ? $first->ingredient : $first->menuItem;
        $unit = $itemType === 'ingredient'
            ? (string) ($first->ingredient?->consumptionUnit?->name ?: $first->ingredient?->unit_name ?: '')
            : 'pcs';
        $totalQuantity = round((float) $movements->sum(fn ($movement) => (float) $movement->quantity), 2);
        $totalCost = round((float) $movements->sum(
            fn ($movement) => (float) $movement->quantity * (float) $movement->unit_cost
        ), 2);

        return [
            'item' => [
                'type' => $itemType,
                'id' => $itemId,
                'name' => $item?->name ?? ($itemType === 'ingredient' ? 'Ingredient' : 'Menu item'),
                'code' => $item?->sku,
                'unit' => $unit,
            ],
            'summary' => [
                'total_quantity' => $totalQuantity,
                'sales_quantity' => round((float) $movements->where('type', 'sale')
                    ->sum(fn ($movement) => (float) $movement->quantity), 2),
                'adjustment_quantity' => round((float) $movements->where('type', '!=', 'sale')
                    ->sum(fn ($movement) => (float) $movement->quantity), 2),
                'total_cost' => $totalCost,
                'sales_order_count' => $sales->count(),
                'adjustment_count' => $adjustments->count(),
            ],
            'sales' => $sales,
            'adjustments' => $adjustments,
        ];
    }

    private static function orderNumberFromNotes(?string $notes): ?string
    {
        if ($notes && preg_match('/order\s+#?([A-Za-z0-9_-]+)/i', $notes, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
