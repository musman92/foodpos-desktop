<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\MenuItem;

/**
 * Convert purchase quantities to menu-item sell (stock) units using item conversion_rate.
 */
final class MenuItemQuantity
{
    public static function toSellQuantity(MenuItem $menuItem, float $purchaseQuantity): float
    {
        $rate = max((float) ($menuItem->conversion_rate ?: 1), 0.0001);

        return $purchaseQuantity * $rate;
    }

    public static function costPerSellUnit(MenuItem $menuItem, float $purchaseUnitPrice): float
    {
        $rate = max((float) ($menuItem->conversion_rate ?: 1), 0.0001);

        return $purchaseUnitPrice / $rate;
    }

    public static function purchaseUnitKey(MenuItem $menuItem): string
    {
        return (string) ($menuItem->purchase_unit_id ?? '');
    }
}
