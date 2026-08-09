<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\MenuItemStock;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\Log;

class MenuItemCostService
{
    /**
     * Update menu item cost after a purchase line (weighted avg from stock, or purchase price).
     */
    public function syncFromPurchase(int $menuItemId, float $purchaseUnitPrice, ?MenuItem $menuItem = null): void
    {
        $menuItem ??= MenuItem::withoutGlobalScopes()->find($menuItemId);
        if (! $menuItem || $menuItem->type !== 'single' || ! $menuItem->track_inventory) {
            return;
        }

        $costPerSell = \App\Support\MenuItemQuantity::costPerSellUnit($menuItem, $purchaseUnitPrice);
        $cost = $this->weightedAverageFromStock($menuItem) ?? $costPerSell;

        $menuItem->cost = round($cost, 4);
        $menuItem->save();
    }

    /**
     * Recalculate and persist cost from stock or purchase history.
     */
    public function syncMenuItem(MenuItem $menuItem): bool
    {
        if ($menuItem->type !== 'single' || ! $menuItem->track_inventory) {
            return false;
        }

        $cost = $this->weightedAverageFromStock($menuItem)
            ?? $this->costFromLatestPurchase($menuItem);

        if ($cost === null) {
            return false;
        }

        $menuItem->cost = round($cost, 4);
        $menuItem->save();

        return true;
    }

    /**
     * Weighted average unit price across all in-stock batches (all branches).
     */
    public function weightedAverageFromStock(MenuItem $menuItem): ?float
    {
        $stocks = MenuItemStock::withoutGlobalScopes()
            ->where('menu_item_id', $menuItem->id)
            ->where('quantity', '>', 0)
            ->get();

        if ($stocks->isEmpty()) {
            return null;
        }

        $totalQty = 0.0;
        $totalValue = 0.0;

        foreach ($stocks as $stock) {
            $qty = (float) $stock->quantity;
            $price = (float) $stock->unit_price;

            if ($qty <= 0 || $price < 0) {
                continue;
            }

            $totalQty += $qty;
            $totalValue += $qty * $price;
        }

        if ($totalQty <= 0) {
            return null;
        }

        return $totalValue / $totalQty;
    }

    /**
     * Latest purchase unit price when no stock exists.
     */
    public function costFromLatestPurchase(MenuItem $menuItem): ?float
    {
        $latest = PurchaseItem::query()
            ->where('item_type', 'menu_item')
            ->where('item_id', $menuItem->id)
            ->whereHas('purchase')
            ->with('purchase')
            ->get()
            ->sortByDesc(fn (PurchaseItem $item) => $item->purchase->purchase_date->format('Y-m-d').'-'.$item->id)
            ->first();

        if (! $latest) {
            return null;
        }

        return \App\Support\MenuItemQuantity::costPerSellUnit($menuItem, (float) $latest->unit_price);
    }

    /**
     * Default purchase price: weighted avg, then latest purchase, then stored menu cost.
     */
    public function preferredPurchasePrice(MenuItem $menuItem): float
    {
        return $this->weightedAverageFromStock($menuItem)
            ?? $this->costFromLatestPurchase($menuItem)
            ?? (float) ($menuItem->purchase_price ?: $menuItem->cost ?? 0);
    }

    public function syncMenuItemById(int $menuItemId): void
    {
        $menuItem = MenuItem::withoutGlobalScopes()->find($menuItemId);
        if (! $menuItem) {
            return;
        }

        if (! $this->syncMenuItem($menuItem)) {
            Log::debug('Menu item cost unchanged after stock sync', ['menu_item_id' => $menuItemId]);
        }
    }
}
