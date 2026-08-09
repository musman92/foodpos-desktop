<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\ProductAddon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class PosAddonService
{
    /**
     * @param  list<array<string, mixed>>|null  $addons
     * @return list<array{id: int, name: string, price: float, quantity: float, code: ?string}>
     */
    public function normalizeAddons(?array $addons): array
    {
        if (! is_array($addons) || $addons === []) {
            return [];
        }

        $grouped = [];
        foreach ($addons as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $qty = round(max(0, (float) ($row['quantity'] ?? 1)), 2);
            if ($qty <= 0) {
                continue;
            }
            if (! isset($grouped[$id])) {
                $grouped[$id] = [
                    'id' => $id,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'price' => round((float) ($row['price'] ?? 0), 2),
                    'quantity' => 0,
                    'code' => isset($row['code']) ? (string) $row['code'] : null,
                ];
            }
            $grouped[$id]['quantity'] = round($grouped[$id]['quantity'] + $qty, 2);
        }

        $normalized = array_values($grouped);
        usort($normalized, fn ($a, $b) => $a['id'] <=> $b['id']);

        return $normalized;
    }

    public function addonsTotal(array $addons): float
    {
        $total = 0.0;
        foreach ($addons as $addon) {
            $total += (float) $addon['price'] * (float) $addon['quantity'];
        }

        return round($total, 2);
    }

    public function resolveBaseUnitPrice(MenuItem $menuItem, ?array $variants): float
    {
        [$variantId, $optionName] = MenuItem::variantContextFromOrderSelection($variants);
        if ($variantId && $optionName) {
            $menuItem->loadMissing('variants');
            $variant = $menuItem->variants->firstWhere('id', $variantId);
            if ($variant) {
                $rawPrices = $variant->pivot->option_prices ?? null;
                $optionPrices = is_array($rawPrices) ? $rawPrices : (is_string($rawPrices) ? (json_decode($rawPrices, true) ?? []) : []);
                if (isset($optionPrices[$optionName])) {
                    return round((float) $optionPrices[$optionName], 2);
                }

                return round((float) ($variant->pivot->price ?? $menuItem->price), 2);
            }
        }

        return round((float) $menuItem->price, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function enrichAndValidatePosItems(array $items, int $companyId): array|JsonResponse
    {
        $menuItemIds = collect($items)
            ->filter(fn ($item) => empty($item['deal_id']) && ! empty($item['menu_item_id']))
            ->pluck('menu_item_id')
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, MenuItem> $menuItems */
        $menuItems = MenuItem::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $menuItemIds)
            ->with(['productAddons', 'variants'])
            ->get()
            ->keyBy('id');

        $addonIds = collect($items)
            ->flatMap(fn ($item) => $this->normalizeAddons(is_array($item['addons'] ?? null) ? $item['addons'] : null))
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, ProductAddon> $addonsById */
        $addonsById = ProductAddon::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $addonIds)
            ->get()
            ->keyBy('id');

        $enriched = [];

        foreach ($items as $item) {
            if (! empty($item['deal_id'])) {
                $enriched[] = $item;

                continue;
            }

            $menuItemId = (int) ($item['menu_item_id'] ?? 0);
            if ($menuItemId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid menu item on cart line.',
                ], 422);
            }

            /** @var MenuItem|null $menuItem */
            $menuItem = $menuItems->get($menuItemId);
            if (! $menuItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu item not found.',
                ], 422);
            }

            $variants = is_array($item['variants'] ?? null) ? $item['variants'] : null;
            $normalizedAddons = $this->normalizeAddons(is_array($item['addons'] ?? null) ? $item['addons'] : null);
            $allowedAddonIds = $menuItem->productAddons->pluck('id')->map(fn ($id) => (int) $id)->all();

            $resolvedAddons = [];
            foreach ($normalizedAddons as $addonRow) {
                if (! in_array($addonRow['id'], $allowedAddonIds, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Addon \"{$addonRow['name']}\" is not allowed for {$menuItem->name}.",
                    ], 422);
                }

                /** @var ProductAddon|null $catalogAddon */
                $catalogAddon = $addonsById->get($addonRow['id']);
                if (! $catalogAddon) {
                    return response()->json([
                        'success' => false,
                        'message' => 'One or more addons are no longer available.',
                    ], 422);
                }

                $resolvedAddons[] = [
                    'id' => $catalogAddon->id,
                    'name' => $catalogAddon->name,
                    'code' => $catalogAddon->code,
                    'price' => round((float) $catalogAddon->price, 2),
                    'quantity' => $addonRow['quantity'],
                ];
            }

            $basePrice = $this->resolveBaseUnitPrice($menuItem, $variants);
            $expectedUnitPrice = round($basePrice + $this->addonsTotal($resolvedAddons), 2);
            $submittedUnitPrice = round((float) ($item['unit_price'] ?? 0), 2);

            if (abs($expectedUnitPrice - $submittedUnitPrice) > 0.02) {
                return response()->json([
                    'success' => false,
                    'message' => "Price mismatch for {$menuItem->name}. Please refresh POS and try again.",
                ], 422);
            }

            $enriched[] = array_merge($item, [
                'unit_price' => $expectedUnitPrice,
                'addons' => $resolvedAddons === [] ? null : $resolvedAddons,
            ]);
        }

        return $enriched;
    }

    /**
     * @param  list<array{id: int, name: string, price: float, quantity: float}>|null  $addons
     */
    public function addonsLabel(?array $addons): string
    {
        $normalized = $this->normalizeAddons($addons);
        if ($normalized === []) {
            return '';
        }

        $parts = [];
        foreach ($normalized as $addon) {
            $label = $addon['name'];
            if ((float) $addon['quantity'] > 1) {
                $label .= ' x'.rtrim(rtrim(number_format((float) $addon['quantity'], 2, '.', ''), '0'), '.');
            }
            $parts[] = $label;
        }

        return '+ '.implode(', ', $parts);
    }
}
