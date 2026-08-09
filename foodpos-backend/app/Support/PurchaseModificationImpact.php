<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Purchase;
use App\Services\PurchaseService;

class PurchaseModificationImpact
{
    public function __construct(
        protected PurchaseService $purchaseService,
    ) {}

    /**
     * @return array{
     *     action: string,
     *     can_proceed: bool,
     *     blocked: bool,
     *     messages: list<array{level: string, text: string}>,
     *     stock_lines: list<array<string, mixed>>,
     *     supplier_payments: list<array<string, mixed>>,
     *     summary: string
     * }
     */
    public function analyzeDelete(Purchase $purchase): array
    {
        $purchase->loadMissing(['items', 'supplierPayments']);

        return $this->buildReport(
            action: 'delete',
            purchase: $purchase,
            stockItems: $purchase->items,
            newTotal: null,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $newItems
     * @return array{
     *     action: string,
     *     can_proceed: bool,
     *     blocked: bool,
     *     messages: list<array{level: string, text: string}>,
     *     stock_lines: list<array<string, mixed>>,
     *     supplier_payments: list<array<string, mixed>>,
     *     summary: string
     * }
     */
    public function analyzeUpdate(Purchase $purchase, array $newItems, ?float $newTotal = null): array
    {
        $purchase->loadMissing(['items', 'supplierPayments']);

        if ($newItems === []) {
            return [
                'action' => 'update',
                'can_proceed' => false,
                'blocked' => true,
                'messages' => [['level' => 'error', 'text' => 'At least one purchase line is required.']],
                'stock_lines' => [],
                'supplier_payments' => [],
                'summary' => 'Cannot update without items.',
            ];
        }

        $messages = [];
        $stockLines = [];
        $blocked = false;
        $branchId = (int) $purchase->branch_id;
        $hasStockChange = false;

        foreach ($this->purchaseService->purchaseLineStockDeltas($purchase, $newItems) as $delta) {
            if ($delta['reverse_quantity'] > 0.0001 && $delta['sample_item']) {
                $hasStockChange = true;
                $sampleItem = $delta['sample_item'];
                $evaluation = $this->purchaseService->evaluatePurchaseItemReversal(
                    $sampleItem,
                    $branchId,
                    $delta['reverse_quantity']
                );

                $stockLines[] = [
                    'item_name' => $evaluation['item_name'],
                    'quantity' => $delta['reverse_quantity'],
                    'unit' => $sampleItem->unit_name ?? '',
                    'reversible' => $evaluation['reversible'],
                    'available' => $evaluation['available'],
                    'required' => $evaluation['required'],
                    'message' => $evaluation['message'],
                    'change' => 'decrease',
                ];

                if (! $evaluation['reversible']) {
                    $blocked = true;
                    $messages[] = [
                        'level' => 'error',
                        'text' => $evaluation['message'] ?? "\"{$evaluation['item_name']}\" stock has already been consumed and cannot be reversed.",
                    ];
                }
            }

            if ($delta['add_quantity'] > 0.0001 && $delta['new_line']) {
                $hasStockChange = true;
                $line = $delta['new_line'];
                $name = $this->resolveLineItemName((string) $line['item_type'], (int) $line['item_id']);

                $stockLines[] = [
                    'item_name' => $name,
                    'quantity' => $delta['add_quantity'],
                    'unit' => $this->resolveLineUnitName($line),
                    'reversible' => true,
                    'available' => null,
                    'required' => $delta['add_quantity'],
                    'message' => null,
                    'change' => 'increase',
                ];
            }
        }

        $supplierPayments = $this->supplierPaymentLines($purchase);
        foreach ($supplierPayments as &$paymentLine) {
            $paymentLine['kept'] = true;
        }
        unset($paymentLine);

        $messages = array_merge($messages, $this->supplierPaymentMessagesForUpdate($purchase, $supplierPayments, $newTotal));

        $oldTotal = round((float) $purchase->total_amount, 2);
        if ($newTotal !== null && abs($newTotal - $oldTotal) >= 0.01) {
            $messages[] = [
                'level' => 'info',
                'text' => 'Purchase total will change from '.format_currency($oldTotal).' to '.format_currency($newTotal).'.',
            ];
        }

        if ($hasStockChange) {
            $messages[] = [
                'level' => 'info',
                'text' => 'Only changed quantities will be adjusted in stock. Unchanged lines with consumed stock are left as-is.',
            ];
        }

        $canProceed = ! $blocked;
        $summary = $blocked
            ? 'This change is blocked because purchased stock has already been used.'
            : 'You can save these changes. Stock, payments, and supplier balance will be adjusted.';

        return [
            'action' => 'update',
            'can_proceed' => $canProceed,
            'blocked' => $blocked,
            'messages' => $messages,
            'stock_lines' => $stockLines,
            'supplier_payments' => $supplierPayments,
            'summary' => $summary,
        ];
    }

    /**
     * @param  iterable<int, \App\Models\PurchaseItem>  $stockItems
     * @return array{
     *     action: string,
     *     can_proceed: bool,
     *     blocked: bool,
     *     messages: list<array{level: string, text: string}>,
     *     stock_lines: list<array<string, mixed>>,
     *     supplier_payments: list<array<string, mixed>>,
     *     summary: string
     * }
     */
    protected function buildReport(string $action, Purchase $purchase, iterable $stockItems, ?float $newTotal): array
    {
        $messages = [];
        $stockLines = [];
        $blocked = false;
        $branchId = (int) $purchase->branch_id;

        foreach ($stockItems as $item) {
            $evaluation = $this->purchaseService->evaluatePurchaseItemReversal(
                $item,
                $branchId,
                allowPartial: $action === 'delete'
            );
            $name = $evaluation['item_name'];
            $stockLines[] = [
                'item_name' => $name,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit_name ?? '',
                'reversible' => $evaluation['reversible'],
                'available' => $evaluation['available'],
                'required' => $evaluation['required'],
                'message' => $evaluation['message'],
            ];

            if (! $evaluation['reversible']) {
                $blocked = true;
                $messages[] = [
                    'level' => 'error',
                    'text' => $evaluation['message'] ?? "\"{$name}\" stock has already been consumed and cannot be reversed.",
                ];
            } elseif ($action === 'delete' && $evaluation['message']) {
                $messages[] = [
                    'level' => 'warning',
                    'text' => $evaluation['message'],
                ];
            }
        }

        $supplierPayments = $this->supplierPaymentLines($purchase);
        $messages = array_merge($messages, $this->supplierPaymentMessages($purchase, $supplierPayments));

        $paidAtPurchase = round((float) $purchase->paid_amount, 2);
        $unpaid = round(max(0, (float) $purchase->total_amount - $paidAtPurchase), 2);
        if ($unpaid > 0 && $purchase->supplier_id) {
            $messages[] = [
                'level' => 'info',
                'text' => 'Supplier balance will be reduced by '.format_currency($unpaid).' (unpaid portion on this purchase).',
            ];
        }

        if ($action === 'delete') {
            $messages[] = [
                'level' => 'warning',
                'text' => 'This purchase record will be permanently deleted.',
            ];
        }

        $canProceed = ! $blocked;
        $summary = $blocked
            ? 'This change is blocked because purchased stock has already been used.'
            : ($action === 'delete'
                ? ($this->deleteHasPartialStockWarnings($stockLines)
                    ? 'You can delete this purchase. Available stock will be reversed; consumed portions will stay deducted.'
                    : 'You can delete this purchase. Stock, payments, and supplier balance will be adjusted.')
                : 'You can save these changes. Stock, payments, and supplier balance will be adjusted.');

        return [
            'action' => $action,
            'can_proceed' => $canProceed,
            'blocked' => $blocked,
            'messages' => $messages,
            'stock_lines' => $stockLines,
            'supplier_payments' => $supplierPayments,
            'summary' => $summary,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $stockLines
     */
    protected function deleteHasPartialStockWarnings(array $stockLines): bool
    {
        foreach ($stockLines as $line) {
            if (! empty($line['message']) && (float) ($line['available'] ?? 0) + 0.0001 < (float) ($line['required'] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function supplierPaymentLines(Purchase $purchase): array
    {
        $supplierPayments = [];

        foreach ($purchase->supplierPayments as $payment) {
            $allocated = round((float) ($payment->pivot->amount ?? 0), 2);
            if ($allocated <= 0) {
                continue;
            }

            $supplierPayments[] = [
                'payment_number' => $payment->payment_number,
                'payment_id' => $payment->id,
                'allocated_amount' => $allocated,
                'payment_total' => round((float) $payment->total_amount, 2),
                'kept' => false,
            ];
        }

        return $supplierPayments;
    }

    /**
     * @param  list<array<string, mixed>>  $supplierPayments
     * @return list<array{level: string, text: string}>
     */
    protected function supplierPaymentMessages(Purchase $purchase, array $supplierPayments): array
    {
        $messages = [];

        if ($supplierPayments !== []) {
            $totalAllocated = round(collect($supplierPayments)->sum('allocated_amount'), 2);
            $messages[] = [
                'level' => 'warning',
                'text' => count($supplierPayments).' supplier payment(s) ('.format_currency($totalAllocated).' allocated to this purchase) will be unlinked and reversed.',
            ];
        }

        return $messages;
    }

    /**
     * @param  list<array<string, mixed>>  $supplierPayments
     * @return list<array{level: string, text: string}>
     */
    protected function supplierPaymentMessagesForUpdate(
        Purchase $purchase,
        array $supplierPayments,
        ?float $newTotal
    ): array {
        if ($supplierPayments === []) {
            return [];
        }

        $totalAllocated = round(collect($supplierPayments)->sum('allocated_amount'), 2);
        $oldTotal = round((float) $purchase->total_amount, 2);
        $resolvedNewTotal = $newTotal !== null ? round($newTotal, 2) : $oldTotal;
        $delta = round($resolvedNewTotal - $oldTotal, 2);

        if (abs($delta) < 0.01) {
            return [[
                'level' => 'info',
                'text' => count($supplierPayments).' linked supplier payment(s) ('.format_currency($totalAllocated).') will be kept.',
            ]];
        }

        if ($delta > 0) {
            return [[
                'level' => 'info',
                'text' => count($supplierPayments).' linked supplier payment(s) ('.format_currency($totalAllocated).') will be kept. '.format_currency($delta).' will be added to supplier balance.',
            ]];
        }

        return [[
            'level' => 'info',
            'text' => count($supplierPayments).' linked supplier payment(s) ('.format_currency($totalAllocated).') will be kept. '.format_currency(abs($delta)).' will be credited to supplier balance.',
        ]];
    }

    protected function resolveLineItemName(string $itemType, int $itemId): string
    {
        if ($itemType === 'ingredient') {
            return Ingredient::withoutGlobalScopes()->find($itemId)?->name ?? 'Ingredient';
        }

        if ($itemType === 'menu_item') {
            return MenuItem::withoutGlobalScopes()->find($itemId)?->name ?? 'Menu item';
        }

        return 'Item';
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function resolveLineUnitName(array $line): string
    {
        if (! isset($line['unit_id']) || $line['unit_id'] === null || $line['unit_id'] === '') {
            return '';
        }

        return UnitLabel::forIngredientUnitId($line['unit_id']) ?? '';
    }
}
