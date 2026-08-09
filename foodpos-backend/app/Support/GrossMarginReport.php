<?php

namespace App\Support;

use App\Models\MenuItem;
use App\Services\MenuItemCostService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GrossMarginReport
{
    public static function filtersFromRequest(Request $request): array
    {
        $sort = (string) $request->input('sort', 'margin_percent');
        $allowedSorts = ['name', 'category', 'price', 'cost', 'margin', 'margin_percent', 'type', 'status'];

        return [
            'search' => trim((string) $request->input('search', '')),
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'type' => in_array($request->input('type'), ['all', 'recipe', 'single'], true)
                ? $request->input('type')
                : 'all',
            'availability' => in_array($request->input('availability'), ['all', 'available', 'unavailable'], true)
                ? $request->input('availability')
                : 'all',
            'min_margin' => $request->filled('min_margin') ? (float) $request->input('min_margin') : null,
            'max_margin' => $request->filled('max_margin') ? (float) $request->input('max_margin') : null,
            'sort' => in_array($sort, $allowedSorts, true) ? $sort : 'margin_percent',
            'dir' => strtolower((string) $request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc',
            'per_page' => min(100, max(10, (int) $request->input('per_page', 25))),
        ];
    }

    /**
     * @return array{filters: array, rows: Collection<int, array>}
     */
    public static function build(Request $request): array
    {
        $filters = self::filtersFromRequest($request);
        $rows = self::queryRows($filters);

        return compact('filters', 'rows');
    }

    public static function paginate(Request $request): LengthAwarePaginator
    {
        $built = self::build($request);

        return self::paginateCollection($built['rows'], $built['filters']['per_page'], $request);
    }

    protected static function queryRows(array $filters): Collection
    {
        $query = MenuItem::with(['category', 'defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient', 'legacyRecipeLines.ingredient']);

        if ($filters['search'] !== '') {
            $query->where('name', 'like', '%'.CatalogListingQuery::escapeLike($filters['search']).'%');
        }

        if ($filters['category_id']) {
            $query->where('category_id', $filters['category_id']);
        }

        if ($filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if ($filters['availability'] === 'available') {
            $query->where('is_available', true);
        } elseif ($filters['availability'] === 'unavailable') {
            $query->where('is_available', false);
        }

        $rows = $query->get()->map(fn (MenuItem $item) => self::rowFromMenuItem($item));

        if ($filters['min_margin'] !== null) {
            $rows = $rows->filter(
                fn (array $row) => $row['margin_percent'] !== null && $row['margin_percent'] >= $filters['min_margin']
            );
        }

        if ($filters['max_margin'] !== null) {
            $rows = $rows->filter(
                fn (array $row) => $row['margin_percent'] !== null && $row['margin_percent'] <= $filters['max_margin']
            );
        }

        return self::sortRows($rows, $filters['sort'], $filters['dir']);
    }

    public static function summary(Collection $rows): array
    {
        $withMargin = $rows->filter(fn (array $row) => $row['margin_percent'] !== null);

        return [
            'item_count' => $rows->count(),
            'avg_margin_percent' => $withMargin->isNotEmpty()
                ? $withMargin->avg('margin_percent')
                : null,
            'negative_margin_count' => $rows->filter(fn (array $row) => $row['margin'] < 0)->count(),
            'stale_cost_count' => $rows->filter(fn (array $row) => $row['cost_is_stale'])->count(),
        ];
    }

    public static function sortUrl(Request $request, string $column): string
    {
        $filters = self::filtersFromRequest($request);
        $dir = $filters['sort'] === $column && $filters['dir'] === 'asc' ? 'desc' : 'asc';

        return $request->fullUrlWithQuery([
            'sort' => $column,
            'dir' => $dir,
            'page' => 1,
        ]);
    }

    public static function sortIcon(Request $request, string $column): string
    {
        $filters = self::filtersFromRequest($request);

        if ($filters['sort'] !== $column) {
            return 'fa-sort text-gray-300';
        }

        return $filters['dir'] === 'asc' ? 'fa-sort-up text-indigo-600' : 'fa-sort-down text-indigo-600';
    }

    protected static function rowFromMenuItem(MenuItem $item): array
    {
        $price = (float) $item->price;
        $storedCost = (float) $item->cost;

        if ($item->type === 'recipe') {
            $cost = $item->calculateCost();
            $costIsStale = abs($cost - $storedCost) > 0.01;
        } else {
            $cost = $storedCost;
            $liveCost = app(MenuItemCostService::class)->weightedAverageFromStock($item);
            $costIsStale = $item->track_inventory
                && $liveCost !== null
                && abs($storedCost - $liveCost) > 0.01;
        }

        $margin = $price - $cost;
        $marginPercent = $price > 0 ? ($margin / $price) * 100 : null;

        return [
            'menu_item' => $item,
            'price' => $price,
            'cost' => $cost,
            'stored_cost' => $storedCost,
            'cost_is_stale' => $costIsStale,
            'margin' => $margin,
            'margin_percent' => $marginPercent,
            'category_name' => $item->category?->name ?? '',
        ];
    }

    protected static function sortRows(Collection $rows, string $sort, string $dir): Collection
    {
        $sorted = $rows->sortBy(function (array $row) use ($sort) {
            $item = $row['menu_item'];

            return match ($sort) {
                'name' => strtolower($item->name),
                'category' => strtolower($row['category_name']),
                'price' => $row['price'],
                'cost' => $row['cost'],
                'margin' => $row['margin'],
                'margin_percent' => $row['margin_percent'] ?? -9999,
                'type' => $item->type,
                'status' => $item->is_available ? 1 : 0,
                default => $row['margin_percent'] ?? -9999,
            };
        }, SORT_REGULAR);

        return $dir === 'desc' ? $sorted->reverse()->values() : $sorted->values();
    }

    public static function paginateCollection(Collection $rows, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->input('page', 1));
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
