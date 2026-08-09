<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\MenuItemStock;
use App\Models\PartyBalanceAdjustment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Support\IngredientQuantity;
use App\Support\MenuItemQuantity;
use App\Support\UnitOfMeasureResolver;
use Illuminate\Database\QueryException;
use App\Support\CurrentShift;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseService
{
    public function __construct(
        protected IngredientCostService $ingredientCosts,
        protected MenuItemCostService $menuItemCosts,
        protected UnitOfMeasureResolver $unitOfMeasureResolver,
    ) {}
    /**
     * Create a new purchase with items and update inventory.
     *
     * @param array $purchaseData Purchase data
     * @param array $items Purchase items data
     * @param int $userId User creating the purchase
     * @return Purchase
     * @throws \Exception
     */
    public function createPurchase(array $purchaseData, array $items, int $userId): Purchase
    {
        return DB::transaction(function () use ($purchaseData, $items, $userId) {
            // Generate purchase number
            $purchaseNumber = Purchase::generatePurchaseNumber($purchaseData['branch_id']);

            // Create purchase
            $purchase = Purchase::create([
                'company_id' => $purchaseData['company_id'],
                'branch_id' => $purchaseData['branch_id'],
                'supplier_id' => $purchaseData['supplier_id'] ?? null,
                'created_by' => $userId,
                'shift_id' => CurrentShift::id((int) $purchaseData['branch_id']),
                'purchase_number' => $purchaseNumber,
                'purchase_date' => $purchaseData['purchase_date'],
                'subtotal' => $purchaseData['subtotal'],
                'tax_amount' => $purchaseData['tax_amount'] ?? 0,
                'discount_amount' => $purchaseData['discount_amount'] ?? 0,
                'total_amount' => $purchaseData['total_amount'],
                'paid_amount' => $purchaseData['paid_amount'] ?? 0,
                'payment_method' => $purchaseData['payment_method'],
                'money_source_id' => $purchaseData['money_source_id'] ?? null,
                'payment_status' => $purchaseData['payment_status'] ?? 'pending',
                'notes' => $purchaseData['notes'] ?? null,
            ]);

            // Validate items
            if (empty($items) || !is_array($items)) {
                throw new \Exception('No items provided in the purchase.');
            }

            // Create purchase items and update inventory
            foreach ($items as $index => $itemData) {
                if (empty($itemData['item_type']) || empty($itemData['item_id'])) {
                    throw new \Exception("Item at index {$index} is missing required fields (item_type or item_id).");
                }

                $this->createPurchaseItem($purchase, $itemData, $purchaseData['branch_id']);
            }

            if ($purchase->paid_amount > 0) {
                if ($purchase->supplier_id) {
                    $this->createSupplierPaymentAtPurchase($purchase, $userId);
                } else {
                    $this->createPurchaseTransaction($purchase);
                }
            }

            // Update supplier balance if supplier is provided
            if ($purchase->supplier_id) {
                $this->updateSupplierBalance($purchase);
            }

            return $purchase;
        });
    }

    /**
     * Check whether purchase lines can be reversed (stock not yet consumed).
     *
     * @throws \Exception
     */
    public function assertPurchaseItemsReversible(Purchase $purchase): void
    {
        $purchase->loadMissing('items');

        foreach ($purchase->items as $item) {
            $this->assertPurchaseItemReversible($item, (int) $purchase->branch_id);
        }
    }

    /**
     * @throws \Exception
     */
    public function assertPurchaseCanBeModified(Purchase $purchase): void
    {
        if ($purchase->trashed()) {
            throw new \Exception('This purchase has already been deleted.');
        }

        $this->assertPurchaseItemsReversible($purchase);
    }

    /**
     * @throws \Exception
     */
    public function assertPurchaseCanBeDeleted(Purchase $purchase): void
    {
        if ($purchase->trashed()) {
            throw new \Exception('This purchase has already been deleted.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $newItems
     * @return list<array{
     *     key: string,
     *     sample_item: ?PurchaseItem,
     *     new_line: ?array<string, mixed>,
     *     old_quantity: float,
     *     new_quantity: float,
     *     reverse_quantity: float,
     *     add_quantity: float
     * }>
     */
    public function purchaseLineStockDeltas(Purchase $purchase, array $newItems): array
    {
        $purchase->loadMissing('items');

        $oldLines = $this->aggregateExistingPurchaseLines($purchase->items);
        $newLines = $this->aggregateRequestedPurchaseLines($newItems);
        $keys = array_unique(array_merge(array_keys($oldLines), array_keys($newLines)));
        $deltas = [];

        foreach ($keys as $key) {
            $oldQty = $oldLines[$key]['quantity'] ?? 0.0;
            $newQty = $newLines[$key]['quantity'] ?? 0.0;

            $deltas[] = [
                'key' => $key,
                'sample_item' => $oldLines[$key]['sample'] ?? null,
                'new_line' => $newLines[$key]['data'] ?? null,
                'old_quantity' => $oldQty,
                'new_quantity' => $newQty,
                'reverse_quantity' => max(0.0, $oldQty - $newQty),
                'add_quantity' => max(0.0, $newQty - $oldQty),
            ];
        }

        return $deltas;
    }

    /**
     * @param  array<int, array<string, mixed>>  $newItems
     * @throws \Exception
     */
    public function assertPurchaseUpdateAllowed(Purchase $purchase, array $newItems): void
    {
        if ($purchase->trashed()) {
            throw new \Exception('This purchase has already been deleted.');
        }

        if ($newItems === []) {
            throw new \Exception('No items provided in the purchase.');
        }

        $branchId = (int) $purchase->branch_id;

        foreach ($this->purchaseLineStockDeltas($purchase, $newItems) as $delta) {
            if ($delta['reverse_quantity'] <= 0.0001) {
                continue;
            }

            $sampleItem = $delta['sample_item'];
            if (! $sampleItem) {
                continue;
            }

            $evaluation = $this->evaluatePurchaseItemReversal(
                $sampleItem,
                $branchId,
                $delta['reverse_quantity']
            );

            if (! $evaluation['reversible']) {
                throw new \Exception($evaluation['message'] ?? 'This purchase line cannot be reversed.');
            }
        }
    }

    /**
     * @return array{
     *     reversible: bool,
     *     item_name: string,
     *     available: float,
     *     required: float,
     *     message: ?string
     * }
     */
    public function evaluatePurchaseItemReversal(
        PurchaseItem $item,
        int $branchId,
        ?float $quantityInPurchaseUnits = null,
        bool $allowPartial = false,
    ): array {
        $required = $quantityInPurchaseUnits ?? (float) $item->quantity;
        $name = 'Item';

        try {
            if ($item->item_type === 'ingredient') {
                $ingredient = Ingredient::withoutGlobalScopes()
                    ->with(['consumptionUnit', 'purchaseUnit'])
                    ->find($item->item_id);

                if (! $ingredient) {
                    return [
                        'reversible' => false,
                        'item_name' => 'Ingredient',
                        'available' => 0.0,
                        'required' => $required,
                        'message' => 'An ingredient on this purchase no longer exists.',
                    ];
                }

                $name = $ingredient->name;
                $stockQuantity = $this->purchaseQuantityToConsumption(
                    $ingredient,
                    $required,
                    $item->unit_id
                );
                $conversionRate = max((float) ($ingredient->conversion_rate ?: 1), 0.0001);
                $costPerConsumptionUnit = (float) $item->unit_price / $conversionRate;

                $branchStock = $this->findPurchaseIngredientStockBatch(
                    $branchId,
                    (int) $item->item_id,
                    $costPerConsumptionUnit
                );

                $available = $this->totalIngredientAvailableQuantity($branchId, (int) $item->item_id);

                if (! $branchStock && $available <= 0.0001) {
                    return [
                        'reversible' => true,
                        'item_name' => $name,
                        'available' => 0.0,
                        'required' => $required,
                        'message' => null,
                    ];
                }

                $tolerance = max(0.01, $stockQuantity * 0.002);

                if ($available + $tolerance < $stockQuantity) {
                    $availableInPurchaseUnits = round($this->consumptionQuantityToPurchase($ingredient, $available), 4);

                    if ($allowPartial) {
                        return [
                            'reversible' => true,
                            'item_name' => $name,
                            'available' => $availableInPurchaseUnits,
                            'required' => $required,
                            'message' => $available > 0.0001
                                ? "Only {$availableInPurchaseUnits} of {$required} can be reversed for \"{$name}\". The rest has been consumed."
                                : "\"{$name}\" stock has already been fully consumed and cannot be reversed.",
                        ];
                    }

                    return [
                        'reversible' => false,
                        'item_name' => $name,
                        'available' => $availableInPurchaseUnits,
                        'required' => $required,
                        'message' => "Cannot change this purchase: \"{$name}\" stock has already been consumed.",
                    ];
                }

                return [
                    'reversible' => true,
                    'item_name' => $name,
                    'available' => round($this->consumptionQuantityToPurchase($ingredient, $available), 4),
                    'required' => $required,
                    'message' => null,
                ];
            }

            if ($item->item_type === 'menu_item') {
                $menuItem = MenuItem::withoutGlobalScopes()->find($item->item_id);
                $name = $menuItem?->name ?? 'Menu item';

                if (! $menuItem) {
                    return [
                        'reversible' => false,
                        'item_name' => $name,
                        'available' => 0.0,
                        'required' => $required,
                        'message' => "Cannot change this purchase: \"{$name}\" no longer exists.",
                    ];
                }

                $stockQuantity = MenuItemQuantity::toSellQuantity($menuItem, $required);
                $costPerSellUnit = MenuItemQuantity::costPerSellUnit($menuItem, (float) $item->unit_price);

                $stock = $this->findPurchaseMenuItemStockBatch(
                    $branchId,
                    (int) $item->item_id,
                    $costPerSellUnit,
                    $item->expiry_date
                );

                if (! $stock) {
                    return [
                        'reversible' => true,
                        'item_name' => $name,
                        'available' => 0.0,
                        'required' => $required,
                        'message' => null,
                    ];
                }

                $available = (float) $stock->quantity;
                $rate = max((float) ($menuItem->conversion_rate ?: 1), 0.0001);

                if ($available + 0.0001 < $stockQuantity) {
                    return [
                        'reversible' => false,
                        'item_name' => $name,
                        'available' => round($available / $rate, 4),
                        'required' => $required,
                        'message' => "Cannot change this purchase: \"{$name}\" stock has already been consumed.",
                    ];
                }

                return [
                    'reversible' => true,
                    'item_name' => $name,
                    'available' => round($available / $rate, 4),
                    'required' => $required,
                    'message' => null,
                ];
            }

            return [
                'reversible' => true,
                'item_name' => $name,
                'available' => 0.0,
                'required' => $required,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'reversible' => false,
                'item_name' => $name,
                'available' => 0.0,
                'required' => $required,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Rebuild branch stock from purchase lines (e.g. after inventory reset while purchases remain).
     *
     * @param  list<int>  $branchIds
     */
    public function rebuildBranchInventoryFromPurchases(int $companyId, array $branchIds): void
    {
        if ($branchIds === []) {
            return;
        }

        $purchases = Purchase::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('branch_id', $branchIds)
            ->with('items')
            ->orderBy('id')
            ->get();

        foreach ($purchases as $purchase) {
            foreach ($purchase->items as $item) {
                if ($item->item_type === 'ingredient') {
                    $this->updateIngredientStock(
                        (int) $purchase->branch_id,
                        (int) $item->item_id,
                        (float) $item->quantity,
                        $item->unit_id,
                        (float) $item->unit_price,
                        $item->expiry_date,
                        (int) $purchase->company_id
                    );
                } elseif ($item->item_type === 'menu_item') {
                    $menuItem = MenuItem::withoutGlobalScopes()->find($item->item_id);
                    if (! $menuItem) {
                        continue;
                    }

                    $this->updateMenuItemStock(
                        (int) $purchase->branch_id,
                        (int) $item->item_id,
                        (float) $item->quantity,
                        (float) $item->unit_price,
                        $item->expiry_date,
                        $menuItem
                    );
                }
            }
        }
    }

    /**
     * Delete a purchase and reverse inventory and financial impact.
     *
     * @throws \Exception
     */
    public function deletePurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $this->assertPurchaseCanBeDeleted($purchase);

            $purchase->loadMissing('items');
            $ingredientIds = [];
            $menuItemIds = [];

            foreach ($purchase->items as $item) {
                $this->reversePurchaseItemStock(
                    $item,
                    (int) $purchase->branch_id,
                    (int) $purchase->company_id,
                    null,
                    allowPartial: true
                );
                if ($item->item_type === 'ingredient') {
                    $ingredientIds[] = (int) $item->item_id;
                } elseif ($item->item_type === 'menu_item') {
                    $menuItemIds[] = (int) $item->item_id;
                }
            }

            $this->reversePurchaseFinancials($purchase);

            $purchase->items()->delete();
            $purchase->archivePurchaseNumber();
            $purchase->delete();

            foreach (array_unique($ingredientIds) as $ingredientId) {
                $ingredient = Ingredient::withoutGlobalScopes()->find($ingredientId);
                if ($ingredient) {
                    $this->ingredientCosts->syncIngredient($ingredient);
                }
            }

            foreach (array_unique($menuItemIds) as $menuItemId) {
                $this->menuItemCosts->syncMenuItemById($menuItemId);
            }
        });
    }

    /**
     * Update an existing purchase (reverse old stock, apply new lines).
     *
     * @param  array<string, mixed>  $purchaseData
     * @param  array<int, array<string, mixed>>  $items
     * @throws \Exception
     */
    public function updatePurchase(Purchase $purchase, array $purchaseData, array $items, int $userId): Purchase
    {
        return DB::transaction(function () use ($purchase, $purchaseData, $items, $userId) {
            $purchase->loadMissing('items');

            if ((int) $purchaseData['branch_id'] !== (int) $purchase->branch_id) {
                throw new \Exception('Branch cannot be changed when editing a purchase. Delete it and create a new one instead.');
            }

            if (empty($items) || ! is_array($items)) {
                throw new \Exception('No items provided in the purchase.');
            }

            $this->assertPurchaseUpdateAllowed($purchase, $items);

            $oldTotal = round((float) $purchase->total_amount, 2);
            $oldSupplierId = $purchase->supplier_id ? (int) $purchase->supplier_id : null;
            $existingPaid = round((float) $purchase->paid_amount, 2);
            $hasExistingPayments = $this->purchaseHasExistingPayments($purchase);

            if (
                $hasExistingPayments
                && $oldSupplierId !== null
                && (int) ($purchaseData['supplier_id'] ?? 0) !== $oldSupplierId
            ) {
                throw new \Exception('Supplier cannot be changed while payments are linked to this purchase.');
            }

            if ($hasExistingPayments) {
                $purchaseData = $this->preparePurchaseDataPreservingPayments($purchase, $purchaseData, $existingPaid);
            }

            $branchId = (int) $purchase->branch_id;
            $companyId = (int) $purchase->company_id;
            $ingredientIds = [];
            $menuItemIds = [];

            foreach ($this->purchaseLineStockDeltas($purchase, $items) as $delta) {
                if ($delta['reverse_quantity'] > 0.0001 && $delta['sample_item']) {
                    $sampleItem = $delta['sample_item'];
                    $this->reversePurchaseItemStock(
                        $sampleItem,
                        $branchId,
                        $companyId,
                        $delta['reverse_quantity']
                    );

                    if ($sampleItem->item_type === 'ingredient') {
                        $ingredientIds[] = (int) $sampleItem->item_id;
                    } elseif ($sampleItem->item_type === 'menu_item') {
                        $menuItemIds[] = (int) $sampleItem->item_id;
                    }
                }

                if ($delta['add_quantity'] > 0.0001 && $delta['new_line']) {
                    $line = $delta['new_line'];
                    $addQuantity = $delta['add_quantity'];

                    if (
                        $delta['sample_item']
                        && $delta['reverse_quantity'] <= 0.0001
                        && $delta['old_quantity'] > 0.0001
                        && ! $this->purchaseLineStockBatchExists($delta['sample_item'], $branchId)
                    ) {
                        $addQuantity = $delta['new_quantity'];
                    }

                    if ($line['item_type'] === 'ingredient') {
                        $this->updateIngredientStock(
                            $branchId,
                            (int) $line['item_id'],
                            $addQuantity,
                            $line['unit_id'] ?? null,
                            (float) $line['unit_price'],
                            $line['expiry_date'] ?? null,
                            $companyId
                        );

                        $this->ingredientCosts->syncFromPurchase(
                            (int) $line['item_id'],
                            (float) $line['unit_price'],
                            $line['unit_id'] ?? null,
                            $companyId
                        );

                        $ingredientIds[] = (int) $line['item_id'];
                    } elseif ($line['item_type'] === 'menu_item') {
                        $menuItem = MenuItem::withoutGlobalScopes()->find($line['item_id']);
                        if (! $menuItem) {
                            throw new \Exception('Menu item not found.');
                        }
                        if ($menuItem->type === 'recipe') {
                            throw new \Exception("Menu item \"{$menuItem->name}\" is a recipe and cannot be purchased. Buy ingredients instead.");
                        }
                        if ($menuItem->type !== 'single' || ! $menuItem->track_inventory) {
                            throw new \Exception("Menu item \"{$menuItem->name}\" is not eligible for purchase stock.");
                        }

                        $this->updateMenuItemStock(
                            $branchId,
                            (int) $line['item_id'],
                            $addQuantity,
                            (float) $line['unit_price'],
                            $line['expiry_date'] ?? null,
                            $menuItem
                        );

                        $this->menuItemCosts->syncFromPurchase(
                            (int) $line['item_id'],
                            (float) $line['unit_price'],
                            $menuItem
                        );

                        $menuItemIds[] = (int) $line['item_id'];
                    }
                }
            }

            $purchase->items()->delete();

            $purchase->fill([
                'supplier_id' => $purchaseData['supplier_id'] ?? null,
                'purchase_date' => $purchaseData['purchase_date'],
                'subtotal' => $purchaseData['subtotal'],
                'tax_amount' => $purchaseData['tax_amount'] ?? 0,
                'discount_amount' => $purchaseData['discount_amount'] ?? 0,
                'total_amount' => $purchaseData['total_amount'],
                'paid_amount' => $purchaseData['paid_amount'] ?? 0,
                'payment_method' => $purchaseData['payment_method'],
                'money_source_id' => $purchaseData['money_source_id'] ?? null,
                'payment_status' => $purchaseData['payment_status'] ?? 'pending',
                'notes' => $purchaseData['notes'] ?? null,
            ]);
            $purchase->save();

            foreach ($items as $index => $itemData) {
                if (empty($itemData['item_type']) || empty($itemData['item_id'])) {
                    throw new \Exception("Item at index {$index} is missing required fields (item_type or item_id).");
                }

                $this->createPurchaseItem($purchase, $itemData, $branchId, applyStock: false);

                if ($itemData['item_type'] === 'ingredient') {
                    $ingredientIds[] = (int) $itemData['item_id'];
                } elseif ($itemData['item_type'] === 'menu_item') {
                    $menuItemIds[] = (int) $itemData['item_id'];
                }
            }

            $this->adjustPurchaseFinancialsOnUpdate(
                $purchase,
                $oldTotal,
                $oldSupplierId,
                $existingPaid,
                $hasExistingPayments,
                $userId
            );

            foreach (array_unique($ingredientIds) as $ingredientId) {
                $ingredient = Ingredient::withoutGlobalScopes()->find($ingredientId);
                if ($ingredient) {
                    $this->ingredientCosts->syncIngredient($ingredient);
                }
            }

            foreach (array_unique($menuItemIds) as $menuItemId) {
                $this->menuItemCosts->syncMenuItemById($menuItemId);
            }

            return $purchase->fresh(['items', 'supplier', 'branch']);
        });
    }

    /**
     * @throws \Exception
     */
    protected function assertPurchaseItemReversible(PurchaseItem $item, int $branchId, ?float $quantityInPurchaseUnits = null): void
    {
        $evaluation = $this->evaluatePurchaseItemReversal($item, $branchId, $quantityInPurchaseUnits);

        if (! $evaluation['reversible']) {
            throw new \Exception($evaluation['message'] ?? 'This purchase line cannot be reversed.');
        }
    }

    /**
     * @throws \Exception
     */
    public function reversePurchaseItemStock(
        PurchaseItem $item,
        int $branchId,
        int $companyId,
        ?float $quantityInPurchaseUnits = null,
        bool $allowPartial = false
    ): void {
        $reverseQty = $quantityInPurchaseUnits ?? (float) $item->quantity;

        if ($reverseQty <= 0.0001) {
            return;
        }

        if (! $allowPartial) {
            $this->assertPurchaseItemReversible($item, $branchId, $reverseQty);
        }

        if ($item->item_type === 'ingredient') {
            $ingredient = Ingredient::withoutGlobalScopes()
                ->with(['consumptionUnit', 'purchaseUnit'])
                ->findOrFail($item->item_id);

            $stockQuantity = $this->purchaseQuantityToConsumption(
                $ingredient,
                $reverseQty,
                $item->unit_id
            );
            $conversionRate = max((float) ($ingredient->conversion_rate ?: 1), 0.0001);
            $costPerConsumptionUnit = (float) $item->unit_price / $conversionRate;

            if ($allowPartial) {
                $available = $this->totalIngredientAvailableQuantity($branchId, (int) $item->item_id);
                $stockQuantity = min($stockQuantity, $available);
                if ($stockQuantity <= 0.0001) {
                    return;
                }
            }

            $this->decrementIngredientStockForPurchaseReversal(
                $branchId,
                (int) $item->item_id,
                $stockQuantity,
                $costPerConsumptionUnit
            );

            return;
        }

        if ($item->item_type === 'menu_item') {
            $menuItem = MenuItem::withoutGlobalScopes()->findOrFail($item->item_id);
            $stockQuantity = MenuItemQuantity::toSellQuantity($menuItem, $reverseQty);
            $costPerSellUnit = MenuItemQuantity::costPerSellUnit($menuItem, (float) $item->unit_price);

            $stock = $this->findPurchaseMenuItemStockBatch(
                $branchId,
                (int) $item->item_id,
                $costPerSellUnit,
                $item->expiry_date
            );

            if (! $stock) {
                return;
            }

            $stock->decrement('quantity', $stockQuantity);
            $stock->refresh();

            if ((float) $stock->quantity <= 0.0001) {
                $stock->delete();
            }
        }
    }

    /**
     * Re-add stock previously removed by a purchase return (or similar reversal).
     */
    public function restorePurchaseItemStock(
        PurchaseItem $item,
        int $branchId,
        int $companyId,
        float $quantityInPurchaseUnits
    ): void {
        if ($quantityInPurchaseUnits <= 0.0001) {
            return;
        }

        if ($item->item_type === 'ingredient') {
            $this->updateIngredientStock(
                $branchId,
                (int) $item->item_id,
                $quantityInPurchaseUnits,
                $item->unit_id,
                (float) $item->unit_price,
                $item->expiry_date,
                $companyId
            );

            return;
        }

        if ($item->item_type === 'menu_item') {
            $menuItem = MenuItem::withoutGlobalScopes()->findOrFail($item->item_id);

            $this->updateMenuItemStock(
                $branchId,
                (int) $item->item_id,
                $quantityInPurchaseUnits,
                (float) $item->unit_price,
                $item->expiry_date,
                $menuItem
            );
        }
    }

    protected function purchaseLineStockBatchExists(PurchaseItem $item, int $branchId): bool
    {
        if ($item->item_type === 'ingredient') {
            $ingredient = Ingredient::withoutGlobalScopes()
                ->with(['consumptionUnit', 'purchaseUnit'])
                ->find($item->item_id);

            if (! $ingredient) {
                return false;
            }

            $conversionRate = max((float) ($ingredient->conversion_rate ?: 1), 0.0001);
            $costPerConsumptionUnit = (float) $item->unit_price / $conversionRate;

            return $this->findPurchaseIngredientStockBatch(
                $branchId,
                (int) $item->item_id,
                $costPerConsumptionUnit
            ) !== null;
        }

        if ($item->item_type === 'menu_item') {
            $menuItem = MenuItem::withoutGlobalScopes()->find($item->item_id);

            if (! $menuItem) {
                return false;
            }

            $costPerSellUnit = MenuItemQuantity::costPerSellUnit($menuItem, (float) $item->unit_price);

            return $this->findPurchaseMenuItemStockBatch(
                $branchId,
                (int) $item->item_id,
                $costPerSellUnit,
                $item->expiry_date
            ) !== null;
        }

        return false;
    }

    protected function purchaseHasExistingPayments(Purchase $purchase): bool
    {
        $purchase->loadMissing('supplierPayments');

        return $purchase->supplierPayments->isNotEmpty()
            || round((float) $purchase->paid_amount, 2) > 0.01;
    }

    /**
     * @param  array<string, mixed>  $purchaseData
     * @return array<string, mixed>
     */
    protected function preparePurchaseDataPreservingPayments(
        Purchase $purchase,
        array $purchaseData,
        float $existingPaid
    ): array {
        $newTotal = round((float) $purchaseData['total_amount'], 2);
        $effectivePaid = round(min($existingPaid, $newTotal), 2);

        $purchaseData['paid_amount'] = $effectivePaid;
        $purchaseData['payment_status'] = $this->resolvePurchasePaymentStatus($newTotal, $effectivePaid);

        if (($purchaseData['payment_method'] ?? 'credit') === 'credit' && $effectivePaid > 0) {
            $purchaseData['payment_method'] = $purchase->payment_method;
            $purchaseData['money_source_id'] = $purchase->money_source_id;
        }

        return $purchaseData;
    }

    protected function resolvePurchasePaymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0.01) {
            return 'pending';
        }

        if ($paid >= $total - 0.01) {
            return 'paid';
        }

        return 'partial';
    }

    protected function adjustPurchaseFinancialsOnUpdate(
        Purchase $purchase,
        float $oldTotal,
        ?int $oldSupplierId,
        float $existingPaid,
        bool $hasExistingPayments,
        int $userId
    ): void {
        $newTotal = round((float) $purchase->total_amount, 2);
        $newSupplierId = $purchase->supplier_id ? (int) $purchase->supplier_id : null;
        $delta = round($newTotal - $oldTotal, 2);

        if ($oldSupplierId && $newSupplierId && $oldSupplierId !== $newSupplierId) {
            $this->transferSupplierPurchaseObligation($purchase, $oldTotal, $existingPaid, $oldSupplierId, $newSupplierId, $userId);

            return;
        }

        if ($newSupplierId && abs($delta) >= 0.01) {
            $supplier = Supplier::withoutTenantScope()->find($newSupplierId);
            if ($supplier) {
                $this->recordSupplierBalanceDeltaForPurchase(
                    $supplier,
                    $delta,
                    $purchase,
                    $oldTotal,
                    $newTotal,
                    $userId
                );
            }
        }

        if ($hasExistingPayments) {
            return;
        }

        if ($purchase->paid_amount > 0 && ! $purchase->supplierPayments()->exists()) {
            if ($purchase->supplier_id) {
                $this->createSupplierPaymentAtPurchase($purchase, $userId);
            } else {
                $this->createPurchaseTransaction($purchase);
            }
        }
    }

    protected function transferSupplierPurchaseObligation(
        Purchase $purchase,
        float $oldTotal,
        float $existingPaid,
        int $oldSupplierId,
        int $newSupplierId,
        int $userId
    ): void {
        $oldUnpaid = round(max(0, $oldTotal - $existingPaid), 2);
        $newTotal = round((float) $purchase->total_amount, 2);
        $newPaid = round(min($existingPaid, $newTotal), 2);
        $newUnpaid = round(max(0, $newTotal - $newPaid), 2);

        if ($oldUnpaid > 0.01) {
            $oldSupplier = Supplier::withoutTenantScope()->find($oldSupplierId);
            if ($oldSupplier) {
                $this->recordSupplierBalanceDeltaForPurchase(
                    $oldSupplier,
                    -$oldUnpaid,
                    $purchase,
                    $oldTotal,
                    0,
                    $userId,
                    "Purchase #{$purchase->purchase_number} moved to another supplier"
                );
            }
        }

        if ($newUnpaid > 0.01) {
            $newSupplier = Supplier::withoutTenantScope()->find($newSupplierId);
            if ($newSupplier) {
                $this->recordSupplierBalanceDeltaForPurchase(
                    $newSupplier,
                    $newUnpaid,
                    $purchase,
                    0,
                    $newTotal,
                    $userId,
                    "Purchase #{$purchase->purchase_number} moved from another supplier"
                );
            }
        }
    }

    protected function recordSupplierBalanceDeltaForPurchase(
        Supplier $supplier,
        float $delta,
        Purchase $purchase,
        float $oldTotal,
        float $newTotal,
        int $userId,
        ?string $reason = null
    ): void {
        if (abs($delta) < 0.01) {
            return;
        }

        $previousBalance = round((float) $supplier->balance, 2);
        $newBalance = round($previousBalance + $delta, 2);

        $reason ??= sprintf(
            'Purchase #%s total changed from %s to %s',
            $purchase->purchase_number,
            format_currency($oldTotal),
            format_currency($newTotal)
        );

        PartyBalanceAdjustment::create([
            'company_id' => $supplier->company_id,
            'party_type' => PartyBalanceAdjustment::PARTY_SUPPLIER,
            'party_id' => $supplier->id,
            'previous_balance' => $previousBalance,
            'new_balance' => $newBalance,
            'reason' => $reason,
            'created_by' => $userId,
        ]);

        $supplier->balance = $newBalance;
        $supplier->save();
    }

    protected function reversePurchaseFinancials(Purchase $purchase): void
    {
        if ($purchase->supplier_id) {
            $supplier = Supplier::withoutTenantScope()->find($purchase->supplier_id);
            if ($supplier) {
                $supplier->balance = round(
                    (float) $supplier->balance
                    - (float) $purchase->total_amount
                    + (float) $purchase->paid_amount,
                    2
                );
                $supplier->save();
            }
        }

        $this->unlinkSupplierPaymentsForPurchase($purchase);

        Transaction::withTrashed()
            ->where('reference_type', 'purchase')
            ->where('ref_id', $purchase->id)
            ->forceDelete();
    }

    protected function unlinkSupplierPaymentsForPurchase(Purchase $purchase): void
    {
        $purchase->loadMissing('supplierPayments');
        $paymentIds = $purchase->supplierPayments->pluck('id')->all();

        foreach ($paymentIds as $paymentId) {
            $purchase->supplierPayments()->detach($paymentId);

            $remainingLinks = DB::table('supplier_payment_purchase')
                ->where('supplier_payment_id', $paymentId)
                ->count();

            if ($remainingLinks === 0) {
                Transaction::withTrashed()
                    ->where('reference_type', 'purchase')
                    ->where('ref_id', $paymentId)
                    ->forceDelete();

                SupplierPayment::withoutGlobalScopes()->whereKey($paymentId)->forceDelete();
            }
        }
    }

    /**
     * Create a purchase item and update inventory.
     */
    protected function createPurchaseItem(
        Purchase $purchase,
        array $itemData,
        int $branchId,
        bool $applyStock = true
    ): PurchaseItem {
        $totalPrice = ($itemData['quantity'] ?? 0) * ($itemData['unit_price'] ?? 0);

        // Create purchase item
        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'item_type' => $itemData['item_type'],
            'item_id' => $itemData['item_id'],
            'quantity' => $itemData['quantity'],
            'unit_id' => $itemData['unit_id'] ?? null,
            'unit_price' => $itemData['unit_price'],
            'total_price' => $totalPrice,
            'expiry_date' => $itemData['expiry_date'] ?? null,
            'notes' => $itemData['notes'] ?? null,
        ]);

        if (! $applyStock) {
            return $purchaseItem;
        }

        // Update inventory based on item type
        if ($itemData['item_type'] === 'ingredient') {
            $this->updateIngredientStock(
                $branchId,
                $itemData['item_id'],
                $itemData['quantity'],
                $itemData['unit_id'] ?? null,
                $itemData['unit_price'],
                $itemData['expiry_date'] ?? null,
                $purchase->company_id
            );

            $this->ingredientCosts->syncFromPurchase(
                (int) $itemData['item_id'],
                (float) $itemData['unit_price'],
                $itemData['unit_id'] ?? null,
                (int) $purchase->company_id
            );
        } elseif ($itemData['item_type'] === 'menu_item') {
            $menuItem = MenuItem::withoutGlobalScopes()->find($itemData['item_id']);
            if (! $menuItem) {
                throw new \Exception('Menu item not found.');
            }
            if ($menuItem->type === 'recipe') {
                throw new \Exception("Menu item \"{$menuItem->name}\" is a recipe and cannot be purchased. Buy ingredients instead.");
            }
            if ($menuItem->type !== 'single' || ! $menuItem->track_inventory) {
                throw new \Exception("Menu item \"{$menuItem->name}\" is not eligible for purchase stock.");
            }

            $this->updateMenuItemStock(
                $branchId,
                (int) $itemData['item_id'],
                (float) $itemData['quantity'],
                (float) $itemData['unit_price'],
                $itemData['expiry_date'] ?? null,
                $menuItem
            );

            $this->menuItemCosts->syncFromPurchase(
                (int) $itemData['item_id'],
                (float) $itemData['unit_price'],
                $menuItem
            );
        }

        return $purchaseItem;
    }

    /**
     * Update ingredient stock - store separate records per price, update quantity if same price.
     */
    protected function updateIngredientStock(
        int $branchId,
        int $ingredientId,
        float $quantity,
        $unitId,
        float $unitPrice,
        $expiryDate = null,
        int $companyId
    ): void {
        $ingredient = Ingredient::withoutGlobalScopes()
            ->with(['consumptionUnit', 'purchaseUnit'])
            ->find($ingredientId);
        if (!$ingredient) {
            throw new \Exception("Ingredient with ID {$ingredientId} not found.");
        }

        $conversionRate = max((float) ($ingredient->conversion_rate ?: 1), 0.0001);
        $stockQuantity = $this->purchaseQuantityToConsumption(
            $ingredient,
            $quantity,
            $unitId
        );
        $costPerConsumptionUnit = $unitPrice / $conversionRate;

        $consumptionUnitKey = (string) ($ingredient->consumption_unit_id ?? $ingredient->base_unit_id);
        $finalUnitId = $this->unitOfMeasureResolver->resolveId($consumptionUnitKey, $companyId);
        
        if (!$finalUnitId) {
            throw new \Exception("Unit not found for ingredient: {$ingredient->name}");
        }

        // Check if stock exists with same price - if yes, update quantity; if no, create new record
        $branchStock = $this->findPurchaseIngredientStockBatch($branchId, $ingredientId, $costPerConsumptionUnit);

        if ($branchStock) {
            // Update quantity for same price batch
            $branchStock->increment('quantity', $stockQuantity);
            $branchStock->update(['last_restocked_at' => now()]);
        } else {
            // Create new stock record for this price
            BranchStock::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'ingredient_id' => $ingredientId,
                'quantity' => $stockQuantity,
                'reserved_quantity' => 0,
                'unit_id' => $finalUnitId,
                'average_cost' => $costPerConsumptionUnit,
                'last_restocked_at' => now(),
            ]);
        }
    }

    /**
     * Update menu item stock - check for same price and expiry, update or insert.
     */
    protected function updateMenuItemStock(
        int $branchId,
        int $menuItemId,
        float $quantity,
        float $unitPrice,
        $expiryDate = null,
        ?MenuItem $menuItem = null
    ): void {
        $menuItem ??= MenuItem::withoutGlobalScopes()
            ->with(['purchaseUnit', 'consumptionUnit'])
            ->find($menuItemId);

        if (! $menuItem) {
            throw new \Exception("Menu item with ID {$menuItemId} not found.");
        }

        $stockQuantity = MenuItemQuantity::toSellQuantity($menuItem, $quantity);
        $costPerSellUnit = MenuItemQuantity::costPerSellUnit($menuItem, $unitPrice);

        // Check if stock exists with same price and expiry date
        $existingStock = $this->findPurchaseMenuItemStockBatch(
            $branchId,
            $menuItemId,
            $costPerSellUnit,
            $expiryDate
        );

        if ($existingStock) {
            // Update quantity for same batch
            $existingStock->increment('quantity', $stockQuantity);
            $existingStock->update(['last_restocked_at' => now()]);
        } else {
            // Create new stock record
            MenuItemStock::create([
                'branch_id' => $branchId,
                'menu_item_id' => $menuItemId,
                'quantity' => $stockQuantity,
                'unit_price' => $costPerSellUnit,
                'expiry_date' => $expiryDate,
                'last_restocked_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createSupplierPaymentWithUniqueNumber(array $attributes, int $branchId): SupplierPayment
    {
        $lastDuplicate = null;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $attributes['payment_number'] = SupplierPayment::allocatePaymentNumber($branchId);

            try {
                return SupplierPayment::create($attributes);
            } catch (QueryException $e) {
                if (! SupplierPayment::isDuplicateKeyException($e)) {
                    throw $e;
                }
                $lastDuplicate = $e;
            }
        }

        throw $lastDuplicate ?? new \RuntimeException('Unable to allocate a unique supplier payment number.');
    }

    /**
     * Record initial supplier payment when amount is paid during purchase creation.
     */
    protected function createSupplierPaymentAtPurchase(Purchase $purchase, int $userId): void
    {
        $purchaseAccount = Account::where('company_id', $purchase->company_id)
            ->where('name', 'Purchase')
            ->where('type', 'expense')
            ->where('is_active', true)
            ->first();

        if (! $purchaseAccount) {
            Log::warning('Purchase account not found for supplier payment at purchase', [
                'purchase_id' => $purchase->id,
                'company_id' => $purchase->company_id,
            ]);
            $this->createPurchaseTransaction($purchase);

            return;
        }

        $paidAmount = round((float) $purchase->paid_amount, 2);
        if ($paidAmount <= 0) {
            return;
        }

        $payment = $this->createSupplierPaymentWithUniqueNumber([
            'company_id' => $purchase->company_id,
            'branch_id' => $purchase->branch_id,
            'supplier_id' => $purchase->supplier_id,
            'account_id' => $purchaseAccount->id,
            'money_source_id' => $purchase->money_source_id,
            'created_by' => $userId,
            'payment_date' => $purchase->purchase_date,
            'total_amount' => $paidAmount,
            'payment_method' => $purchase->payment_method === 'credit' ? 'cash' : $purchase->payment_method,
            'notes' => "Payment at purchase #{$purchase->purchase_number}",
        ], (int) $purchase->branch_id);

        $payment->purchases()->attach($purchase->id, ['amount' => $paidAmount]);

        Transaction::create([
            'company_id' => $purchase->company_id,
            'branch_id' => $purchase->branch_id,
            'account_id' => $purchaseAccount->id,
            'amount' => $paidAmount,
            'type' => 'out',
            'payment_method' => $purchase->payment_method === 'credit' ? 'cash' : $purchase->payment_method,
            'money_source_id' => $purchase->money_source_id,
            'reference_type' => 'purchase',
            'date' => $purchase->purchase_date,
            'ref_id' => $payment->id,
            'created_by' => $userId,
            'shift_id' => $purchase->shift_id,
            'notes' => "Purchase #{$purchase->purchase_number} — payment at purchase",
        ]);
    }

    /**
     * Create payment transaction for purchase.
     */
    protected function createPurchaseTransaction(Purchase $purchase): void
    {
        // Get Purchase account
        $purchaseAccount = Account::where('company_id', $purchase->company_id)
            ->where('name', 'Purchase')
            ->where('type', 'expense')
            ->where('is_active', true)
            ->first();

        if (!$purchaseAccount) {
            Log::warning('Purchase account not found for transaction', [
                'purchase_id' => $purchase->id,
                'company_id' => $purchase->company_id,
            ]);
            return;
        }

        Transaction::create([
            'company_id' => $purchase->company_id,
            'branch_id' => $purchase->branch_id,
            'account_id' => $purchaseAccount->id,
            'amount' => $purchase->paid_amount,
            'type' => 'out',
            'payment_method' => $purchase->payment_method,
            'money_source_id' => $purchase->money_source_id,
            'reference_type' => 'purchase',
            'date' => $purchase->purchase_date,
            'ref_id' => $purchase->id,
            'created_by' => Auth::id(),
            'shift_id' => $purchase->shift_id,
            'notes' => "Purchase #{$purchase->purchase_number}",
        ]);
    }

    /**
     * Update supplier balance when purchase is created.
     * Balance increases by purchase amount (represents amount owed to supplier).
     */
    protected function updateSupplierBalance(Purchase $purchase): void
    {
        if (!$purchase->supplier_id) {
            return;
        }

        $supplier = Supplier::withoutTenantScope()->find($purchase->supplier_id);
        if (!$supplier) {
            Log::warning('Supplier not found for balance update', [
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
            ]);
            return;
        }

        // Calculate the amount to add to balance
        // If payment_status is 'paid', no balance is added (already paid)
        // If payment_status is 'partial', add the unpaid amount
        // If payment_status is 'pending', add the full amount
        $amountToAdd = 0;
        
        if ($purchase->payment_status === 'pending') {
            $amountToAdd = $purchase->total_amount;
        } elseif ($purchase->payment_status === 'partial') {
            $paidAmount = $purchase->paid_amount ?? 0;
            $amountToAdd = $purchase->total_amount - $paidAmount;
        }
        // If 'paid', amountToAdd remains 0

        if ($amountToAdd > 0) {
            $currentBalance = $supplier->balance ?? 0;
            $supplier->balance = round((float) $currentBalance + $amountToAdd, 2);
            $supplier->save();

            Log::info('Supplier balance updated', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'amount_added' => $amountToAdd,
                'new_balance' => $supplier->balance,
                'purchase_id' => $purchase->id,
            ]);
        }
    }

    protected function purchaseQuantityToConsumption(Ingredient $ingredient, float $quantity, $unitId): float
    {
        $unitKey = $unitId !== null && $unitId !== '' ? (string) $unitId : null;
        $consumptionQty = IngredientQuantity::toConsumptionQuantity($ingredient, $quantity, $unitKey);

        if ($consumptionQty !== null) {
            return $consumptionQty;
        }

        $conversionRate = max((float) ($ingredient->conversion_rate ?: 1), 0.0001);

        return $quantity * $conversionRate;
    }

    protected function consumptionQuantityToPurchase(Ingredient $ingredient, float $consumptionQuantity): float
    {
        $conversionRate = max((float) ($ingredient->conversion_rate ?: 1), 0.0001);

        return $consumptionQuantity / $conversionRate;
    }

    protected function findPurchaseIngredientStockBatch(
        int $branchId,
        int $ingredientId,
        float $costPerConsumptionUnit
    ): ?BranchStock {
        return BranchStock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->whereRaw('ABS(average_cost - ?) < 0.01', [$costPerConsumptionUnit])
            ->first();
    }

    protected function totalIngredientAvailableQuantity(int $branchId, int $ingredientId): float
    {
        return (float) BranchStock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->get()
            ->sum(fn (BranchStock $batch) => max(
                0,
                (float) $batch->quantity - (float) $batch->reserved_quantity
            ));
    }

    protected function decrementIngredientStockForPurchaseReversal(
        int $branchId,
        int $ingredientId,
        float $stockQuantity,
        float $costPerConsumptionUnit
    ): void {
        $remaining = $stockQuantity;
        $purchaseBatch = $this->findPurchaseIngredientStockBatch($branchId, $ingredientId, $costPerConsumptionUnit);
        $purchaseBatchId = $purchaseBatch?->id;

        if ($purchaseBatch) {
            $take = min((float) $purchaseBatch->quantity, $remaining);
            if ($take > 0.0001) {
                $purchaseBatch->decrement('quantity', $take);
                $remaining = round($remaining - $take, 4);
                $purchaseBatch->refresh();
                if ((float) $purchaseBatch->quantity <= 0.0001) {
                    $purchaseBatch->delete();
                }
            }
        }

        if ($remaining <= 0.0001) {
            return;
        }

        $batches = BranchStock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('ingredient_id', $ingredientId)
            ->orderByRaw('CASE WHEN last_restocked_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('last_restocked_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }

            if ($purchaseBatchId && (int) $batch->id === (int) $purchaseBatchId) {
                continue;
            }

            $take = min((float) $batch->quantity, $remaining);
            if ($take <= 0.0001) {
                continue;
            }

            $batch->decrement('quantity', $take);
            $remaining = round($remaining - $take, 4);
            $batch->refresh();

            if ((float) $batch->quantity <= 0.0001) {
                $batch->delete();
            }
        }
    }

    protected function findPurchaseMenuItemStockBatch(
        int $branchId,
        int $menuItemId,
        float $costPerSellUnit,
        mixed $expiryDate
    ): ?MenuItemStock {
        return MenuItemStock::where('branch_id', $branchId)
            ->where('menu_item_id', $menuItemId)
            ->whereRaw('ABS(unit_price - ?) < 0.01', [$costPerSellUnit])
            ->where(function ($query) use ($expiryDate) {
                if ($expiryDate) {
                    $query->whereDate('expiry_date', $expiryDate);
                } else {
                    $query->whereNull('expiry_date');
                }
            })
            ->first();
    }

    /**
     * @return array<string, array{quantity: float, sample: PurchaseItem}>
     */
    protected function aggregateExistingPurchaseLines(iterable $items): array
    {
        $aggregated = [];

        foreach ($items as $item) {
            $key = $this->purchaseLineKeyFromItem($item);

            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'quantity' => 0.0,
                    'sample' => $item,
                ];
            }

            $aggregated[$key]['quantity'] += (float) $item->quantity;
        }

        return $aggregated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array{quantity: float, data: array<string, mixed>}>
     */
    protected function aggregateRequestedPurchaseLines(array $items): array
    {
        $aggregated = [];

        foreach ($items as $itemData) {
            $key = $this->purchaseLineKeyFromRequest($itemData);

            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'quantity' => 0.0,
                    'data' => $itemData,
                ];
            }

            $aggregated[$key]['quantity'] += (float) ($itemData['quantity'] ?? 0);
        }

        return $aggregated;
    }

    protected function purchaseLineKeyFromItem(PurchaseItem $item): string
    {
        $expiry = $item->expiry_date?->format('Y-m-d');

        return $this->purchaseLineKey(
            (string) $item->item_type,
            (int) $item->item_id,
            (float) $item->unit_price,
            $expiry
        );
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    protected function purchaseLineKeyFromRequest(array $itemData): string
    {
        $expiry = $itemData['expiry_date'] ?? null;

        if ($expiry instanceof \DateTimeInterface) {
            $expiry = $expiry->format('Y-m-d');
        } elseif (is_string($expiry) && $expiry !== '') {
            $expiry = substr($expiry, 0, 10);
        } else {
            $expiry = null;
        }

        return $this->purchaseLineKey(
            (string) $itemData['item_type'],
            (int) $itemData['item_id'],
            (float) ($itemData['unit_price'] ?? 0),
            $expiry
        );
    }

    protected function purchaseLineKey(string $itemType, int $itemId, float $unitPrice, ?string $expiryDate): string
    {
        $price = number_format(round($unitPrice, 4), 4, '.', '');
        $expiry = $expiryDate ?? '';

        return "{$itemType}|{$itemId}|{$price}|{$expiry}";
    }
}

