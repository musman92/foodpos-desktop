<?php

namespace App\Services;

use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\Log;

class IngredientCostService
{
    /**
     * Update ingredient cost after a purchase line (weighted avg from stock, or purchase price).
     */
    public function syncFromPurchase(
        int $ingredientId,
        float $unitPrice,
        ?string $purchaseUnitId = null,
        ?int $companyId = null
    ): void {
        $ingredient = Ingredient::withoutGlobalScopes()
            ->with(['consumptionUnit', 'purchaseUnit'])
            ->find($ingredientId);
        if (! $ingredient?->consumption_unit_id) {
            return;
        }

        if ($ingredient->company_id !== null && $companyId !== null && (int) $ingredient->company_id !== (int) $companyId) {
            return;
        }

        $costPerBase = $this->weightedAverageFromStock($ingredient);

        if ($costPerBase === null) {
            $costPerBase = $this->costPerConsumptionFromPurchasePrice($ingredient, $unitPrice);
        }

        if ($costPerBase === null) {
            Log::warning('Could not sync ingredient cost from purchase', [
                'ingredient_id' => $ingredientId,
                'unit_price' => $unitPrice,
                'purchase_unit' => $purchaseUnitId,
            ]);

            return;
        }

        $ingredient->cost_per_unit = round($costPerBase, 4);
        $ingredient->save();

        $this->refreshMenuItemCosts($ingredient->id);
    }

    /**
     * Cost per consumption unit from a purchase-unit price using the ingredient conversion rate.
     */
    public function costPerConsumptionFromPurchasePrice(Ingredient $ingredient, float $purchaseUnitPrice): ?float
    {
        $rate = (float) ($ingredient->conversion_rate ?: 1);
        if ($rate <= 0) {
            return null;
        }

        return $purchaseUnitPrice / $rate;
    }

    /**
     * Recalculate cost for one ingredient from branch stock batches.
     */
    public function weightedAverageFromStock(Ingredient $ingredient): ?float
    {
        if (! $ingredient->consumption_unit_id) {
            return null;
        }

        $stocks = BranchStock::withoutGlobalScopes()
            ->where('ingredient_id', $ingredient->id)
            ->where('quantity', '>', 0)
            ->with('unit')
            ->get();

        if ($stocks->isEmpty()) {
            return null;
        }

        $totalQtyBase = 0.0;
        $totalValue = 0.0;

        foreach ($stocks as $stock) {
            $costPerBase = (float) $stock->average_cost;
            $qtyBase = (float) $stock->quantity;

            if ($qtyBase <= 0 || $costPerBase < 0) {
                continue;
            }

            $totalQtyBase += $qtyBase;
            $totalValue += $qtyBase * $costPerBase;
        }

        if ($totalQtyBase <= 0) {
            return null;
        }

        return $totalValue / $totalQtyBase;
    }

    /**
     * Recalculate from latest purchase when no stock exists.
     */
    public function costFromLatestPurchase(Ingredient $ingredient): ?float
    {
        if (! $ingredient->consumption_unit_id) {
            return null;
        }

        $latest = PurchaseItem::query()
            ->where('item_type', 'ingredient')
            ->where('item_id', $ingredient->id)
            ->whereHas('purchase')
            ->with('purchase')
            ->get()
            ->sortByDesc(fn (PurchaseItem $item) => $item->purchase->purchase_date->format('Y-m-d').'-'.$item->id)
            ->first();

        if (! $latest) {
            return null;
        }

        return $this->costPerConsumptionFromPurchasePrice($ingredient, (float) $latest->unit_price);
    }

    /**
     * Recalculate and persist cost from stock or purchase history.
     */
    public function syncIngredient(Ingredient $ingredient): bool
    {
        $cost = $this->weightedAverageFromStock($ingredient)
            ?? $this->costFromLatestPurchase($ingredient);

        if ($cost === null) {
            return false;
        }

        $ingredient->cost_per_unit = round($cost, 4);
        $ingredient->save();
        $this->refreshMenuItemCosts($ingredient->id);

        return true;
    }

    public function refreshMenuItemCosts(int $ingredientId): void
    {
        MenuItem::query()
            ->where('type', 'recipe')
            ->whereHas('recipes', fn ($q) => $q->where('ingredient_id', $ingredientId))
            ->with('defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient', 'legacyRecipeLines.ingredient')
            ->chunkById(50, function ($menuItems) {
                foreach ($menuItems as $menuItem) {
                    $menuItem->cost = $menuItem->calculateCost();
                    $menuItem->save();
                }
            });
    }
}
