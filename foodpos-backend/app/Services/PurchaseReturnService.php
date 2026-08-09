<?php

namespace App\Services;

use App\Models\PartyBalanceAdjustment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Support\CurrentShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    public function __construct(
        protected PurchaseService $purchaseService,
    ) {}

    /**
     * @param  list<array{purchase_item_id: int, quantity: float|int|string, notes?: ?string}>  $lines
     */
    public function createReturn(
        Purchase $purchase,
        array $lines,
        int $userId,
        ?string $notes = null,
        ?string $returnDate = null,
    ): PurchaseReturn {
        return DB::transaction(function () use ($purchase, $lines, $userId, $notes, $returnDate) {
            $purchase = Purchase::withoutGlobalScopes()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            $normalized = $this->normalizeLines($purchase, $lines);
            if ($normalized === []) {
                throw ValidationException::withMessages([
                    'items' => 'Select at least one item quantity to return.',
                ]);
            }

            $this->assertStockAvailableForLines($purchase, $normalized);

            $branchId = (int) $purchase->branch_id;
            $companyId = (int) $purchase->company_id;
            $subtotal = round(collect($normalized)->sum('total_price'), 2);
            $settlement = $this->settlementForPurchase($purchase, $subtotal);

            $return = PurchaseReturn::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'created_by' => $userId,
                'shift_id' => CurrentShift::id($branchId),
                'return_number' => PurchaseReturn::generateReturnNumber($branchId),
                'return_date' => $returnDate ?: local_today($branchId),
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'settlement_type' => $settlement['settlement_type'],
                'payable_reduction_amount' => $settlement['payable_reduction'],
                'credit_amount' => $settlement['credit_amount'],
                'notes' => $notes,
            ]);

            $this->applyReturnLines($return, $purchase, $normalized);
            $this->applyReturnedAmountDelta($purchase, $subtotal);
            $this->applySupplierBalanceDelta(
                $purchase,
                -$subtotal,
                $userId,
                "Purchase return #{$return->return_number} against purchase #{$purchase->purchase_number}"
            );

            return $return->fresh(['items.purchaseItem', 'purchase', 'supplier', 'creator']);
        });
    }

    /**
     * @param  list<array{purchase_item_id: int, quantity: float|int|string, notes?: ?string}>  $lines
     */
    public function updateReturn(
        PurchaseReturn $purchaseReturn,
        array $lines,
        int $userId,
        ?string $notes = null,
        ?string $returnDate = null,
    ): PurchaseReturn {
        return DB::transaction(function () use ($purchaseReturn, $lines, $userId, $notes, $returnDate) {
            $purchaseReturn = PurchaseReturn::withoutGlobalScopes()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->id);

            $purchase = Purchase::withoutGlobalScopes()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->purchase_id);

            $previousTotal = round((float) $purchaseReturn->total_amount, 2);

            $this->undoReturnEffects($purchaseReturn, $purchase, restoreStock: true);
            $purchaseReturn->items()->delete();
            $purchase->load('items');

            $normalized = $this->normalizeLines($purchase, $lines);
            if ($normalized === []) {
                throw ValidationException::withMessages([
                    'items' => 'Select at least one item quantity to return.',
                ]);
            }

            $this->assertStockAvailableForLines($purchase, $normalized);

            $subtotal = round(collect($normalized)->sum('total_price'), 2);
            $settlement = $this->settlementForPurchase($purchase, $subtotal);

            $purchaseReturn->fill([
                'return_date' => $returnDate ?: $purchaseReturn->return_date,
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'settlement_type' => $settlement['settlement_type'],
                'payable_reduction_amount' => $settlement['payable_reduction'],
                'credit_amount' => $settlement['credit_amount'],
                'notes' => $notes,
            ]);
            $purchaseReturn->save();

            $this->applyReturnLines($purchaseReturn, $purchase, $normalized);
            $this->applyReturnedAmountDelta($purchase, $subtotal);
            $this->applySupplierBalanceDelta(
                $purchase,
                $previousTotal - $subtotal,
                $userId,
                "Purchase return #{$purchaseReturn->return_number} updated against purchase #{$purchase->purchase_number}"
            );

            return $purchaseReturn->fresh(['items.purchaseItem', 'purchase', 'supplier', 'creator']);
        });
    }

    public function deleteReturn(PurchaseReturn $purchaseReturn, int $userId): void
    {
        DB::transaction(function () use ($purchaseReturn, $userId) {
            $purchaseReturn = PurchaseReturn::withoutGlobalScopes()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->id);

            $purchase = Purchase::withoutGlobalScopes()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->purchase_id);

            $previousTotal = round((float) $purchaseReturn->total_amount, 2);

            $this->undoReturnEffects($purchaseReturn, $purchase, restoreStock: true);
            $this->applySupplierBalanceDelta(
                $purchase,
                $previousTotal,
                $userId,
                "Purchase return #{$purchaseReturn->return_number} deleted against purchase #{$purchase->purchase_number}"
            );

            $purchaseReturn->items()->delete();
            $purchaseReturn->delete();
        });
    }

    /**
     * @param  list<array{purchase_item: PurchaseItem, quantity: float, unit_price: float, total_price: float, notes: ?string}>  $normalized
     */
    protected function assertStockAvailableForLines(Purchase $purchase, array $normalized): void
    {
        $branchId = (int) $purchase->branch_id;

        foreach ($normalized as $line) {
            $evaluation = $this->purchaseService->evaluatePurchaseItemReversal(
                $line['purchase_item'],
                $branchId,
                $line['quantity'],
                allowPartial: true,
            );

            if (! $evaluation['reversible']) {
                throw ValidationException::withMessages([
                    'items' => $evaluation['message'] ?? 'Cannot reverse stock for one or more return lines.',
                ]);
            }

            $available = (float) ($evaluation['available'] ?? 0);
            if ($available + 0.0001 < $line['quantity']) {
                $name = $evaluation['item_name'] ?? 'Item';
                throw ValidationException::withMessages([
                    'items' => $evaluation['message']
                        ?? "Only {$available} can be returned for \"{$name}\" (stock already consumed).",
                ]);
            }
        }
    }

    /**
     * @param  list<array{purchase_item: PurchaseItem, quantity: float, unit_price: float, total_price: float, notes: ?string}>  $normalized
     */
    protected function applyReturnLines(PurchaseReturn $return, Purchase $purchase, array $normalized): void
    {
        $branchId = (int) $purchase->branch_id;
        $companyId = (int) $purchase->company_id;

        foreach ($normalized as $line) {
            /** @var PurchaseItem $purchaseItem */
            $purchaseItem = $line['purchase_item'];

            PurchaseReturnItem::create([
                'purchase_return_id' => $return->id,
                'purchase_item_id' => $purchaseItem->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'total_price' => $line['total_price'],
                'stock_reversed_qty' => $line['quantity'],
                'notes' => $line['notes'],
            ]);

            $this->purchaseService->reversePurchaseItemStock(
                $purchaseItem,
                $branchId,
                $companyId,
                $line['quantity'],
                allowPartial: false,
            );

            $purchaseItem->quantity_returned = round(
                (float) ($purchaseItem->quantity_returned ?? 0) + $line['quantity'],
                4
            );
            $purchaseItem->save();
        }
    }

    protected function undoReturnEffects(
        PurchaseReturn $purchaseReturn,
        Purchase $purchase,
        bool $restoreStock
    ): void {
        $branchId = (int) $purchase->branch_id;
        $companyId = (int) $purchase->company_id;
        $itemsById = $purchase->items->keyBy('id');

        foreach ($purchaseReturn->items as $returnItem) {
            /** @var PurchaseItem|null $purchaseItem */
            $purchaseItem = $itemsById->get($returnItem->purchase_item_id)
                ?? PurchaseItem::query()->find($returnItem->purchase_item_id);

            if (! $purchaseItem) {
                continue;
            }

            $qty = (float) ($returnItem->stock_reversed_qty ?? $returnItem->quantity);

            if ($restoreStock && $qty > 0.0001) {
                $this->purchaseService->restorePurchaseItemStock(
                    $purchaseItem,
                    $branchId,
                    $companyId,
                    $qty
                );
            }

            $purchaseItem->quantity_returned = max(
                0,
                round((float) ($purchaseItem->quantity_returned ?? 0) - (float) $returnItem->quantity, 4)
            );
            $purchaseItem->save();
        }

        $this->applyReturnedAmountDelta(
            $purchase,
            -round((float) $purchaseReturn->total_amount, 2)
        );
    }

    protected function applyReturnedAmountDelta(Purchase $purchase, float $delta): void
    {
        $purchase->returned_amount = max(
            0,
            round((float) ($purchase->returned_amount ?? 0) + $delta, 2)
        );
        $purchase->payment_status = $this->paymentStatusAfterReturn($purchase);
        $purchase->save();
    }

    /**
     * @return array{payable_reduction: float, credit_amount: float, settlement_type: string}
     */
    protected function settlementForPurchase(Purchase $purchase, float $subtotal): array
    {
        $unpaidBefore = max(
            0,
            round((float) $purchase->total_amount - (float) ($purchase->returned_amount ?? 0) - (float) $purchase->paid_amount, 2)
        );
        $payableReduction = min($subtotal, $unpaidBefore);
        $creditAmount = round(max(0, $subtotal - $payableReduction), 2);
        $settlementType = $creditAmount > 0.009 && $payableReduction > 0.009
            ? 'mixed'
            : ($creditAmount > 0.009 ? 'supplier_credit' : 'reduce_payable');

        return [
            'payable_reduction' => $payableReduction,
            'credit_amount' => $creditAmount,
            'settlement_type' => $settlementType,
        ];
    }

    /**
     * @param  list<array{purchase_item_id: int, quantity: float|int|string, notes?: ?string}>  $lines
     * @return list<array{purchase_item: PurchaseItem, quantity: float, unit_price: float, total_price: float, notes: ?string}>
     */
    protected function normalizeLines(Purchase $purchase, array $lines): array
    {
        $itemsById = $purchase->items->keyBy('id');
        $normalized = [];

        foreach ($lines as $index => $line) {
            $purchaseItemId = (int) ($line['purchase_item_id'] ?? 0);
            $quantity = round((float) ($line['quantity'] ?? 0), 4);

            if ($purchaseItemId <= 0 || $quantity <= 0.0001) {
                continue;
            }

            /** @var PurchaseItem|null $purchaseItem */
            $purchaseItem = $itemsById->get($purchaseItemId);
            if (! $purchaseItem) {
                throw ValidationException::withMessages([
                    "items.{$index}.purchase_item_id" => 'Invalid purchase item selected.',
                ]);
            }

            $returnable = $purchaseItem->returnableQuantity();
            if ($quantity > $returnable + 0.0001) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => "Return quantity cannot exceed remaining {$returnable}.",
                ]);
            }

            $unitPrice = round((float) $purchaseItem->unit_price, 2);
            $normalized[] = [
                'purchase_item' => $purchaseItem,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => round($quantity * $unitPrice, 2),
                'notes' => isset($line['notes']) ? (string) $line['notes'] : null,
            ];
        }

        return $normalized;
    }

    protected function paymentStatusAfterReturn(Purchase $purchase): string
    {
        $pending = max(
            0,
            round((float) $purchase->total_amount - (float) ($purchase->returned_amount ?? 0) - (float) $purchase->paid_amount, 2)
        );

        if ($pending <= 0.009) {
            return 'paid';
        }

        if ((float) $purchase->paid_amount > 0.009) {
            return 'partial';
        }

        return 'pending';
    }

    /**
     * Positive delta increases supplier balance; negative decreases it.
     */
    protected function applySupplierBalanceDelta(
        Purchase $purchase,
        float $delta,
        int $userId,
        string $reason
    ): void {
        if (! $purchase->supplier_id || abs($delta) <= 0.009) {
            return;
        }

        $supplier = Supplier::withoutTenantScope()->find($purchase->supplier_id);
        if (! $supplier) {
            return;
        }

        $previousBalance = round((float) $supplier->balance, 2);
        $newBalance = round($previousBalance + $delta, 2);

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
}
