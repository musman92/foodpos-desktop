<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\MenuItemStock;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ConsumptionReport
{
    /**
     * @return array{
     *   summary: array{
     *     total_cost: float,
     *     sales_cost: float,
     *     adjustment_cost: float,
     *     item_count: int
     *   },
     *   rows: Collection<int, array{
     *     item_type: string,
     *     item_type_label: string,
     *     item_id: int,
     *     name: string,
     *     code: ?string,
     *     category: ?string,
     *     unit: string,
     *     quantity_unit: string,
     *     remaining_stock_unit: string,
     *     quantity: float,
     *     remaining_stock: float,
     *     avg_unit_cost: float,
     *     total_cost: float,
     *     sales_quantity: float,
     *     sales_cost: float,
     *     adjustment_quantity: float,
     *     adjustment_cost: float
     *   }>
     * }
     */
    public static function build(
        User $user,
        ?int $branchId,
        string $from,
        string $to,
        string $search = '',
        ?int $categoryId = null,
        ?int $menuItemId = null
    ): array {
        $query = self::movementsQuery($user, $branchId, $from, $to, $categoryId, $menuItemId)
            ->with([
                'ingredient.category',
                'ingredient.consumptionUnit',
                'ingredient.purchaseUnit',
                'menuItem.category',
            ]);

        $movements = $query->get();

        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];

        foreach ($movements as $movement) {
            $itemType = $movement->ingredient_id ? 'ingredient' : 'menu_item';
            $itemId = (int) ($movement->ingredient_id ?: $movement->menu_item_id);
            $key = "{$itemType}:{$itemId}";

            if (! isset($grouped[$key])) {
                $grouped[$key] = self::emptyRow($movement, $itemType, $itemId);
            }

            $qty = round((float) $movement->quantity, 2);
            $cost = round($qty * (float) $movement->unit_cost, 2);

            $grouped[$key]['quantity'] = round($grouped[$key]['quantity'] + $qty, 2);
            $grouped[$key]['total_cost'] = round($grouped[$key]['total_cost'] + $cost, 2);

            if (in_array($movement->type, ['sale'], true)) {
                $grouped[$key]['sales_quantity'] = round($grouped[$key]['sales_quantity'] + $qty, 2);
                $grouped[$key]['sales_cost'] = round($grouped[$key]['sales_cost'] + $cost, 2);
            } else {
                $grouped[$key]['adjustment_quantity'] = round($grouped[$key]['adjustment_quantity'] + $qty, 2);
                $grouped[$key]['adjustment_cost'] = round($grouped[$key]['adjustment_cost'] + $cost, 2);
            }
        }

        $searchTerms = self::searchTerms($search);
        $stockByKey = self::remainingStockByKey(array_keys($grouped), $user, $branchId);
        $ingredientUnits = self::ingredientUnitMeta(array_keys($grouped));

        $rows = collect($grouped)
            ->map(function (array $row) use ($stockByKey, $ingredientUnits) {
                $key = "{$row['item_type']}:{$row['item_id']}";
                $consumptionRemaining = (float) ($stockByKey[$key] ?? 0);

                if ($row['item_type'] === 'ingredient') {
                    $meta = $ingredientUnits[$row['item_id']] ?? null;
                    $row['quantity_unit'] = (string) ($meta['consumption_unit'] ?? $row['quantity_unit'] ?? '');
                    $row['remaining_stock_unit'] = (string) ($meta['purchase_unit'] ?? $row['quantity_unit']);
                    $row['remaining_stock'] = $meta
                        ? round($meta['to_purchase']($consumptionRemaining), 4)
                        : round($consumptionRemaining, 2);
                    $row['unit'] = $row['quantity_unit'];
                } else {
                    $row['remaining_stock'] = round($consumptionRemaining, 2);
                    $row['quantity_unit'] = $row['quantity_unit'] ?: 'pcs';
                    $row['remaining_stock_unit'] = $row['remaining_stock_unit'] ?: 'pcs';
                    $row['unit'] = $row['quantity_unit'];
                }

                $row['avg_unit_cost'] = $row['quantity'] > 0
                    ? round($row['total_cost'] / $row['quantity'], 4)
                    : 0.0;

                return $row;
            })
            ->when($searchTerms !== [], function (Collection $collection) use ($searchTerms) {
                return $collection->filter(fn (array $row) => self::rowMatchesSearch($row, $searchTerms));
            })
            ->sortBy([
                ['item_type_label', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        $totalCost = round((float) $rows->sum('total_cost'), 2);
        $salesCost = round((float) $rows->sum('sales_cost'), 2);
        $adjustmentCost = round((float) $rows->sum('adjustment_cost'), 2);

        return [
            'summary' => [
                'total_cost' => $totalCost,
                'sales_cost' => $salesCost,
                'adjustment_cost' => $adjustmentCost,
                'item_count' => $rows->count(),
            ],
            'rows' => $rows,
        ];
    }

    public static function movementsQuery(
        User $user,
        ?int $branchId,
        string $from,
        string $to,
        ?int $categoryId = null,
        ?int $menuItemId = null
    ): Builder {
        $query = StockMovement::query()
            ->withoutGlobalScope('branch')
            ->where('movement', 'out')
            ->whereIn('type', ['sale', 'adjustment', 'waste'])
            ->where(function (Builder $q) {
                $q->whereNotNull('ingredient_id')
                    ->orWhereNotNull('menu_item_id');
            });

        tz()->applyBusinessDateRange($query, $from, $to, $branchId);

        self::applyBranchScope($query, $user, $branchId);
        self::applyMenuFilters($query, $user, $categoryId, $menuItemId);

        return $query;
    }

    /**
     * Limit movements to sales of selected menu item(s), or menu-item stock rows for those items.
     */
    private static function applyMenuFilters(
        Builder $query,
        User $user,
        ?int $categoryId,
        ?int $menuItemId
    ): void {
        if (! $categoryId && ! $menuItemId) {
            return;
        }

        $menuItemIds = self::resolveMenuItemIds($user, $categoryId, $menuItemId);
        if ($menuItemIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $orderItemType = OrderItem::class;

        $query->where(function (Builder $q) use ($menuItemIds, $orderItemType) {
            $q->whereIn('menu_item_id', $menuItemIds)
                ->orWhere(function (Builder $inner) use ($menuItemIds, $orderItemType) {
                    $inner->whereNotNull('ingredient_id')
                        ->where('reference_type', $orderItemType)
                        ->whereIn('reference_id', function ($sub) use ($menuItemIds) {
                            $sub->select('id')
                                ->from('order_items')
                                ->whereIn('menu_item_id', $menuItemIds);
                        });
                });
        });
    }

    /**
     * @return list<int>
     */
    private static function resolveMenuItemIds(User $user, ?int $categoryId, ?int $menuItemId): array
    {
        $query = MenuItem::query()->withoutGlobalScopes();

        if (! $user->isSuperAdmin()) {
            $query->where('company_id', (int) $user->company_id);
        }

        if ($menuItemId) {
            $query->whereKey($menuItemId);
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $id = $query->value('id');

            return $id ? [(int) $id] : [];
        }

        return $query
            ->where('category_id', $categoryId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyRow(StockMovement $movement, string $itemType, int $itemId): array
    {
        if ($itemType === 'ingredient') {
            $ingredient = $movement->ingredient;
            $consumptionUnit = (string) ($ingredient?->consumptionUnit?->name
                ?? $ingredient?->unit_name
                ?? '');
            $purchaseUnit = (string) ($ingredient?->purchaseUnit?->name
                ?: $consumptionUnit);

            return [
                'item_type' => 'ingredient',
                'item_type_label' => 'Ingredient',
                'item_id' => $itemId,
                'name' => (string) ($ingredient?->name ?? 'Ingredient'),
                'code' => $ingredient?->sku,
                'category' => $ingredient?->category?->name,
                'unit' => $consumptionUnit,
                'quantity_unit' => $consumptionUnit,
                'remaining_stock_unit' => $purchaseUnit,
                'quantity' => 0.0,
                'remaining_stock' => 0.0,
                'avg_unit_cost' => 0.0,
                'total_cost' => 0.0,
                'sales_quantity' => 0.0,
                'sales_cost' => 0.0,
                'adjustment_quantity' => 0.0,
                'adjustment_cost' => 0.0,
            ];
        }

        $menuItem = $movement->menuItem;

        return [
            'item_type' => 'menu_item',
            'item_type_label' => 'Menu item',
            'item_id' => $itemId,
            'name' => (string) ($menuItem?->name ?? 'Menu item'),
            'code' => $menuItem?->sku,
            'category' => $menuItem?->category?->name,
            'unit' => 'pcs',
            'quantity_unit' => 'pcs',
            'remaining_stock_unit' => 'pcs',
            'quantity' => 0.0,
            'remaining_stock' => 0.0,
            'avg_unit_cost' => 0.0,
            'total_cost' => 0.0,
            'sales_quantity' => 0.0,
            'sales_cost' => 0.0,
            'adjustment_quantity' => 0.0,
            'adjustment_cost' => 0.0,
        ];
    }

    private static function applyBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return;
        }

        $companyId = (int) $user->company_id;
        $branchIds = Branch::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->pluck('id');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $allowed = $user->isCompanyAdmin()
                ? $branchIds
                : ($user->branches()->where('status', 'active')->pluck('branches.id')->isNotEmpty()
                    ? $user->branches()->where('status', 'active')->pluck('branches.id')
                    : collect($user->branch_id ? [$user->branch_id] : []));

            $query->whereIn('branch_id', $allowed->intersect($branchIds)->all() ?: [-1]);
        }
    }

    /**
     * Unit labels and purchase conversion for ingredients.
     * Qty used is in consumption units; remaining stock is shown in purchase units.
     *
     * @param  list<string>  $keys
     * @return array<int, array{consumption_unit: string, purchase_unit: string, to_purchase: callable(float): float}>
     */
    private static function ingredientUnitMeta(array $keys): array
    {
        $ingredientIds = [];
        foreach ($keys as $key) {
            [$type, $id] = array_pad(explode(':', $key, 2), 2, null);
            if ($type === 'ingredient' && (int) $id > 0) {
                $ingredientIds[] = (int) $id;
            }
        }

        if ($ingredientIds === []) {
            return [];
        }

        $ingredients = Ingredient::query()
            ->withoutGlobalScopes()
            ->with([
                'consumptionUnit' => fn ($q) => $q->withoutGlobalScopes(),
                'purchaseUnit' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->whereIn('id', array_values(array_unique($ingredientIds)))
            ->get()
            ->keyBy('id');

        $meta = [];
        foreach ($ingredients as $id => $ingredient) {
            $consumption = (string) ($ingredient->consumptionUnit?->name ?: $ingredient->unit_name ?: '');
            $purchase = (string) ($ingredient->purchaseUnit?->name ?: $consumption);

            $meta[(int) $id] = [
                'consumption_unit' => $consumption,
                'purchase_unit' => $purchase,
                'to_purchase' => fn (float $qty) => $ingredient->toPurchaseQuantity($qty),
            ];
        }

        return $meta;
    }

    /**
     * Current on-hand stock keyed as "{item_type}:{item_id}".
     * Ingredient quantities are in consumption (stock) units.
     *
     * @param  list<string>  $keys
     * @return array<string, float>
     */
    private static function remainingStockByKey(array $keys, User $user, ?int $branchId): array
    {
        if ($keys === []) {
            return [];
        }

        $ingredientIds = [];
        $menuItemIds = [];
        foreach ($keys as $key) {
            [$type, $id] = array_pad(explode(':', $key, 2), 2, null);
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            if ($type === 'ingredient') {
                $ingredientIds[] = $id;
            } elseif ($type === 'menu_item') {
                $menuItemIds[] = $id;
            }
        }

        $branchIds = self::stockBranchIds($user, $branchId);
        if ($branchIds === []) {
            return [];
        }

        $stock = [];

        if ($ingredientIds !== []) {
            $rows = BranchStock::query()
                ->withoutGlobalScopes()
                ->whereIn('branch_id', $branchIds)
                ->whereIn('ingredient_id', array_values(array_unique($ingredientIds)))
                ->selectRaw('ingredient_id, SUM(quantity) as qty')
                ->groupBy('ingredient_id')
                ->pluck('qty', 'ingredient_id');

            foreach ($rows as $ingredientId => $qty) {
                $stock['ingredient:'.$ingredientId] = (float) $qty;
            }
        }

        if ($menuItemIds !== []) {
            $rows = MenuItemStock::query()
                ->withoutGlobalScopes()
                ->whereIn('branch_id', $branchIds)
                ->whereIn('menu_item_id', array_values(array_unique($menuItemIds)))
                ->selectRaw('menu_item_id, SUM(quantity) as qty')
                ->groupBy('menu_item_id')
                ->pluck('qty', 'menu_item_id');

            foreach ($rows as $menuItemId => $qty) {
                $stock['menu_item:'.$menuItemId] = (float) $qty;
            }
        }

        return $stock;
    }

    /**
     * @return list<int>
     */
    private static function stockBranchIds(User $user, ?int $branchId): array
    {
        if ($user->isSuperAdmin()) {
            if ($branchId) {
                return [$branchId];
            }

            return Branch::query()->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $companyId = (int) $user->company_id;
        $companyBranchIds = Branch::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($branchId) {
            return [$branchId];
        }

        $allowed = $user->isCompanyAdmin()
            ? $companyBranchIds
            : ($user->branches()->where('status', 'active')->pluck('branches.id')->isNotEmpty()
                ? $user->branches()->where('status', 'active')->pluck('branches.id')->map(fn ($id) => (int) $id)
                : collect($user->branch_id ? [(int) $user->branch_id] : []));

        return $allowed->intersect($companyBranchIds)->values()->all();
    }

    /**
     * @return list<string>
     */
    private static function searchTerms(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $terms = preg_split('/\s*,\s*/', $search) ?: [];
        $terms = array_values(array_filter(array_map('trim', $terms), fn (string $term) => $term !== ''));

        return array_values(array_unique($terms));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $terms
     */
    private static function rowMatchesSearch(array $row, array $terms): bool
    {
        $haystacks = [
            mb_strtolower((string) ($row['name'] ?? '')),
            mb_strtolower((string) ($row['code'] ?? '')),
        ];

        foreach ($terms as $term) {
            $needle = mb_strtolower($term);
            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
