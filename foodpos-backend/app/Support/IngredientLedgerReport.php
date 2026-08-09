<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IngredientLedgerReport
{
    /**
     * Full purchase / sale / adjustment timeline for one ingredient.
     *
     * @return array{
     *     ingredient: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     opening: array<string, float|string>,
     *     rows: Collection<int, array<string, mixed>>,
     *     batches: Collection<int, array<string, mixed>>
     * }|null
     */
    public static function build(
        User $user,
        int $ingredientId,
        ?int $branchId,
        string $from,
        string $to
    ): ?array {
        $ingredient = Ingredient::query()
            ->withoutGlobalScopes()
            ->with([
                'category',
                'consumptionUnit' => fn ($q) => $q->withoutGlobalScopes(),
                'purchaseUnit' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->whereKey($ingredientId)
            ->first();

        if (! $ingredient) {
            return null;
        }

        if (! $user->isSuperAdmin() && (int) $ingredient->company_id !== (int) $user->company_id) {
            return null;
        }

        $consumptionUnit = (string) ($ingredient->consumptionUnit?->name ?: $ingredient->unit_name ?: '');
        $purchaseUnit = (string) ($ingredient->purchaseUnit?->name ?: $consumptionUnit);

        $purchaseEvents = self::purchaseEvents($user, $ingredient, $branchId, $from, $to);
        $purchaseReturnEvents = self::purchaseReturnEvents($user, $ingredient, $branchId, $from, $to);
        $movementEvents = self::movementEvents($user, $ingredient, $branchId, $from, $to);

        $events = $purchaseEvents
            ->concat($purchaseReturnEvents)
            ->concat($movementEvents)
            ->sortBy(function (array $event) {
                $ts = $event['occurred_at']?->getTimestamp() ?? 0;

                return sprintf('%015d-%s', $ts, $event['sort_key']);
            })
            ->values();

        $currentOnHand = self::currentOnHand($user, $ingredient->id, $branchId);
        $periodNet = round((float) $events->sum('signed_qty'), 4);
        $openingQty = round($currentOnHand - $periodNet, 4);

        $running = $openingQty;
        $rows = $events->map(function (array $event) use (&$running, $ingredient) {
            $running = round($running + (float) $event['signed_qty'], 4);
            $event['balance_qty'] = $running;
            $event['balance_purchase_qty'] = round($ingredient->toPurchaseQuantity($running), 4);
            $event['qty_purchase'] = round(
                $ingredient->toPurchaseQuantity(abs((float) $event['signed_qty'])),
                4
            );
            if ((float) $event['signed_qty'] < 0) {
                $event['qty_purchase'] = -1 * abs((float) $event['qty_purchase']);
            }

            return $event;
        });

        $purchasedIn = round((float) $events->where('kind', 'purchase')->sum('signed_qty'), 4);
        $returnedOut = round(abs((float) $events->where('kind', 'purchase_return')->sum('signed_qty')), 4);
        $soldOut = round(abs((float) $events->where('kind', 'sale')->sum('signed_qty')), 4);
        $adjustedIn = round((float) $events->where('kind', 'adjustment_in')->sum('signed_qty'), 4);
        $adjustedOut = round(abs((float) $events
            ->whereIn('kind', ['adjustment_out', 'waste'])
            ->sum('signed_qty')), 4);

        return [
            'ingredient' => [
                'id' => (int) $ingredient->id,
                'name' => (string) $ingredient->name,
                'sku' => $ingredient->sku,
                'category' => $ingredient->category?->name,
                'track_stock' => (string) $ingredient->track_stock,
                'conversion_rate' => (float) ($ingredient->conversion_rate ?: 1),
                'consumption_unit' => $consumptionUnit,
                'purchase_unit' => $purchaseUnit,
            ],
            'summary' => [
                'purchased_qty' => $purchasedIn,
                'purchased_purchase_qty' => round($ingredient->toPurchaseQuantity($purchasedIn), 4),
                'returned_qty' => $returnedOut,
                'returned_purchase_qty' => round($ingredient->toPurchaseQuantity($returnedOut), 4),
                'sold_qty' => $soldOut,
                'sold_purchase_qty' => round($ingredient->toPurchaseQuantity($soldOut), 4),
                'adjusted_in_qty' => $adjustedIn,
                'adjusted_in_purchase_qty' => round($ingredient->toPurchaseQuantity($adjustedIn), 4),
                'adjusted_out_qty' => $adjustedOut,
                'adjusted_out_purchase_qty' => round($ingredient->toPurchaseQuantity($adjustedOut), 4),
                'current_qty' => round($currentOnHand, 4),
                'current_purchase_qty' => round($ingredient->toPurchaseQuantity($currentOnHand), 4),
                'event_count' => $rows->count(),
            ],
            'opening' => [
                'qty' => $openingQty,
                'purchase_qty' => round($ingredient->toPurchaseQuantity($openingQty), 4),
                'consumption_unit' => $consumptionUnit,
                'purchase_unit' => $purchaseUnit,
            ],
            'rows' => $rows,
            'batches' => self::currentBatches($user, $ingredient, $branchId),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, sku: ?string}>
     */
    public static function ingredientsForUser(User $user): Collection
    {
        $query = Ingredient::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('name');

        if (! $user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        return $query->get(['id', 'name', 'sku'])->map(fn (Ingredient $ingredient) => [
            'id' => (int) $ingredient->id,
            'name' => (string) $ingredient->name,
            'sku' => $ingredient->sku,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function purchaseEvents(
        User $user,
        Ingredient $ingredient,
        ?int $branchId,
        string $from,
        string $to
    ): Collection {
        $purchaseQuery = Purchase::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at');

        self::applyPurchaseBranchScope($purchaseQuery, $user, $branchId);
        tz()->applyBusinessDateRange($purchaseQuery, $from, $to, $branchId);

        $purchases = $purchaseQuery
            ->with(['supplier:id,name', 'branch:id,name', 'creator:id,name'])
            ->whereHas('items', function (Builder $items) use ($ingredient) {
                $items->where('item_type', 'ingredient')
                    ->where('item_id', $ingredient->id);
            })
            ->get()
            ->keyBy('id');

        if ($purchases->isEmpty()) {
            return collect();
        }

        $items = PurchaseItem::query()
            ->whereIn('purchase_id', $purchases->keys())
            ->where('item_type', 'ingredient')
            ->where('item_id', $ingredient->id)
            ->get();

        return $items->map(function (PurchaseItem $item) use ($purchases, $ingredient) {
            $purchase = $purchases->get($item->purchase_id);
            $unitKey = $item->unit_id !== null && $item->unit_id !== '' ? (string) $item->unit_id : null;
            $consumptionQty = IngredientQuantity::toConsumptionQuantity(
                $ingredient,
                (float) $item->quantity,
                $unitKey
            );
            if ($consumptionQty === null) {
                $consumptionQty = $ingredient->toConsumptionQuantity((float) $item->quantity);
            }
            $consumptionQty = round((float) $consumptionQty, 4);
            $occurredAt = $purchase?->created_at;

            return [
                'kind' => 'purchase',
                'kind_label' => 'Purchase',
                'direction' => 'in',
                'signed_qty' => $consumptionQty,
                'quantity' => $consumptionQty,
                'unit_cost' => $consumptionQty > 0
                    ? round((float) $item->total_price / $consumptionQty, 4)
                    : 0.0,
                'line_cost' => round((float) $item->total_price, 2),
                'occurred_at' => $occurredAt,
                'business_date' => $purchase?->business_date?->toDateString()
                    ?? $purchase?->purchase_date?->toDateString(),
                'branch' => $purchase?->branch?->name,
                'created_by' => $purchase?->creator?->name,
                'reference_label' => $purchase?->purchase_number ?? ('Purchase #'.$item->purchase_id),
                'reference_type' => 'purchase',
                'reference_id' => (int) $item->purchase_id,
                'detail' => $purchase?->supplier?->name
                    ? 'Supplier: '.$purchase->supplier->name
                    : null,
                'notes' => $item->notes ?: $purchase?->notes,
                'sort_key' => sprintf('purchase-%010d-%010d', $item->purchase_id, $item->id),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function purchaseReturnEvents(
        User $user,
        Ingredient $ingredient,
        ?int $branchId,
        string $from,
        string $to
    ): Collection {
        $returnQuery = PurchaseReturn::query()->withoutGlobalScopes();

        self::applyPurchaseBranchScope($returnQuery, $user, $branchId);
        tz()->applyBusinessDateRange($returnQuery, $from, $to, $branchId);

        $returns = $returnQuery
            ->with([
                'supplier:id,name',
                'branch:id,name',
                'creator:id,name',
                'purchase:id,purchase_number',
            ])
            ->whereHas('items.purchaseItem', function (Builder $items) use ($ingredient) {
                $items->where('item_type', 'ingredient')
                    ->where('item_id', $ingredient->id);
            })
            ->get()
            ->keyBy('id');

        if ($returns->isEmpty()) {
            return collect();
        }

        $lines = PurchaseReturnItem::query()
            ->with('purchaseItem')
            ->whereIn('purchase_return_id', $returns->keys())
            ->whereHas('purchaseItem', function (Builder $items) use ($ingredient) {
                $items->where('item_type', 'ingredient')
                    ->where('item_id', $ingredient->id);
            })
            ->get();

        return $lines->map(function (PurchaseReturnItem $line) use ($returns, $ingredient) {
            $return = $returns->get($line->purchase_return_id);
            $purchaseItem = $line->purchaseItem;
            $qtyInPurchaseUnits = (float) ($line->stock_reversed_qty ?: $line->quantity);
            $unitKey = $purchaseItem && $purchaseItem->unit_id !== null && $purchaseItem->unit_id !== ''
                ? (string) $purchaseItem->unit_id
                : null;

            $consumptionQty = IngredientQuantity::toConsumptionQuantity(
                $ingredient,
                $qtyInPurchaseUnits,
                $unitKey
            );
            if ($consumptionQty === null) {
                $consumptionQty = $ingredient->toConsumptionQuantity($qtyInPurchaseUnits);
            }
            $consumptionQty = round((float) $consumptionQty, 4);
            $occurredAt = $return?->created_at;

            $purchaseNumber = $return?->purchase?->purchase_number;
            $detailParts = array_values(array_filter([
                $return?->supplier?->name ? 'Supplier: '.$return->supplier->name : null,
                $purchaseNumber ? 'Purchase: '.$purchaseNumber : null,
            ]));

            return [
                'kind' => 'purchase_return',
                'kind_label' => 'Purchase return',
                'direction' => 'out',
                'signed_qty' => -1 * $consumptionQty,
                'quantity' => $consumptionQty,
                'unit_cost' => $consumptionQty > 0
                    ? round((float) $line->total_price / $consumptionQty, 4)
                    : 0.0,
                'line_cost' => round((float) $line->total_price, 2),
                'occurred_at' => $occurredAt,
                'business_date' => $return?->business_date?->toDateString()
                    ?? $return?->return_date?->toDateString(),
                'branch' => $return?->branch?->name,
                'created_by' => $return?->creator?->name,
                'reference_label' => $return?->return_number ?? ('Return #'.$line->purchase_return_id),
                'reference_type' => 'purchase_return',
                'reference_id' => (int) $line->purchase_return_id,
                'detail' => $detailParts !== [] ? implode(' · ', $detailParts) : null,
                'notes' => $line->notes ?: $return?->notes,
                'sort_key' => sprintf('purchase-return-%010d-%010d', $line->purchase_return_id, $line->id),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function movementEvents(
        User $user,
        Ingredient $ingredient,
        ?int $branchId,
        string $from,
        string $to
    ): Collection {
        $query = StockMovement::query()
            ->withoutGlobalScope('branch')
            ->where('ingredient_id', $ingredient->id)
            ->whereIn('type', ['sale', 'adjustment', 'waste']);

        tz()->applyBusinessDateRange($query, $from, $to, $branchId);
        self::applyMovementBranchScope($query, $user, $branchId);

        $movements = $query
            ->with(['branch:id,name', 'creator:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            return collect();
        }

        $orderItemMorph = (new OrderItem)->getMorphClass();
        $orderItemIds = $movements
            ->filter(fn (StockMovement $movement) => in_array($movement->reference_type, [
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

        return $movements->map(function (StockMovement $movement) use ($orderItems, $orders) {
            $qty = round((float) $movement->quantity, 4);
            $isIn = $movement->movement === 'in';
            $signed = $isIn ? $qty : -1 * $qty;

            $kind = match (true) {
                $movement->type === 'sale' => 'sale',
                $movement->type === 'waste' => 'waste',
                $isIn => 'adjustment_in',
                default => 'adjustment_out',
            };

            $orderItem = $orderItems->get((int) $movement->reference_id);
            $order = $orderItem ? $orders->get((int) $orderItem->order_id) : null;

            $referenceLabel = null;
            $referenceType = null;
            $referenceId = null;
            $detail = null;

            if ($order) {
                $referenceLabel = $order->order_number ?? ('Order #'.$order->id);
                $referenceType = 'order';
                $referenceId = (int) $order->id;
                $detail = $orderItem?->item_name;
            } elseif ($movement->type === 'adjustment' || $movement->type === 'waste') {
                $referenceLabel = 'Adjustment #'.$movement->id;
                $referenceType = 'adjustment';
                $referenceId = (int) $movement->id;
            }

            return [
                'kind' => $kind,
                'kind_label' => match ($kind) {
                    'sale' => 'Sale',
                    'waste' => 'Waste',
                    'adjustment_in' => 'Adjustment in',
                    default => 'Adjustment out',
                },
                'direction' => $isIn ? 'in' : 'out',
                'signed_qty' => $signed,
                'quantity' => $qty,
                'unit_cost' => round((float) $movement->unit_cost, 4),
                'line_cost' => round($qty * (float) $movement->unit_cost, 2),
                'occurred_at' => $movement->created_at,
                'business_date' => $movement->business_date?->toDateString(),
                'branch' => $movement->branch?->name,
                'created_by' => $movement->creator?->name,
                'reference_label' => $referenceLabel,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'detail' => $detail,
                'notes' => $movement->notes,
                'sort_key' => sprintf('movement-%010d', $movement->id),
            ];
        });
    }

    private static function currentOnHand(User $user, int $ingredientId, ?int $branchId): float
    {
        $query = BranchStock::query()
            ->withoutGlobalScopes()
            ->where('ingredient_id', $ingredientId);

        self::applyMovementBranchScope($query, $user, $branchId);

        return round((float) $query->sum('quantity'), 4);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function currentBatches(User $user, Ingredient $ingredient, ?int $branchId): Collection
    {
        $query = BranchStock::query()
            ->withoutGlobalScopes()
            ->with('branch:id,name')
            ->where('ingredient_id', $ingredient->id)
            ->where('quantity', '>', 0)
            ->orderBy('created_at');

        self::applyMovementBranchScope($query, $user, $branchId);

        return $query->get()->map(function (BranchStock $batch) use ($ingredient) {
            $qty = round((float) $batch->quantity, 4);

            return [
                'branch' => $batch->branch?->name,
                'quantity' => $qty,
                'purchase_quantity' => round($ingredient->toPurchaseQuantity($qty), 4),
                'unit_cost' => round((float) $batch->average_cost, 4),
                'last_restocked_at' => $batch->last_restocked_at,
            ];
        });
    }

    private static function applyPurchaseBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return;
        }

        $query->where('company_id', $user->company_id);

        if ($branchId) {
            $query->where('branch_id', $branchId);

            return;
        }

        $allowed = self::allowedBranchIds($user);
        $query->whereIn('branch_id', $allowed !== [] ? $allowed : [-1]);
    }

    private static function applyMovementBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return;
        }

        $companyBranchIds = Branch::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        if ($branchId) {
            $query->where('branch_id', $branchId);

            return;
        }

        $allowed = array_values(array_intersect(self::allowedBranchIds($user), $companyBranchIds));
        $query->whereIn('branch_id', $allowed !== [] ? $allowed : [-1]);
    }

    /**
     * @return list<int>
     */
    private static function allowedBranchIds(User $user): array
    {
        if ($user->isCompanyAdmin()) {
            return Branch::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $fromPivot = $user->branches()
            ->where('status', 'active')
            ->pluck('branches.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($fromPivot !== []) {
            return $fromPivot;
        }

        return $user->branch_id ? [(int) $user->branch_id] : [];
    }
}
