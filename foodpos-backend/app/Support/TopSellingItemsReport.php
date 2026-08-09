<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class TopSellingItemsReport
{
    /**
     * Top menu items / deals by total quantity, with optional variant breakdown rows.
     *
     * @return Collection<int, object{
     *     item_name: string,
     *     menu_item_id: ?int,
     *     deal_id: ?int,
     *     total_quantity: float,
     *     total_revenue: float,
     *     variants: Collection<int, object{label: string, total_quantity: float, total_revenue: float}>
     * }>
     */
    public static function aggregate(Builder $orderItemQuery, int $limit = 20): Collection
    {
        $rows = (clone $orderItemQuery)
            ->get(['item_name', 'menu_item_id', 'deal_id', 'variants', 'quantity', 'total_price']);

        return self::aggregateRows($rows, $limit);
    }

    /**
     * @param  Collection<int, object|array<string, mixed>>  $rows
     */
    public static function aggregateRows(Collection $rows, int $limit = 20): Collection
    {
        return $rows
            ->groupBy(fn ($row) => self::parentKey($row->menu_item_id, $row->deal_id, (string) $row->item_name))
            ->map(function (Collection $parentGroup) {
                $first = $parentGroup->first();
                $baseName = trim((string) $first->item_name);

                $variantBuckets = $parentGroup
                    ->groupBy(fn ($row) => self::variantBucketKey($row->variants))
                    ->map(function (Collection $variantGroup) {
                        $variantRow = $variantGroup->first();
                        $variants = self::normalizeVariants($variantRow->variants);
                        $label = self::variantChildLabel($variants);

                        return (object) [
                            'label' => $label,
                            'total_quantity' => round((float) $variantGroup->sum('quantity'), 2),
                            'total_revenue' => round((float) $variantGroup->sum('total_price'), 2),
                            'has_variant' => $variants !== null && self::variantLabel($variants) !== null,
                        ];
                    })
                    ->sortByDesc('total_quantity')
                    ->values();

                $showBreakdown = $variantBuckets->contains(fn ($bucket) => $bucket->has_variant);

                return (object) [
                    'item_name' => $baseName,
                    'menu_item_id' => $first->menu_item_id,
                    'deal_id' => $first->deal_id,
                    'total_quantity' => round((float) $parentGroup->sum('quantity'), 2),
                    'total_revenue' => round((float) $parentGroup->sum('total_price'), 2),
                    'variants' => $showBreakdown
                        ? $variantBuckets->map(fn ($bucket) => (object) [
                            'label' => $bucket->label,
                            'total_quantity' => $bucket->total_quantity,
                            'total_revenue' => $bucket->total_revenue,
                        ])->values()
                        : collect(),
                ];
            })
            ->sortByDesc('total_quantity')
            ->take($limit)
            ->values();
    }

    public static function parentKey(?int $menuItemId, ?int $dealId, string $itemName): string
    {
        if ($dealId) {
            return 'deal:'.$dealId;
        }

        if ($menuItemId) {
            return 'menu:'.$menuItemId;
        }

        return 'name:'.mb_strtolower(trim($itemName));
    }

    /**
     * @param  mixed  $variants
     */
    public static function variantBucketKey($variants): string
    {
        return self::groupKey(null, null, $variants);
    }

    public static function variantChildLabel(?array $variants): string
    {
        $label = self::variantLabel($variants);

        return $label !== null && $label !== '' ? $label : 'Standard';
    }

    public static function displayLabel(string $itemName, ?array $variants): string
    {
        $variantLabel = self::variantLabel($variants);
        if ($variantLabel === null || $variantLabel === '') {
            return trim($itemName);
        }

        return trim($itemName).' '.$variantLabel;
    }

    public static function variantLabel(?array $variants): ?string
    {
        $variants = self::normalizeVariants($variants);
        if ($variants === null) {
            return null;
        }

        $option = trim((string) ($variants['option_name'] ?? ''));
        if ($option !== '') {
            return $option;
        }

        $variantName = trim((string) ($variants['variant_name'] ?? ''));

        return $variantName !== '' ? $variantName : null;
    }

    /**
     * @param  mixed  $variants
     */
    public static function groupKey(?int $menuItemId, ?int $dealId, $variants): string
    {
        $variants = self::normalizeVariants($variants);
        $prefix = $dealId ? 'deal:'.$dealId : 'menu:'.($menuItemId ?? 0);

        if ($variants === null) {
            return $prefix.'|';
        }

        $variantId = (string) ($variants['variant_id'] ?? '');
        $optionName = trim((string) ($variants['option_name'] ?? ''));
        $variantName = trim((string) ($variants['variant_name'] ?? ''));

        return $prefix.'|'.$variantId.'|'.$optionName.'|'.$variantName;
    }

    /**
     * @param  mixed  $variants
     * @return array<string, mixed>|null
     */
    public static function normalizeVariants($variants): ?array
    {
        if (! is_array($variants) || $variants === []) {
            return null;
        }

        if (array_is_list($variants)) {
            $first = $variants[0] ?? null;

            return is_array($first) ? $first : null;
        }

        return $variants;
    }
}
