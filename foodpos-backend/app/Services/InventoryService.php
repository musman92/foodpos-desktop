<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Deal;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\MenuItemStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefundLine;
use App\Models\ProductAddon;
use App\Models\ProductAddonRecipe;
use App\Models\StockMovement;
use App\Support\IngredientQuantity;
use App\Support\UnitOfMeasureResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    public function __construct(
        protected MenuItemCostService $menuItemCosts,
        protected UnitOfMeasureResolver $unitOfMeasureResolver,
    ) {}

    /**
     * Reserve inventory for an order item.
     * This is called when an order is created but not yet completed.
     */
    public function reserveInventory(OrderItem $orderItem, int $branchId): bool
    {
        if ($orderItem->deal_id) {
            $this->reserveDealInventory($orderItem, $branchId);

            return true;
        }

        $menuItem = $orderItem->menuItem;

        if ($menuItem && $menuItem->track_inventory) {
            Log::info('Reserving inventory for order item', [
                'order_item_id' => $orderItem->id,
                'menu_item_id' => $menuItem->id,
                'menu_item_name' => $menuItem->name,
                'menu_item_type' => $menuItem->type,
                'track_inventory' => $menuItem->track_inventory,
            ]);

            if ($menuItem->type === 'single') {
                $this->reserveMenuItemStock($orderItem, $branchId);
            } elseif ($menuItem->type === 'recipe') {
                $this->reserveIngredientStock($orderItem, $branchId);
            } else {
                Log::warning('Unknown menu item type, skipping menu item inventory', [
                    'order_item_id' => $orderItem->id,
                    'menu_item_id' => $menuItem->id,
                    'menu_item_type' => $menuItem->type,
                ]);
            }
        }

        $this->reserveAddonInventoryForOrderItem($orderItem, $branchId);

        return true;
    }

    /**
     * Reserve menu item stock for single type menu items.
     */
    protected function reserveMenuItemStock(OrderItem $orderItem, int $branchId): bool
    {
        try {
            DB::beginTransaction();

            $menuItem = $orderItem->menuItem;
            $quantity = $orderItem->quantity;

            // Get menu item stock for this branch
            $menuItemStock = MenuItemStock::where('branch_id', $branchId)
                ->where('menu_item_id', $menuItem->id)
                ->orderBy('expiry_date', 'asc') // Use FIFO - oldest first
                ->first();

            if (! $menuItemStock) {
                DB::rollBack();
                throw new \Exception(
                    "Insufficient stock for {$menuItem->name}. No stock available in branch."
                );
            }

            // Check if enough stock is available
            $totalAvailable = MenuItemStock::where('branch_id', $branchId)
                ->where('menu_item_id', $menuItem->id)
                ->sum('quantity');

            if ($totalAvailable < $quantity) {
                DB::rollBack();
                throw new \Exception(
                    "Insufficient stock for {$menuItem->name}. ".
                    "Required: {$quantity}, Available: {$totalAvailable}"
                );
            }

            // Reserve quantity (we'll deduct in finalize)
            // For now, we'll just check availability
            // Actual deduction happens in finalizeMenuItemStock

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Menu item stock reservation failed', [
                'order_item_id' => $orderItem->id,
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reserve ingredient stock for recipe type menu items.
     */
    protected function reserveIngredientStock(OrderItem $orderItem, int $branchId): bool
    {
        $menuItem = $orderItem->menuItem;

        // Ensure recipes are loaded
        if (! $menuItem->relationLoaded('recipes')) {
            $menuItem->load('defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient', 'legacyRecipeLines.ingredient');
        }

        // Ensure order is loaded for order number
        if (! $orderItem->relationLoaded('order')) {
            $orderItem->load('order');
        }

        if (StockMovement::where('reference_type', OrderItem::class)
            ->where('reference_id', $orderItem->id)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->exists()) {
            Log::info('Inventory already reserved for order item, skipping duplicate reserve', [
                'order_item_id' => $orderItem->id,
                'menu_item_id' => $menuItem->id,
            ]);

            return true;
        }

        try {
            DB::beginTransaction();

            $recipes = $this->resolveRecipesForOrderItem($orderItem);

            // Skip if no recipes
            if (! $recipes || $recipes->isEmpty()) {
                DB::rollBack();
                Log::info('Menu item has no recipes, skipping inventory', [
                    'order_item_id' => $orderItem->id,
                    'menu_item_id' => $menuItem->id,
                    'menu_item_name' => $menuItem->name,
                ]);

                return true; // Not an error, just no inventory to track
            }

            $quantity = $orderItem->quantity;
            $orderNumber = $orderItem->order ? $orderItem->order->order_number : "Order #{$orderItem->order_id}";

            foreach ($recipes as $recipe) {
                // Ensure ingredient is loaded
                if (! $recipe->relationLoaded('ingredient')) {
                    $recipe->load('ingredient');
                }

                $ingredient = $recipe->ingredient;

                // Skip if ingredient doesn't exist or doesn't track stock
                if (! $ingredient || $ingredient->track_stock === 'no') {
                    continue;
                }

                // Calculate required quantity in consumption (stock) units
                $requiredQuantityInRecipeUnit = $recipe->effective_quantity * $quantity;
                $requiredQuantity = $this->recipeQuantityInConsumptionUnits(
                    $ingredient,
                    $requiredQuantityInRecipeUnit,
                    $recipe->recipeUnitId()
                );

                $this->reserveIngredientQuantityFifo(
                    $branchId,
                    $ingredient,
                    $requiredQuantity,
                    $orderItem,
                    "Reserved for {$orderNumber}"
                );
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Inventory reservation failed', [
                'order_item_id' => $orderItem->id,
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Finalize inventory deduction when order is completed.
     * This moves reserved quantities to actual deduction.
     */
    public function finalizeInventoryDeduction(Order $order): bool
    {
        // Check if inventory has already been finalized for this order
        // by checking if stock movements have been updated with "Finalized" note
        $alreadyFinalized = StockMovement::where('reference_type', OrderItem::class)
            ->whereIn('reference_id', $order->items->pluck('id'))
            ->where('type', 'sale')
            ->where('notes', 'like', '%Finalized for completed order%')
            ->exists();

        if ($alreadyFinalized) {
            Log::info('Inventory already finalized for order, skipping duplicate finalization', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return true;
        }

        try {
            DB::beginTransaction();

            foreach ($order->items as $orderItem) {
                if ($orderItem->deal_id) {
                    $this->finalizeDealInventory($orderItem, (int) $order->branch_id, (string) $order->order_number);

                    continue;
                }

                $menuItem = $orderItem->menuItem;

                if ($menuItem && $menuItem->track_inventory) {
                    Log::info('Finalizing inventory for order item', [
                        'order_item_id' => $orderItem->id,
                        'menu_item_id' => $menuItem->id,
                        'menu_item_name' => $menuItem->name,
                        'menu_item_type' => $menuItem->type,
                    ]);

                    if ($menuItem->type === 'single') {
                        $this->finalizeMenuItemStock($orderItem, $order->branch_id, $order->order_number);
                    } elseif ($menuItem->type === 'recipe') {
                        $this->finalizeIngredientStock($orderItem, $order->branch_id, $order->order_number);
                    } else {
                        Log::warning('Unknown menu item type in finalize', [
                            'order_item_id' => $orderItem->id,
                            'menu_item_id' => $menuItem->id,
                            'menu_item_type' => $menuItem->type,
                        ]);
                    }
                }

                $this->finalizeAddonInventoryForOrderItem($orderItem, $order->branch_id, $order->order_number);
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Inventory finalization failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Finalize menu item stock deduction for single type menu items.
     */
    protected function finalizeMenuItemStock(OrderItem $orderItem, int $branchId, string $orderNumber, ?float $quantityOverride = null): void
    {
        $menuItem = $orderItem->menuItem;
        $quantity = $quantityOverride ?? (float) $orderItem->quantity;
        $remainingQuantity = $quantity;

        if ($this->menuItemSaleMovementsFinalized($orderItem, $menuItem->id)) {
            return;
        }

        // Get all menu item stock batches (FIFO - oldest expiry first)
        // Handle null expiry dates (put them last)
        $stockBatches = MenuItemStock::where('branch_id', $branchId)
            ->where('menu_item_id', $menuItem->id)
            ->where('quantity', '>', 0)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($stockBatches->isEmpty()) {
            Log::warning('No menu item stock found for deduction', [
                'order_item_id' => $orderItem->id,
                'menu_item_id' => $menuItem->id,
                'menu_item_name' => $menuItem->name,
                'branch_id' => $branchId,
                'quantity_required' => $quantity,
            ]);

            return;
        }

        // Deduct from batches using FIFO
        foreach ($stockBatches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $deductAmount = min($remainingQuantity, $batch->quantity);
            $unitCost = (float) ($batch->unit_price ?: $menuItem->cost ?: 0);
            $batch->decrement('quantity', $deductAmount);
            $remainingQuantity -= $deductAmount;

            $this->recordMenuItemSaleMovement(
                $branchId,
                (int) $menuItem->id,
                (float) $deductAmount,
                $unitCost,
                $orderItem,
                $orderNumber
            );

            Log::info('Menu item stock deducted', [
                'order_item_id' => $orderItem->id,
                'menu_item_id' => $menuItem->id,
                'menu_item_name' => $menuItem->name,
                'quantity_deducted' => $deductAmount,
                'batch_id' => $batch->id,
                'remaining_quantity' => $batch->quantity,
            ]);

            // Delete batch if quantity is zero
            if ($batch->quantity <= 0) {
                $batch->delete();
            }
        }

        if ($remainingQuantity > 0) {
            Log::warning('Insufficient menu item stock for complete deduction', [
                'order_item_id' => $orderItem->id,
                'menu_item_id' => $menuItem->id,
                'remaining_quantity' => $remainingQuantity,
            ]);
        }

        $this->menuItemCosts->syncMenuItemById((int) $menuItem->id);
    }

    protected function menuItemSaleMovementsFinalized(OrderItem $orderItem, int $menuItemId): bool
    {
        return StockMovement::query()
            ->where('reference_type', OrderItem::class)
            ->where('reference_id', $orderItem->id)
            ->where('menu_item_id', $menuItemId)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->where('notes', 'like', '%Finalized for completed order%')
            ->exists();
    }

    protected function recordMenuItemSaleMovement(
        int $branchId,
        int $menuItemId,
        float $quantity,
        float $unitCost,
        OrderItem $orderItem,
        string $orderNumber,
        ?string $notes = null
    ): void {
        if ($quantity <= 0.0001) {
            return;
        }

        StockMovement::create([
            'branch_id' => $branchId,
            'ingredient_id' => null,
            'menu_item_id' => $menuItemId,
            'type' => 'sale',
            'movement' => 'out',
            'quantity' => round($quantity, 2),
            'unit_id' => 'pcs',
            'unit_cost' => round($unitCost, 2),
            'reference_type' => OrderItem::class,
            'reference_id' => $orderItem->id,
            'notes' => $notes ?? "Finalized for completed order #{$orderNumber}",
        ]);
    }

    /**
     * Finalize ingredient stock deduction for recipe type menu items.
     */
    protected function finalizeIngredientStock(OrderItem $orderItem, int $branchId, string $orderNumber): void
    {
        $menuItem = $orderItem->menuItem;

        // Ensure recipes are loaded
        if (! $menuItem->relationLoaded('recipes')) {
            $menuItem->load('defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient', 'legacyRecipeLines.ingredient');
        }

        $recipes = $this->resolveRecipesForOrderItem($orderItem);

        // Skip if no recipes
        if (! $recipes || $recipes->isEmpty()) {
            Log::info('Menu item has no recipes in finalizeIngredientStock', [
                'order_item_id' => $orderItem->id,
                'menu_item_id' => $menuItem->id,
                'menu_item_name' => $menuItem->name,
            ]);

            return;
        }

        $quantity = $orderItem->quantity;

        foreach ($recipes as $recipe) {
            // Ensure ingredient is loaded
            if (! $recipe->relationLoaded('ingredient')) {
                $recipe->load('ingredient');
            }

            $ingredient = $recipe->ingredient;

            if (! $ingredient || $ingredient->track_stock === 'no') {
                continue;
            }

            $requiredQuantityInRecipeUnit = $recipe->effective_quantity * $quantity;
            $requiredQuantity = IngredientQuantity::toConsumptionQuantity(
                $ingredient,
                $requiredQuantityInRecipeUnit,
                $recipe->recipeUnitId()
            );

            if ($requiredQuantity === null) {
                Log::error('Ingredient unit conversion failed in finalize', [
                    'order_item_id' => $orderItem->id,
                    'recipe_unit' => $recipe->recipeUnitId(),
                    'ingredient_name' => $ingredient->name,
                ]);

                continue;
            }

            $saleMovements = StockMovement::query()
                ->where('reference_type', OrderItem::class)
                ->where('reference_id', $orderItem->id)
                ->where('ingredient_id', $ingredient->id)
                ->where('type', 'sale')
                ->where('movement', 'out')
                ->where('notes', 'not like', '%addon%')
                ->get();

            if ($saleMovements->isNotEmpty()) {
                $this->finalizeIngredientFromSaleMovements($branchId, $ingredient, $saleMovements);
            } else {
                $this->deductIngredientQuantityFifoWithMovements(
                    $branchId,
                    $ingredient,
                    $requiredQuantity,
                    $orderItem,
                    "Finalized for completed order #{$orderNumber}"
                );
            }

            Log::info('Ingredient stock deducted', [
                'order_item_id' => $orderItem->id,
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => $ingredient->name,
                'quantity_deducted' => $requiredQuantity,
                'branch_id' => $branchId,
            ]);

            StockMovement::query()
                ->where('reference_type', OrderItem::class)
                ->where('reference_id', $orderItem->id)
                ->where('ingredient_id', $ingredient->id)
                ->where('type', 'sale')
                ->where('movement', 'out')
                ->update([
                    'notes' => "Finalized for completed order #{$orderNumber}",
                ]);
        }
    }

    /**
     * Release reserved inventory when order is cancelled.
     */
    public function releaseReservedInventory(Order $order): bool
    {
        try {
            DB::beginTransaction();

            foreach ($order->items as $orderItem) {
                $menuItem = $orderItem->menuItem;

                if ($menuItem && $menuItem->track_inventory) {
                    $recipes = $this->resolveRecipesForOrderItem($orderItem);
                    $quantity = $orderItem->quantity;

                    foreach ($recipes as $recipe) {
                        $ingredient = $recipe->ingredient;

                        if (! $ingredient || $ingredient->track_stock === 'no') {
                            continue;
                        }

                        $requiredInRecipeUnit = $recipe->effective_quantity * $quantity;
                        $requiredQuantity = IngredientQuantity::toConsumptionQuantity(
                            $ingredient,
                            $requiredInRecipeUnit,
                            $recipe->recipeUnitId()
                        );

                        if ($requiredQuantity === null) {
                            continue;
                        }

                        $this->releaseIngredientReservedFifo(
                            $order->branch_id,
                            $ingredient->id,
                            $requiredQuantity
                        );

                        StockMovement::where('reference_type', OrderItem::class)
                            ->where('reference_id', $orderItem->id)
                            ->where('type', 'sale')
                            ->update([
                                'type' => 'adjustment',
                                'notes' => "Released due to order cancellation #{$order->order_number}",
                            ]);
                    }
                }

                $addons = is_array($orderItem->addons) ? $orderItem->addons : [];
                $normalized = app(PosAddonService::class)->normalizeAddons($addons);
                if ($normalized === []) {
                    continue;
                }

                $catalog = ProductAddon::query()
                    ->whereIn('id', collect($normalized)->pluck('id'))
                    ->with(['recipes.ingredient'])
                    ->get()
                    ->keyBy('id');

                foreach ($normalized as $row) {
                    $addon = $catalog->get($row['id']);
                    if (! $addon || ! $addon->track_inventory || $addon->type !== ProductAddon::TYPE_RECIPE) {
                        continue;
                    }

                    $multiplier = (float) $orderItem->quantity * (float) $row['quantity'];

                    foreach ($addon->recipes as $recipe) {
                        $ingredient = $recipe->ingredient;
                        if (! $ingredient || $ingredient->track_stock === 'no') {
                            continue;
                        }

                        $requiredInRecipeUnit = $recipe->effective_quantity * $multiplier;
                        $requiredQuantity = IngredientQuantity::toConsumptionQuantity(
                            $ingredient,
                            $requiredInRecipeUnit,
                            $recipe->recipeUnitId()
                        );

                        if ($requiredQuantity === null) {
                            continue;
                        }

                        $this->releaseIngredientReservedFifo(
                            $order->branch_id,
                            $ingredient->id,
                            $requiredQuantity
                        );
                    }
                }
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Inventory release failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if menu item can be sold (has enough stock).
     * Returns array with 'can_sell' boolean and 'error_message' if cannot sell.
     */
    public function checkMenuItemAvailability(
        MenuItem $menuItem,
        float $quantity,
        int $branchId,
        ?int $variantId = null,
        ?string $variantOption = null
    ): array {
        if (! $menuItem->track_inventory) {
            return ['can_sell' => true, 'error_message' => null];
        }

        if ($menuItem->type === 'single') {
            // Check menu item stock
            $totalAvailable = MenuItemStock::where('branch_id', $branchId)
                ->where('menu_item_id', $menuItem->id)
                ->sum('quantity');

            if ($totalAvailable < $quantity) {
                return [
                    'can_sell' => false,
                    'error_message' => "Insufficient stock for {$menuItem->name}. Required: {$quantity}, Available: {$totalAvailable}",
                ];
            }
        } elseif ($menuItem->type === 'recipe') {
            // Ensure recipes are loaded
            if (! $menuItem->relationLoaded('recipes')) {
                $menuItem->load('defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient', 'legacyRecipeLines.ingredient');
            }

            $recipes = $menuItem->resolveRecipes($variantId, $variantOption);

            if (! $recipes || $recipes->isEmpty()) {
                // No recipes means no inventory to track
                return ['can_sell' => true, 'error_message' => null];
            }

            foreach ($recipes as $recipe) {
                // Ensure ingredient is loaded
                if (! $recipe->relationLoaded('ingredient')) {
                    $recipe->load('ingredient');
                }

                $ingredient = $recipe->ingredient;

                // Skip if ingredient doesn't exist or doesn't track stock
                if (! $ingredient || $ingredient->track_stock === 'no') {
                    continue;
                }

                $recipeUnit = $recipe->recipeUnitId();

                if (! $recipeUnit) {
                    return [
                        'can_sell' => false,
                        'error_message' => "Recipe unit is not set for {$ingredient->name} in {$menuItem->name}. Please set the unit in the recipe.",
                    ];
                }

                $requiredQuantityInRecipeUnit = $recipe->effective_quantity * $quantity;
                $requiredQuantityInBaseUnit = IngredientQuantity::toConsumptionQuantity(
                    $ingredient,
                    $requiredQuantityInRecipeUnit,
                    $recipeUnit
                );

                if ($requiredQuantityInBaseUnit === null) {
                    return [
                        'can_sell' => false,
                        'error_message' => IngredientQuantity::conversionErrorMessage($ingredient, $recipeUnit),
                    ];
                }

                // Get branch stock
                $availableQuantity = $this->totalIngredientAvailableQuantity($branchId, $ingredient->id);

                if ($availableQuantity < $requiredQuantityInBaseUnit - 0.0001) {
                    $unitAbbr = $ingredient->unit_abbreviation ?? $ingredient->base_unit_id ?? 'units';

                    if ($availableQuantity <= 0.0001) {
                        return [
                            'can_sell' => false,
                            'error_message' => "Insufficient stock for {$ingredient->name}. No stock available. Required: {$requiredQuantityInBaseUnit} {$unitAbbr}",
                        ];
                    }

                    return [
                        'can_sell' => false,
                        'error_message' => "Insufficient stock for {$ingredient->name}. Required: {$requiredQuantityInBaseUnit} {$unitAbbr}, Available: {$availableQuantity} {$unitAbbr}",
                    ];
                }
            }
        }

        return ['can_sell' => true, 'error_message' => null];
    }

    /**
     * Check whether a deal's linked menu items have enough stock for the deal quantity.
     *
     * @return array{can_sell: bool, error_message: ?string}
     */
    public function checkDealAvailability(Deal $deal, float $quantity, int $branchId): array
    {
        $deal->loadMissing([
            'menuItems.defaultRecipe.items.ingredient',
            'menuItems.variantRecipes.recipe.items.ingredient',
            'menuItems.legacyRecipeLines.ingredient',
        ]);

        foreach ($deal->menuItems as $menuItem) {
            $componentQty = round((float) ($menuItem->pivot->quantity ?? 1) * $quantity, 4);
            $variantId = $menuItem->pivot->variant_id ? (int) $menuItem->pivot->variant_id : null;
            $optionName = $menuItem->pivot->option_name ? (string) $menuItem->pivot->option_name : null;

            $availability = $this->checkMenuItemAvailability(
                $menuItem,
                $componentQty,
                $branchId,
                $variantId,
                $optionName
            );

            if (! $availability['can_sell']) {
                $componentName = $menuItem->name;
                $message = $availability['error_message'] ?? "Insufficient stock for {$componentName}.";

                return [
                    'can_sell' => false,
                    'error_message' => "Deal \"{$deal->title}\": {$message}",
                ];
            }
        }

        return ['can_sell' => true, 'error_message' => null];
    }

    /**
     * Availability check only — actual deduction happens in finalize (same as single items).
     */
    protected function reserveDealInventory(OrderItem $orderItem, int $branchId): void
    {
        $deal = $this->dealForOrderItem($orderItem);
        if (! $deal) {
            return;
        }

        $availability = $this->checkDealAvailability($deal, (float) $orderItem->quantity, $branchId);
        if (! $availability['can_sell']) {
            throw new \Exception($availability['error_message'] ?? 'Insufficient stock for deal.');
        }

        Log::info('Deal inventory availability checked', [
            'order_item_id' => $orderItem->id,
            'deal_id' => $deal->id,
            'deal_title' => $deal->title,
        ]);
    }

    /**
     * Deduct stock for each tracked menu item inside the deal.
     */
    protected function finalizeDealInventory(OrderItem $orderItem, int $branchId, string $orderNumber): void
    {
        $deal = $this->dealForOrderItem($orderItem);
        if (! $deal) {
            return;
        }

        foreach ($this->dealComponents($orderItem) as $component) {
            /** @var MenuItem $menuItem */
            $menuItem = $component['menu_item'];
            $quantity = $component['quantity'];
            $variantId = $component['variant_id'];
            $optionName = $component['option_name'];

            if (! $menuItem->track_inventory || $quantity <= 0.0001) {
                continue;
            }

            Log::info('Finalizing deal component inventory', [
                'order_item_id' => $orderItem->id,
                'deal_id' => $deal->id,
                'menu_item_id' => $menuItem->id,
                'menu_item_name' => $menuItem->name,
                'menu_item_type' => $menuItem->type,
                'quantity' => $quantity,
            ]);

            if ($menuItem->type === 'single') {
                $orderItem->setRelation('menuItem', $menuItem);
                $this->finalizeMenuItemStock($orderItem, $branchId, $orderNumber, $quantity);
            } elseif ($menuItem->type === 'recipe') {
                $this->finalizeDealRecipeComponent(
                    $orderItem,
                    $menuItem,
                    $quantity,
                    $variantId,
                    $optionName,
                    $branchId,
                    $orderNumber
                );
            }
        }

        // Clear any temporary relation so later code does not treat the deal line as a menu item.
        $orderItem->unsetRelation('menuItem');
    }

    protected function finalizeDealRecipeComponent(
        OrderItem $orderItem,
        MenuItem $menuItem,
        float $quantity,
        ?int $variantId,
        ?string $optionName,
        int $branchId,
        string $orderNumber
    ): void {
        $menuItem->loadMissing([
            'defaultRecipe.items.ingredient',
            'variantRecipes.recipe.items.ingredient',
            'legacyRecipeLines.ingredient',
        ]);

        $recipes = $menuItem->resolveRecipes($variantId, $optionName);
        if ($recipes->isEmpty()) {
            return;
        }

        $notes = "Finalized for completed order #{$orderNumber}";

        foreach ($recipes as $recipe) {
            $recipe->loadMissing('ingredient');
            $ingredient = $recipe->ingredient;

            if (! $ingredient || $ingredient->track_stock === 'no') {
                continue;
            }

            $requiredQuantityInRecipeUnit = $recipe->effective_quantity * $quantity;
            $requiredQuantity = IngredientQuantity::toConsumptionQuantity(
                $ingredient,
                $requiredQuantityInRecipeUnit,
                $recipe->recipeUnitId()
            );

            if ($requiredQuantity === null || $requiredQuantity <= 0.0001) {
                continue;
            }

            // Idempotency: skip if this component's ingredient was already finalized for this line.
            $alreadyFinalizedQty = (float) StockMovement::query()
                ->where('reference_type', OrderItem::class)
                ->where('reference_id', $orderItem->id)
                ->where('ingredient_id', $ingredient->id)
                ->where('menu_item_id', $menuItem->id)
                ->where('type', 'sale')
                ->where('movement', 'out')
                ->where('notes', 'like', '%Finalized for completed order%')
                ->sum('quantity');

            $remaining = round(max(0, $requiredQuantity - $alreadyFinalizedQty), 4);
            if ($remaining <= 0.0001) {
                continue;
            }

            $this->deductIngredientQuantityFifoWithMovementsForMenuItem(
                $branchId,
                $ingredient,
                $remaining,
                $orderItem,
                $menuItem,
                $notes
            );
        }
    }

    /**
     * Like deductIngredientQuantityFifoWithMovements, but tags movements with menu_item_id
     * so deal components sharing an ingredient stay idempotent per component.
     */
    protected function deductIngredientQuantityFifoWithMovementsForMenuItem(
        int $branchId,
        Ingredient $ingredient,
        float $requiredQuantity,
        OrderItem $orderItem,
        MenuItem $menuItem,
        string $notes
    ): void {
        $remaining = $requiredQuantity;

        foreach ($this->ingredientStockBatches($branchId, $ingredient->id) as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }

            $qty = max(0, (float) $batch->quantity);
            if ($qty <= 0.0001) {
                continue;
            }

            $take = min($qty, $remaining);
            $unitCost = (float) $batch->average_cost;
            $batch->decrement('quantity', $take);
            $remaining -= $take;
            $this->cleanupEmptyBranchStockBatch($batch->fresh());

            StockMovement::create([
                'branch_id' => $branchId,
                'ingredient_id' => $ingredient->id,
                'menu_item_id' => $menuItem->id,
                'type' => 'sale',
                'movement' => 'out',
                'quantity' => $take,
                'unit_id' => $ingredient->base_unit_id,
                'unit_cost' => $unitCost,
                'reference_type' => OrderItem::class,
                'reference_id' => $orderItem->id,
                'notes' => $notes,
            ]);
        }

        if ($remaining > 0.0001) {
            $unitAbbr = $ingredient->unit_abbreviation ?? $ingredient->base_unit_id ?? 'units';
            throw new \Exception(
                "Insufficient stock for {$ingredient->name}. ".
                "Could not deduct remaining {$remaining} {$unitAbbr}."
            );
        }
    }

    protected function restockDealInventoryForRefund(
        OrderItem $orderItem,
        float $restockQty,
        int $branchId,
        int $userId,
        ?int $referenceId,
        string $orderNumber,
        string $referenceType
    ): void {
        $originalQty = (float) $orderItem->quantity;
        $scale = $originalQty > 0.0001 ? min(1.0, $restockQty / $originalQty) : 1.0;

        $this->restockIngredientsFromOrderItemMovements(
            $orderItem,
            $branchId,
            $userId,
            $referenceId,
            $orderNumber,
            $referenceType,
            $scale
        );

        $deal = $this->dealForOrderItem($orderItem);
        if (! $deal) {
            return;
        }

        foreach ($deal->menuItems as $menuItem) {
            if (! $menuItem->track_inventory || $menuItem->type !== 'single') {
                continue;
            }

            $pivotQty = (float) ($menuItem->pivot->quantity ?? 1);
            $componentRestock = round($pivotQty * $restockQty, 4);
            if ($componentRestock <= 0.0001) {
                continue;
            }

            $this->restockMenuItemStockAfterRefund(
                $menuItem,
                $componentRestock,
                $branchId,
                $userId,
                $referenceId,
                $orderNumber,
                $referenceType
            );
        }
    }

    protected function dealForOrderItem(OrderItem $orderItem): ?Deal
    {
        if (! $orderItem->deal_id) {
            return null;
        }

        $orderItem->loadMissing([
            'deal.menuItems.defaultRecipe.items.ingredient',
            'deal.menuItems.variantRecipes.recipe.items.ingredient',
            'deal.menuItems.legacyRecipeLines.ingredient',
        ]);

        return $orderItem->deal;
    }

    /**
     * @return list<array{menu_item: MenuItem, quantity: float, variant_id: ?int, option_name: ?string}>
     */
    protected function dealComponents(OrderItem $orderItem): array
    {
        $deal = $this->dealForOrderItem($orderItem);
        if (! $deal) {
            return [];
        }

        $dealQty = (float) $orderItem->quantity;
        $components = [];

        foreach ($deal->menuItems as $menuItem) {
            $pivotQty = (float) ($menuItem->pivot->quantity ?? 1);
            $components[] = [
                'menu_item' => $menuItem,
                'quantity' => round($dealQty * $pivotQty, 4),
                'variant_id' => $menuItem->pivot->variant_id ? (int) $menuItem->pivot->variant_id : null,
                'option_name' => $menuItem->pivot->option_name ? (string) $menuItem->pivot->option_name : null,
            ];
        }

        return $components;
    }

    /**
     * Get low stock alerts for a branch.
     */
    public function getLowStockAlerts(int $branchId): array
    {
        $alerts = [];

        $branchStocks = BranchStock::where('branch_id', $branchId)
            ->with('ingredient')
            ->get();

        foreach ($branchStocks as $stock) {
            if ($stock->isLowStock()) {
                $alerts[] = [
                    'type' => 'ingredient',
                    'name' => $stock->ingredient->name,
                    'current_stock' => $stock->quantity,
                    'min_stock_level' => $stock->ingredient->min_stock_level,
                    'unit' => $stock->ingredient->unit_abbreviation ?? $stock->ingredient->base_unit_id ?? 'units',
                ];
            }
        }

        $menuItemTotals = MenuItemStock::where('branch_id', $branchId)
            ->with('menuItem')
            ->get()
            ->groupBy('menu_item_id');

        foreach ($menuItemTotals as $menuItemId => $stocks) {
            $menuItem = $stocks->first()?->menuItem;
            if (! $menuItem || ! $menuItem->isLowStockAtBranch($branchId)) {
                continue;
            }

            $alerts[] = [
                'type' => 'menu_item',
                'name' => $menuItem->name,
                'current_stock' => $menuItem->totalStockAtBranch($branchId),
                'min_stock_level' => $menuItem->min_stock_level,
                'unit' => 'pcs',
            ];
        }

        return $alerts;
    }

    /**
     * Manual ingredient stock adjustment (increase or decrease quantity at branch).
     */
    public function adjustIngredientStockManually(
        int $branchId,
        int $ingredientId,
        float $quantityDelta,
        int $userId,
        string $notes
    ): void {
        $ingredient = Ingredient::findOrFail($ingredientId);

        if ($ingredient->track_stock === 'no') {
            throw new \InvalidArgumentException('This ingredient does not track stock.');
        }

        if (abs($quantityDelta) < 0.0001) {
            throw new \InvalidArgumentException('Quantity change must be non-zero.');
        }

        DB::transaction(function () use ($branchId, $ingredient, $quantityDelta, $userId, $notes) {
            if ($quantityDelta < 0) {
                $deduct = abs($quantityDelta);
                $unitCost = $this->deductIngredientQuantityFifo($branchId, (int) $ingredient->id, $deduct);
                StockMovement::create([
                    'branch_id' => $branchId,
                    'ingredient_id' => $ingredient->id,
                    'type' => 'adjustment',
                    'movement' => 'out',
                    'quantity' => $deduct,
                    'unit_id' => $ingredient->base_unit_id,
                    'unit_cost' => $unitCost,
                    'reference_type' => null,
                    'reference_id' => null,
                    'notes' => $notes,
                    'created_by' => $userId,
                ]);
            } else {
                $batch = $this->incrementIngredientQuantityLatest($branchId, $ingredient, $quantityDelta);
                StockMovement::create([
                    'branch_id' => $branchId,
                    'ingredient_id' => $ingredient->id,
                    'type' => 'adjustment',
                    'movement' => 'in',
                    'quantity' => $quantityDelta,
                    'unit_id' => $ingredient->base_unit_id,
                    'unit_cost' => $batch->average_cost,
                    'reference_type' => null,
                    'reference_id' => null,
                    'notes' => $notes,
                    'created_by' => $userId,
                ]);
            }
        });
    }

    /**
     * Manual stock adjustment for a single-type menu item that tracks inventory (menu_item_stock batches).
     * Recipe-type items are not adjusted here — use ingredient adjustments instead.
     */
    public function adjustMenuItemStockManually(
        int $branchId,
        int $menuItemId,
        float $quantityDelta,
        int $userId,
        string $notes
    ): void {
        $menuItem = MenuItem::findOrFail($menuItemId);

        if ($menuItem->type !== 'single') {
            throw new \InvalidArgumentException('Only single-type menu items with tracked inventory can be adjusted here.');
        }

        if (! $menuItem->track_inventory) {
            throw new \InvalidArgumentException('This menu item does not track inventory.');
        }

        if (abs($quantityDelta) < 0.0001) {
            throw new \InvalidArgumentException('Quantity change must be non-zero.');
        }

        $unitId = 'pcs';
        $unitPrice = (float) ($menuItem->cost ?? 0);

        DB::transaction(function () use ($branchId, $menuItemId, $quantityDelta, $userId, $notes, $unitId, $unitPrice) {
            if ($quantityDelta > 0) {
                $batch = MenuItemStock::firstOrCreate(
                    [
                        'branch_id' => $branchId,
                        'menu_item_id' => $menuItemId,
                        'unit_price' => $unitPrice,
                        'expiry_date' => null,
                    ],
                    [
                        'quantity' => 0,
                        'last_restocked_at' => now(),
                    ]
                );
                $batch->increment('quantity', $quantityDelta);
                $batch->update(['last_restocked_at' => now()]);

                StockMovement::create([
                    'branch_id' => $branchId,
                    'ingredient_id' => null,
                    'menu_item_id' => $menuItemId,
                    'type' => 'adjustment',
                    'movement' => 'in',
                    'quantity' => $quantityDelta,
                    'unit_id' => $unitId,
                    'unit_cost' => $unitPrice,
                    'reference_type' => null,
                    'reference_id' => null,
                    'notes' => $notes,
                    'created_by' => $userId,
                ]);

                return;
            }

            $deduct = abs($quantityDelta);
            $stockBatches = MenuItemStock::where('branch_id', $branchId)
                ->where('menu_item_id', $menuItemId)
                ->where('quantity', '>', 0)
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            $remaining = $deduct;
            foreach ($stockBatches as $batch) {
                if ($remaining <= 0.0001) {
                    break;
                }
                $batchQty = (float) $batch->quantity;
                $take = min($batchQty, $remaining);
                $batch->decrement('quantity', $take);
                $remaining -= $take;
                $batch->refresh();
                if ((float) $batch->quantity <= 0.0001) {
                    $batch->delete();
                }
            }

            if ($remaining > 0.0001) {
                throw new \InvalidArgumentException('Cannot reduce stock below available quantity for this menu item.');
            }

            StockMovement::create([
                'branch_id' => $branchId,
                'ingredient_id' => null,
                'menu_item_id' => $menuItemId,
                'type' => 'adjustment',
                'movement' => 'out',
                'quantity' => $deduct,
                'unit_id' => $unitId,
                'unit_cost' => $unitPrice,
                'reference_type' => null,
                'reference_id' => null,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });

        $this->menuItemCosts->syncMenuItemById($menuItemId);
    }

    /**
     * Signed consumption-unit effect of a manual adjustment movement (+in / -out).
     */
    public function signedAdjustmentDelta(StockMovement $movement): float
    {
        $qty = abs((float) $movement->quantity);

        return $movement->movement === 'in' ? $qty : -$qty;
    }

    /**
     * Reverse stock from a manual adjustment and delete the movement.
     */
    public function deleteManualAdjustment(StockMovement $movement): void
    {
        if ($movement->type !== 'adjustment') {
            throw new \InvalidArgumentException('Only adjustment movements can be deleted.');
        }

        if (! $movement->ingredient_id && ! $movement->menu_item_id) {
            throw new \InvalidArgumentException('This adjustment has no item to reverse.');
        }

        DB::transaction(function () use ($movement) {
            $this->reverseManualAdjustmentStock($movement);
            $movement->delete();

            if ($movement->menu_item_id) {
                $this->menuItemCosts->syncMenuItemById((int) $movement->menu_item_id);
            }
        });
    }

    /**
     * Replace a manual adjustment: reverse original stock effect, remove the row, apply a new delta.
     */
    public function replaceManualAdjustment(
        StockMovement $movement,
        float $newQuantityDelta,
        int $userId,
        string $notes
    ): void {
        if ($movement->type !== 'adjustment') {
            throw new \InvalidArgumentException('Only adjustment movements can be edited.');
        }

        if (abs($newQuantityDelta) < 0.0001) {
            throw new \InvalidArgumentException('Quantity change must be non-zero.');
        }

        $branchId = (int) $movement->branch_id;
        $ingredientId = $movement->ingredient_id ? (int) $movement->ingredient_id : null;
        $menuItemId = $movement->menu_item_id ? (int) $movement->menu_item_id : null;

        DB::transaction(function () use ($movement, $newQuantityDelta, $userId, $notes, $branchId, $ingredientId, $menuItemId) {
            $this->reverseManualAdjustmentStock($movement);
            $movement->delete();

            if ($ingredientId) {
                $this->adjustIngredientStockManually($branchId, $ingredientId, $newQuantityDelta, $userId, $notes);
            } elseif ($menuItemId) {
                $this->adjustMenuItemStockManually($branchId, $menuItemId, $newQuantityDelta, $userId, $notes);
            } else {
                throw new \InvalidArgumentException('This adjustment has no item to replace.');
            }
        });
    }

    /**
     * Undo only the stock quantity effect of a manual adjustment (does not delete the row).
     */
    protected function reverseManualAdjustmentStock(StockMovement $movement): void
    {
        $signed = $this->signedAdjustmentDelta($movement);
        $reverseDelta = -$signed;
        $branchId = (int) $movement->branch_id;

        if ($movement->ingredient_id) {
            $this->applyIngredientStockDeltaOnly($branchId, (int) $movement->ingredient_id, $reverseDelta);

            return;
        }

        if ($movement->menu_item_id) {
            $this->applyMenuItemStockDeltaOnly($branchId, (int) $movement->menu_item_id, $reverseDelta);
        }
    }

    protected function applyIngredientStockDeltaOnly(int $branchId, int $ingredientId, float $quantityDelta): void
    {
        $ingredient = Ingredient::findOrFail($ingredientId);

        if ($quantityDelta < 0) {
            $this->deductIngredientQuantityFifo($branchId, $ingredientId, abs($quantityDelta));
        } else {
            $this->incrementIngredientQuantityLatest($branchId, $ingredient, $quantityDelta);
        }
    }

    protected function applyMenuItemStockDeltaOnly(int $branchId, int $menuItemId, float $quantityDelta): void
    {
        $menuItem = MenuItem::findOrFail($menuItemId);
        $unitPrice = (float) ($menuItem->cost ?? 0);

        if ($quantityDelta > 0) {
            $batch = MenuItemStock::firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'menu_item_id' => $menuItemId,
                    'unit_price' => $unitPrice,
                    'expiry_date' => null,
                ],
                [
                    'quantity' => 0,
                    'last_restocked_at' => now(),
                ]
            );
            $batch->increment('quantity', $quantityDelta);
            $batch->update(['last_restocked_at' => now()]);

            return;
        }

        $deduct = abs($quantityDelta);
        $stockBatches = MenuItemStock::where('branch_id', $branchId)
            ->where('menu_item_id', $menuItemId)
            ->where('quantity', '>', 0)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();

        $remaining = $deduct;
        foreach ($stockBatches as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }
            $take = min((float) $batch->quantity, $remaining);
            $batch->decrement('quantity', $take);
            $remaining -= $take;
            $batch->refresh();
            if ((float) $batch->quantity <= 0.0001) {
                $batch->delete();
            }
        }

        if ($remaining > 0.0001) {
            throw new \InvalidArgumentException('Cannot reverse this adjustment — available menu item stock is too low.');
        }
    }

    /**
     * Return inventory to branch stock after an order refund (when staff chose restock).
     */
    public function restockOrderItemForRefund(
        OrderItem $orderItem,
        float $restockQty,
        int $branchId,
        int $userId,
        ?int $referenceId,
        string $orderNumber,
        ?string $referenceType = null
    ): void {
        if ($restockQty <= 0) {
            return;
        }

        $referenceType ??= OrderRefundLine::class;

        if ($orderItem->deal_id) {
            $this->restockDealInventoryForRefund(
                $orderItem,
                $restockQty,
                $branchId,
                $userId,
                $referenceId,
                $orderNumber,
                $referenceType
            );

            return;
        }

        $menuItem = $orderItem->menuItem;

        if ($menuItem && $menuItem->track_inventory) {
            if ($menuItem->type === 'single') {
                $this->restockMenuItemStockAfterRefund($menuItem, $restockQty, $branchId, $userId, $referenceId, $orderNumber, $referenceType);
            } elseif ($menuItem->type === 'recipe') {
                $this->restockRecipeIngredientsAfterRefund($orderItem, $restockQty, $branchId, $userId, $referenceId, $orderNumber, $referenceType);
            }
        }

        $this->restockAddonInventoryForRefund(
            $orderItem,
            $restockQty,
            $branchId,
            $userId,
            $referenceId,
            $orderNumber,
            $referenceType
        );
    }

    /**
     * Return inventory for all remaining billable quantities when an order is deleted.
     */
    public function restockOrderForDelete(Order $order, int $userId): void
    {
        foreach ($order->items as $orderItem) {
            $billable = max(0, (float) $orderItem->quantity - (float) $orderItem->quantity_refunded);
            if ($billable <= 0.0001) {
                continue;
            }

            $this->restockOrderItemForRefund(
                $orderItem,
                $billable,
                (int) $order->branch_id,
                $userId,
                (int) $order->id,
                (string) $order->order_number,
                Order::class
            );
        }

        StockMovement::query()
            ->where('reference_type', OrderItem::class)
            ->whereIn('reference_id', $order->items->pluck('id'))
            ->delete();
    }

    protected function restockMenuItemStockAfterRefund(
        MenuItem $menuItem,
        float $restockQty,
        int $branchId,
        int $userId,
        ?int $referenceId,
        string $orderNumber,
        string $referenceType = OrderRefundLine::class
    ): void {
        $unitPrice = (float) ($menuItem->cost ?? 0);

        $batch = MenuItemStock::firstOrCreate(
            [
                'branch_id' => $branchId,
                'menu_item_id' => $menuItem->id,
                'unit_price' => $unitPrice,
                'expiry_date' => null,
            ],
            [
                'quantity' => 0,
                'last_restocked_at' => now(),
            ]
        );

        $batch->increment('quantity', $restockQty);
        $batch->update(['last_restocked_at' => now()]);

        Log::info('Menu item stock restocked after refund', [
            'menu_item_id' => $menuItem->id,
            'quantity' => $restockQty,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        $this->menuItemCosts->syncMenuItemById((int) $menuItem->id);
    }

    protected function restockRecipeIngredientsAfterRefund(
        OrderItem $orderItem,
        float $restockQty,
        int $branchId,
        int $userId,
        ?int $referenceId,
        string $orderNumber,
        string $referenceType = OrderRefundLine::class
    ): void {
        $originalQty = (float) $orderItem->quantity;
        $scale = $originalQty > 0.0001 ? min(1.0, $restockQty / $originalQty) : 1.0;

        $restoredByIngredient = $this->restockIngredientsFromOrderItemMovements(
            $orderItem,
            $branchId,
            $userId,
            $referenceId,
            $orderNumber,
            $referenceType,
            $scale
        );

        $menuItem = $orderItem->menuItem;
        if (! $menuItem->relationLoaded('recipes')) {
            $menuItem->load('defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient', 'legacyRecipeLines.ingredient');
        }

        $recipes = $this->resolveRecipesForOrderItem($orderItem);
        if (! $recipes || $recipes->isEmpty()) {
            return;
        }

        $companyId = (int) Branch::query()->whereKey($branchId)->value('company_id');

        foreach ($recipes as $recipe) {
            if (! $recipe->relationLoaded('ingredient')) {
                $recipe->load('ingredient');
            }

            $ingredient = $recipe->ingredient;
            if (! $ingredient || $ingredient->track_stock === 'no') {
                continue;
            }

            $requiredQuantityInRecipeUnit = $recipe->effective_quantity * $restockQty;
            $requiredQuantity = IngredientQuantity::toConsumptionQuantity(
                $ingredient,
                $requiredQuantityInRecipeUnit,
                $recipe->recipeUnitId()
            );

            if ($requiredQuantity === null || $requiredQuantity <= 0) {
                continue;
            }

            $alreadyRestored = $restoredByIngredient[(int) $ingredient->id] ?? 0.0;
            $remainingQuantity = round(max(0, $requiredQuantity - $alreadyRestored), 4);
            if ($remainingQuantity <= 0.0001) {
                continue;
            }

            $branchStock = BranchStock::firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'ingredient_id' => $ingredient->id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'unit_id' => $this->branchStockUnitId($ingredient, $companyId),
                    'average_cost' => $ingredient->cost_per_unit,
                ]
            );

            $branchStock->increment('quantity', $remainingQuantity);

            StockMovement::create([
                'branch_id' => $branchId,
                'ingredient_id' => $ingredient->id,
                'type' => 'adjustment',
                'movement' => 'in',
                'quantity' => $remainingQuantity,
                'unit_id' => $ingredient->base_unit_id,
                'unit_cost' => $branchStock->average_cost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $referenceType === Order::class
                    ? "Order delete restock gap #{$orderNumber} (order item #{$orderItem->id})"
                    : "Refund restock gap order #{$orderNumber} (order item #{$orderItem->id})",
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * @return array<int, float> Ingredient ID => quantity restored from sale movements
     */
    protected function restockIngredientsFromOrderItemMovements(
        OrderItem $orderItem,
        int $branchId,
        int $userId,
        ?int $referenceId,
        string $orderNumber,
        string $referenceType,
        float $quantityScale = 1.0
    ): array {
        $quantityScale = max(0.0, min(1.0, $quantityScale));
        if ($quantityScale <= 0.0001) {
            return [];
        }

        $movements = StockMovement::query()
            ->where('reference_type', OrderItem::class)
            ->where('reference_id', $orderItem->id)
            ->where('type', 'sale')
            ->where('movement', 'out')
            ->whereNotNull('ingredient_id')
            ->get();

        if ($movements->isEmpty()) {
            return [];
        }

        $restoredByIngredient = [];
        $companyId = (int) Branch::query()->whereKey($branchId)->value('company_id');

        foreach ($movements->groupBy('ingredient_id') as $ingredientId => $ingredientMovements) {
            $ingredient = Ingredient::withoutGlobalScopes()->find($ingredientId);
            if (! $ingredient || $ingredient->track_stock === 'no') {
                continue;
            }

            foreach ($ingredientMovements->groupBy(
                fn (StockMovement $movement) => number_format(round((float) $movement->unit_cost, 4), 4, '.', '')
            ) as $groupedMovements) {
                $quantity = round(
                    $groupedMovements->sum(fn (StockMovement $movement) => (float) $movement->quantity) * $quantityScale,
                    4
                );
                if ($quantity <= 0.0001) {
                    continue;
                }

                $unitCost = (float) $groupedMovements->first()->unit_cost;
                $batch = BranchStock::withoutGlobalScopes()
                    ->where('branch_id', $branchId)
                    ->where('ingredient_id', $ingredientId)
                    ->whereRaw('ABS(average_cost - ?) < 0.01', [$unitCost])
                    ->first();

                if (! $batch) {
                    $batch = BranchStock::withoutGlobalScopes()->create([
                        'branch_id' => $branchId,
                        'ingredient_id' => (int) $ingredientId,
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'unit_id' => $this->branchStockUnitId($ingredient, $companyId),
                        'average_cost' => $unitCost,
                        'last_restocked_at' => now(),
                    ]);
                }

                $batch->increment('quantity', $quantity);
                $batch->update(['last_restocked_at' => now()]);

                StockMovement::create([
                    'branch_id' => $branchId,
                    'ingredient_id' => (int) $ingredientId,
                    'type' => 'adjustment',
                    'movement' => 'in',
                    'quantity' => $quantity,
                    'unit_id' => $ingredient->base_unit_id,
                    'unit_cost' => $unitCost,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'notes' => $referenceType === Order::class
                        ? "Order delete restock #{$orderNumber} (order item #{$orderItem->id})"
                        : "Refund restock order #{$orderNumber} (order item #{$orderItem->id})",
                    'created_by' => $userId,
                ]);

                $restoredByIngredient[(int) $ingredientId] = round(
                    ($restoredByIngredient[(int) $ingredientId] ?? 0.0) + $quantity,
                    4
                );
            }
        }

        return $restoredByIngredient;
    }

    /**
     * Restock tracked addons for a refunded (or deleted) quantity of an order line.
     */
    public function restockAddonInventoryForRefund(
        OrderItem $orderItem,
        float $billableQty,
        int $branchId,
        int $userId,
        ?int $referenceId,
        string $orderNumber,
        string $referenceType = OrderRefundLine::class
    ): void {
        $this->restockAddonInventoryForDelete(
            $orderItem,
            $billableQty,
            $branchId,
            $userId,
            (int) ($referenceId ?? $orderItem->order_id),
            $orderNumber,
            $referenceType
        );
    }

    protected function restockAddonInventoryForDelete(
        OrderItem $orderItem,
        float $billableQty,
        int $branchId,
        int $userId,
        int $orderId,
        string $orderNumber,
        string $referenceType = Order::class
    ): void {
        $addons = is_array($orderItem->addons) ? $orderItem->addons : [];
        $normalized = app(PosAddonService::class)->normalizeAddons($addons);
        if ($normalized === []) {
            return;
        }

        $catalog = ProductAddon::query()
            ->whereIn('id', collect($normalized)->pluck('id'))
            ->with(['recipes.ingredient', 'menuItem'])
            ->get()
            ->keyBy('id');

        $companyId = (int) Branch::query()->whereKey($branchId)->value('company_id');

        foreach ($normalized as $row) {
            /** @var ProductAddon|null $addon */
            $addon = $catalog->get($row['id']);
            if (! $addon || ! $addon->track_inventory) {
                continue;
            }

            $multiplier = $billableQty * (float) $row['quantity'];

            if ($addon->type === ProductAddon::TYPE_SINGLE && $addon->menuItem) {
                $menuItem = $addon->menuItem;
                $unitPrice = (float) ($menuItem->cost ?? 0);
                $batch = MenuItemStock::firstOrCreate(
                    [
                        'branch_id' => $branchId,
                        'menu_item_id' => $menuItem->id,
                        'unit_price' => $unitPrice,
                        'expiry_date' => null,
                    ],
                    [
                        'quantity' => 0,
                        'last_restocked_at' => now(),
                    ]
                );
                $batch->increment('quantity', $multiplier);
                $batch->update(['last_restocked_at' => now()]);
                $this->menuItemCosts->syncMenuItemById((int) $menuItem->id);

                continue;
            }

            if ($addon->type !== ProductAddon::TYPE_RECIPE) {
                continue;
            }

            foreach ($addon->recipes as $recipe) {
                $ingredient = $recipe->ingredient;
                if (! $ingredient || $ingredient->track_stock === 'no') {
                    continue;
                }

                $requiredInRecipeUnit = $recipe->effective_quantity * $multiplier;
                $requiredQuantity = IngredientQuantity::toConsumptionQuantity(
                    $ingredient,
                    $requiredInRecipeUnit,
                    $recipe->recipeUnitId()
                );

                if ($requiredQuantity === null || $requiredQuantity <= 0) {
                    continue;
                }

                $branchStock = BranchStock::firstOrCreate(
                    [
                        'branch_id' => $branchId,
                        'ingredient_id' => $ingredient->id,
                    ],
                    [
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'unit_id' => $this->branchStockUnitId($ingredient, $companyId),
                        'average_cost' => $ingredient->cost_per_unit,
                    ]
                );

                $branchStock->increment('quantity', $requiredQuantity);

                StockMovement::create([
                    'branch_id' => $branchId,
                    'ingredient_id' => $ingredient->id,
                    'type' => 'adjustment',
                    'movement' => 'in',
                    'quantity' => $requiredQuantity,
                    'unit_id' => $ingredient->base_unit_id,
                    'unit_cost' => $branchStock->average_cost,
                    'reference_type' => $referenceType,
                    'reference_id' => $orderId,
                    'notes' => $referenceType === Order::class
                        ? "Order delete addon restock #{$orderNumber} (order item #{$orderItem->id})"
                        : "Refund addon restock order #{$orderNumber} (order item #{$orderItem->id})",
                    'created_by' => $userId,
                ]);
            }
        }
    }

    protected function resolveRecipesForOrderItem(OrderItem $orderItem): \Illuminate\Support\Collection
    {
        $menuItem = $orderItem->menuItem;

        if (! $menuItem->relationLoaded('recipes')) {
            $menuItem->load('defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient', 'legacyRecipeLines.ingredient');
        }

        [$variantId, $optionName] = MenuItem::variantContextFromOrderSelection(
            is_array($orderItem->variants) ? $orderItem->variants : null
        );

        return $menuItem->resolveRecipes($variantId, $optionName);
    }

    /**
     * Ingredient stock batches for a branch, oldest restock first (FIFO).
     */
    protected function ingredientStockBatches(int $branchId, int $ingredientId): \Illuminate\Support\Collection
    {
        return BranchStock::query()
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->where('quantity', '>', 0)
            ->orderByRaw('CASE WHEN last_restocked_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('last_restocked_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Most recently restocked ingredient batch (LIFO target for increases).
     */
    protected function ingredientLatestStockBatch(int $branchId, int $ingredientId): ?BranchStock
    {
        return BranchStock::query()
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->orderByRaw('CASE WHEN last_restocked_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('last_restocked_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Deduct across cost batches oldest-first. Returns weighted average unit cost for the movement.
     */
    protected function deductIngredientQuantityFifo(int $branchId, int $ingredientId, float $deduct): float
    {
        $totalOnHand = (float) BranchStock::query()
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->sum('quantity');

        if ($totalOnHand < $deduct - 0.0001) {
            throw new \InvalidArgumentException('Cannot reduce stock below zero for this ingredient.');
        }

        $remaining = $deduct;
        $costTotal = 0.0;

        foreach ($this->ingredientStockBatches($branchId, $ingredientId) as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }

            $batchQty = (float) $batch->quantity;
            if ($batchQty <= 0.0001) {
                continue;
            }

            $take = min($batchQty, $remaining);
            $batch->decrement('quantity', $take);
            $costTotal += $take * (float) $batch->average_cost;
            $remaining -= $take;
            $this->cleanupEmptyBranchStockBatch($batch->fresh());
        }

        if ($remaining > 0.0001) {
            throw new \InvalidArgumentException('Cannot reduce stock below zero for this ingredient.');
        }

        return round($costTotal / $deduct, 4);
    }

    /**
     * Add stock to the newest batch, or create one when none exist.
     */
    protected function incrementIngredientQuantityLatest(int $branchId, Ingredient $ingredient, float $quantity): BranchStock
    {
        $batch = $this->ingredientLatestStockBatch($branchId, (int) $ingredient->id);

        if (! $batch) {
            $companyId = (int) Branch::query()->whereKey($branchId)->value('company_id');
            $batch = BranchStock::create([
                'branch_id' => $branchId,
                'ingredient_id' => $ingredient->id,
                'quantity' => 0,
                'reserved_quantity' => 0,
                'unit_id' => $this->branchStockUnitId($ingredient, $companyId),
                'average_cost' => $ingredient->cost_per_unit,
                'last_restocked_at' => now(),
            ]);
        }

        $batch->increment('quantity', $quantity);
        $batch->update(['last_restocked_at' => now()]);

        return $batch->fresh();
    }

    protected function totalIngredientAvailableQuantity(int $branchId, int $ingredientId): float
    {
        $batches = BranchStock::query()
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->get();

        return $batches->sum(fn (BranchStock $batch) => max(
            0,
            (float) $batch->quantity - (float) $batch->reserved_quantity
        ));
    }

    protected function cleanupEmptyBranchStockBatch(BranchStock $batch): void
    {
        $batch->refresh();
        if ((float) $batch->quantity <= 0.0001 && (float) $batch->reserved_quantity <= 0.0001) {
            $batch->delete();
        }
    }

    /**
     * Reserve ingredient stock across all batches (FIFO).
     */
    protected function reserveIngredientQuantityFifo(
        int $branchId,
        Ingredient $ingredient,
        float $requiredQuantity,
        OrderItem $orderItem,
        string $notes
    ): void {
        $available = $this->totalIngredientAvailableQuantity($branchId, $ingredient->id);
        if ($available < $requiredQuantity - 0.0001) {
            $unitAbbr = $ingredient->unit_abbreviation ?? $ingredient->base_unit_id ?? 'units';
            throw new \Exception(
                "Insufficient stock for {$ingredient->name}. ".
                "Required: {$requiredQuantity} {$unitAbbr}, Available: {$available} {$unitAbbr}"
            );
        }

        $remaining = $requiredQuantity;
        foreach ($this->ingredientStockBatches($branchId, $ingredient->id) as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }

            $batchAvailable = max(0, (float) $batch->quantity - (float) $batch->reserved_quantity);
            if ($batchAvailable <= 0.0001) {
                continue;
            }

            $take = min($batchAvailable, $remaining);
            $batch->increment('reserved_quantity', $take);

            StockMovement::create([
                'branch_id' => $branchId,
                'ingredient_id' => $ingredient->id,
                'type' => 'sale',
                'movement' => 'out',
                'quantity' => $take,
                'unit_id' => $ingredient->base_unit_id,
                'unit_cost' => $batch->average_cost,
                'reference_type' => OrderItem::class,
                'reference_id' => $orderItem->id,
                'notes' => $notes,
            ]);

            $remaining -= $take;
        }
    }

    /**
     * Finalize ingredient deduction from existing sale movements (created during reserve).
     */
    protected function finalizeIngredientFromSaleMovements(
        int $branchId,
        Ingredient $ingredient,
        $movements
    ): void {
        foreach ($movements as $movement) {
            $quantity = (float) $movement->quantity;
            if ($quantity <= 0.0001) {
                continue;
            }

            $unitCost = (float) $movement->unit_cost;
            $batch = BranchStock::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('ingredient_id', $ingredient->id)
                ->whereRaw('ABS(average_cost - ?) < 0.01', [$unitCost])
                ->first();

            if ($batch) {
                $fromReserved = min(max(0, (float) $batch->reserved_quantity), $quantity);
                if ($fromReserved > 0.0001) {
                    $batch->decrement('reserved_quantity', $fromReserved);
                }

                $batch->decrement('quantity', $quantity);
                $this->cleanupEmptyBranchStockBatch($batch->fresh());

                continue;
            }

            $remaining = $quantity;
            foreach ($this->ingredientStockBatches($branchId, $ingredient->id) as $fallbackBatch) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $fromReserved = min(max(0, (float) $fallbackBatch->reserved_quantity), $remaining);
                if ($fromReserved > 0.0001) {
                    $fallbackBatch->decrement('reserved_quantity', $fromReserved);
                }

                $take = min(max(0, (float) $fallbackBatch->quantity), $remaining);
                if ($take <= 0.0001) {
                    continue;
                }

                $fallbackBatch->decrement('quantity', $take);
                $remaining -= $take;
                $this->cleanupEmptyBranchStockBatch($fallbackBatch->fresh());
            }
        }
    }

    /**
     * Deduct ingredient stock FIFO and record sale movements (when reserve was skipped).
     */
    protected function deductIngredientQuantityFifoWithMovements(
        int $branchId,
        Ingredient $ingredient,
        float $requiredQuantity,
        OrderItem $orderItem,
        string $notes
    ): void {
        $remaining = $requiredQuantity;

        foreach ($this->ingredientStockBatches($branchId, $ingredient->id) as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }

            $reserved = max(0, (float) $batch->reserved_quantity);
            if ($reserved <= 0.0001) {
                continue;
            }

            $take = min($reserved, $remaining);
            $batch->decrement('reserved_quantity', $take);
            $batch->decrement('quantity', $take);
            $remaining -= $take;
            $this->cleanupEmptyBranchStockBatch($batch->fresh());
        }

        if ($remaining <= 0.0001) {
            return;
        }

        foreach ($this->ingredientStockBatches($branchId, $ingredient->id) as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }

            $qty = max(0, (float) $batch->quantity);
            if ($qty <= 0.0001) {
                continue;
            }

            $take = min($qty, $remaining);
            $unitCost = (float) $batch->average_cost;
            $batch->decrement('quantity', $take);
            $remaining -= $take;
            $this->cleanupEmptyBranchStockBatch($batch->fresh());

            StockMovement::create([
                'branch_id' => $branchId,
                'ingredient_id' => $ingredient->id,
                'type' => 'sale',
                'movement' => 'out',
                'quantity' => $take,
                'unit_id' => $ingredient->base_unit_id,
                'unit_cost' => $unitCost,
                'reference_type' => OrderItem::class,
                'reference_id' => $orderItem->id,
                'notes' => $notes,
            ]);
        }
    }

    /**
     * Finalize ingredient deduction across batches (FIFO), consuming reserved stock first.
     */
    protected function finalizeIngredientQuantityFifo(
        int $branchId,
        int $ingredientId,
        float $requiredQuantity
    ): void {
        $remaining = $requiredQuantity;

        foreach ($this->ingredientStockBatches($branchId, $ingredientId) as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }

            $reserved = max(0, (float) $batch->reserved_quantity);
            if ($reserved <= 0.0001) {
                continue;
            }

            $take = min($reserved, $remaining);
            $batch->decrement('reserved_quantity', $take);
            $batch->decrement('quantity', $take);
            $remaining -= $take;
            $this->cleanupEmptyBranchStockBatch($batch->fresh());
        }

        if ($remaining > 0.0001) {
            foreach ($this->ingredientStockBatches($branchId, $ingredientId) as $batch) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $qty = max(0, (float) $batch->quantity);
                if ($qty <= 0.0001) {
                    continue;
                }

                $take = min($qty, $remaining);
                $batch->decrement('quantity', $take);
                $remaining -= $take;
                $this->cleanupEmptyBranchStockBatch($batch->fresh());
            }
        }
    }

    /**
     * Release reserved ingredient stock across batches (FIFO).
     */
    protected function releaseIngredientReservedFifo(
        int $branchId,
        int $ingredientId,
        float $quantityToRelease
    ): void {
        $remaining = $quantityToRelease;

        foreach ($this->ingredientStockBatches($branchId, $ingredientId) as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }

            $reserved = max(0, (float) $batch->reserved_quantity);
            if ($reserved <= 0.0001) {
                continue;
            }

            $take = min($reserved, $remaining);
            $batch->decrement('reserved_quantity', $take);
            $remaining -= $take;
        }
    }

    /**
     * @param  list<array<string, mixed>>|null  $addons
     */
    public function checkAddonsAvailability(?array $addons, float $orderItemQuantity, int $branchId): array
    {
        $normalized = app(PosAddonService::class)->normalizeAddons($addons);
        if ($normalized === []) {
            return ['can_sell' => true, 'error_message' => null];
        }

        $catalog = ProductAddon::query()
            ->whereIn('id', collect($normalized)->pluck('id'))
            ->with(['recipes.ingredient', 'menuItem'])
            ->get()
            ->keyBy('id');

        foreach ($normalized as $row) {
            /** @var ProductAddon|null $addon */
            $addon = $catalog->get($row['id']);
            if (! $addon || ! $addon->track_inventory) {
                continue;
            }

            $multiplier = $orderItemQuantity * (float) $row['quantity'];

            if ($addon->type === ProductAddon::TYPE_SINGLE) {
                $linked = $addon->menuItem;
                if (! $linked) {
                    return [
                        'can_sell' => false,
                        'error_message' => "Addon \"{$addon->name}\" has no linked menu item for stock.",
                    ];
                }

                $totalAvailable = MenuItemStock::where('branch_id', $branchId)
                    ->where('menu_item_id', $linked->id)
                    ->sum('quantity');

                if ($totalAvailable < $multiplier) {
                    return [
                        'can_sell' => false,
                        'error_message' => "Insufficient stock for addon \"{$addon->name}\". Required: {$multiplier}, Available: {$totalAvailable}",
                    ];
                }

                continue;
            }

            if ($addon->type !== ProductAddon::TYPE_RECIPE) {
                continue;
            }

            foreach ($addon->recipes as $recipe) {
                $ingredient = $recipe->ingredient;
                if (! $ingredient || $ingredient->track_stock === 'no') {
                    continue;
                }

                $requiredInRecipeUnit = $recipe->effective_quantity * $multiplier;
                $requiredQuantity = IngredientQuantity::toConsumptionQuantity(
                    $ingredient,
                    $requiredInRecipeUnit,
                    $recipe->recipeUnitId()
                );

                if ($requiredQuantity === null) {
                    return [
                        'can_sell' => false,
                        'error_message' => IngredientQuantity::conversionErrorMessage($ingredient, $recipe->recipeUnitId()),
                    ];
                }

                $available = $this->totalIngredientAvailableQuantity($branchId, $ingredient->id);
                if ($available < $requiredQuantity - 0.0001) {
                    $unitAbbr = $ingredient->unit_abbreviation ?? $ingredient->base_unit_id ?? 'units';

                    return [
                        'can_sell' => false,
                        'error_message' => "Insufficient stock for addon \"{$addon->name}\" ({$ingredient->name}). Required: {$requiredQuantity} {$unitAbbr}, Available: {$available} {$unitAbbr}",
                    ];
                }
            }
        }

        return ['can_sell' => true, 'error_message' => null];
    }

    protected function reserveAddonInventoryForOrderItem(OrderItem $orderItem, int $branchId): void
    {
        $addons = is_array($orderItem->addons) ? $orderItem->addons : [];
        $normalized = app(PosAddonService::class)->normalizeAddons($addons);
        if ($normalized === []) {
            return;
        }

        if (! $orderItem->relationLoaded('order')) {
            $orderItem->load('order');
        }

        $orderNumber = $orderItem->order ? $orderItem->order->order_number : "Order #{$orderItem->order_id}";
        $catalog = ProductAddon::query()
            ->whereIn('id', collect($normalized)->pluck('id'))
            ->with(['recipes.ingredient', 'menuItem'])
            ->get()
            ->keyBy('id');

        foreach ($normalized as $row) {
            /** @var ProductAddon|null $addon */
            $addon = $catalog->get($row['id']);
            if (! $addon || ! $addon->track_inventory) {
                continue;
            }

            $multiplier = (float) $orderItem->quantity * (float) $row['quantity'];

            if ($addon->type === ProductAddon::TYPE_SINGLE && $addon->menuItem) {
                $totalAvailable = MenuItemStock::where('branch_id', $branchId)
                    ->where('menu_item_id', $addon->menuItem->id)
                    ->sum('quantity');

                if ($totalAvailable < $multiplier) {
                    throw new \Exception("Insufficient stock for addon {$addon->name}.");
                }

                continue;
            }

            if ($addon->type !== ProductAddon::TYPE_RECIPE) {
                continue;
            }

            foreach ($addon->recipes as $recipe) {
                $ingredient = $recipe->ingredient;
                if (! $ingredient || $ingredient->track_stock === 'no') {
                    continue;
                }

                $requiredInRecipeUnit = $recipe->effective_quantity * $multiplier;
                $requiredQuantity = $this->recipeQuantityInConsumptionUnits(
                    $ingredient,
                    $requiredInRecipeUnit,
                    $recipe->recipeUnitId()
                );

                $this->reserveIngredientQuantityFifo(
                    $branchId,
                    $ingredient,
                    $requiredQuantity,
                    $orderItem,
                    "Reserved addon {$addon->name} for {$orderNumber}"
                );
            }
        }
    }

    protected function finalizeAddonInventoryForOrderItem(OrderItem $orderItem, int $branchId, string $orderNumber): void
    {
        $addons = is_array($orderItem->addons) ? $orderItem->addons : [];
        $normalized = app(PosAddonService::class)->normalizeAddons($addons);
        if ($normalized === []) {
            return;
        }

        $catalog = ProductAddon::query()
            ->whereIn('id', collect($normalized)->pluck('id'))
            ->with(['recipes.ingredient', 'menuItem'])
            ->get()
            ->keyBy('id');

        foreach ($normalized as $row) {
            /** @var ProductAddon|null $addon */
            $addon = $catalog->get($row['id']);
            if (! $addon || ! $addon->track_inventory) {
                continue;
            }

            $multiplier = (float) $orderItem->quantity * (float) $row['quantity'];

            if ($addon->type === ProductAddon::TYPE_SINGLE && $addon->menuItem) {
                $remaining = $multiplier;
                $stockBatches = MenuItemStock::where('branch_id', $branchId)
                    ->where('menu_item_id', $addon->menuItem->id)
                    ->where('quantity', '>', 0)
                    ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('expiry_date', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->get();

                foreach ($stockBatches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $deduct = min($remaining, (float) $batch->quantity);
                    $unitCost = (float) ($batch->unit_price ?: $addon->menuItem->cost ?: 0);
                    $batch->decrement('quantity', $deduct);
                    $remaining -= $deduct;

                    $this->recordMenuItemSaleMovement(
                        $branchId,
                        (int) $addon->menuItem->id,
                        (float) $deduct,
                        $unitCost,
                        $orderItem,
                        $orderNumber,
                        "Finalized addon {$addon->name} for completed order #{$orderNumber}"
                    );

                    if ((float) $batch->quantity <= 0) {
                        $batch->delete();
                    }
                }

                continue;
            }

            if ($addon->type !== ProductAddon::TYPE_RECIPE) {
                continue;
            }

            foreach ($addon->recipes as $recipe) {
                $ingredient = $recipe->ingredient;
                if (! $ingredient || $ingredient->track_stock === 'no') {
                    continue;
                }

                $requiredInRecipeUnit = $recipe->effective_quantity * $multiplier;
                $requiredQuantity = IngredientQuantity::toConsumptionQuantity(
                    $ingredient,
                    $requiredInRecipeUnit,
                    $recipe->recipeUnitId()
                );

                if ($requiredQuantity === null) {
                    continue;
                }

                $addonMovementNotes = "Reserved addon {$addon->name} for";
                $saleMovements = StockMovement::query()
                    ->where('reference_type', OrderItem::class)
                    ->where('reference_id', $orderItem->id)
                    ->where('ingredient_id', $ingredient->id)
                    ->where('type', 'sale')
                    ->where('movement', 'out')
                    ->where('notes', 'like', '%'.$addonMovementNotes.'%')
                    ->get();

                if ($saleMovements->isNotEmpty()) {
                    $this->finalizeIngredientFromSaleMovements($branchId, $ingredient, $saleMovements);
                } else {
                    $this->deductIngredientQuantityFifoWithMovements(
                        $branchId,
                        $ingredient,
                        $requiredQuantity,
                        $orderItem,
                        "Finalized addon {$addon->name} for completed order #{$orderNumber}"
                    );
                }

                StockMovement::query()
                    ->where('reference_type', OrderItem::class)
                    ->where('reference_id', $orderItem->id)
                    ->where('ingredient_id', $ingredient->id)
                    ->where('type', 'sale')
                    ->where('movement', 'out')
                    ->where('notes', 'like', '%'.$addonMovementNotes.'%')
                    ->update([
                        'notes' => "Finalized addon {$addon->name} for completed order #{$orderNumber}",
                    ]);
            }
        }
    }

    protected function recipeQuantityInConsumptionUnits(
        Ingredient $ingredient,
        float $quantityInRecipeUnit,
        ?string $recipeUnit,
    ): float {
        if (! $recipeUnit) {
            throw new \Exception("Recipe unit is not set for {$ingredient->name}. Please set the unit in the recipe.");
        }

        $consumptionQty = IngredientQuantity::toConsumptionQuantity($ingredient, $quantityInRecipeUnit, $recipeUnit);
        if ($consumptionQty === null) {
            throw new \Exception(IngredientQuantity::conversionErrorMessage($ingredient, $recipeUnit));
        }

        return $consumptionQty;
    }

    protected function branchStockUnitId(Ingredient $ingredient, int $companyId): int
    {
        $ingredient->loadMissing('consumptionUnit');
        $unitKey = (string) ($ingredient->consumption_unit_id ?? $ingredient->base_unit_id);
        $unitId = $this->unitOfMeasureResolver->resolveId($unitKey, $companyId);

        if (! $unitId) {
            throw new \InvalidArgumentException("Unit not found for ingredient: {$ingredient->name}");
        }

        return $unitId;
    }
}
