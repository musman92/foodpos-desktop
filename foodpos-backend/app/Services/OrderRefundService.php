<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\OrderRefundLine;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderRefundService
{
    public function __construct(
        protected InventoryService $inventoryService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function processRefund(Order $order, array $lines, string $notes, int $userId): OrderRefund
    {
        $normalized = $this->normalizeLines($order, $lines);

        return DB::transaction(function () use ($order, $normalized, $notes, $userId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $previousPaymentStatus = $order->payment_status;

            OrderItem::where('order_id', $order->id)->lockForUpdate()->get();
            $order->unsetRelation('items');
            $order->load([
                'items.menuItem.defaultRecipe.items.ingredient',
                'items.menuItem.variantRecipes.recipe.items.ingredient',
                'items.menuItem.legacyRecipeLines.ingredient',
                'payments',
                'customer',
            ]);

            $computedLines = [];
            $batchSubtotal = 0.0;

            foreach ($normalized as $row) {
                $item = $order->items->firstWhere('id', $row['order_item_id']);
                if (! $item) {
                    throw new InvalidArgumentException('Invalid order line.');
                }
                $billableBefore = (float) $item->quantity - (float) $item->quantity_refunded;
                if ($row['quantity'] > $billableBefore + 0.0001) {
                    throw new InvalidArgumentException("Refund quantity exceeds remaining for line #{$item->id}.");
                }
                $unitValue = $billableBefore > 0 ? ((float) $item->total_price) / $billableBefore : 0.0;
                $refundSub = round($row['quantity'] * $unitValue, 2);
                $batchSubtotal += $refundSub;
                $computedLines[] = [
                    'item_id' => $item->id,
                    'qty' => $row['quantity'],
                    'line_notes' => $row['line_notes'] ?? null,
                    'refund_subtotal' => $refundSub,
                ];
            }

            $preSubtotal = (float) $order->subtotal;
            $preTax = (float) $order->tax_amount;
            $preTotal = (float) $order->total_amount;
            $prePaid = (float) $order->paid_amount;

            $batchTax = 0.0;
            if ($batchSubtotal > 0 && $preSubtotal > 0.0001) {
                $batchTax = round($preTax * ($batchSubtotal / $preSubtotal), 2);
            }
            $batchTax = min($batchTax, $preTax);

            $batchTotal = round($batchSubtotal + $batchTax, 2);
            if ($batchTotal > $preTotal + 0.02) {
                throw new InvalidArgumentException('Refund exceeds current order total.');
            }

            $n = count($computedLines);
            $allocatedTax = 0.0;
            foreach ($computedLines as $idx => &$cl) {
                if ($idx === $n - 1) {
                    $cl['refund_tax'] = round($batchTax - $allocatedTax, 2);
                } else {
                    $share = $batchSubtotal > 0 ? ($cl['refund_subtotal'] / $batchSubtotal) : 0.0;
                    $cl['refund_tax'] = round($batchTax * $share, 2);
                    $allocatedTax += $cl['refund_tax'];
                }
            }
            unset($cl);

            $orderRefund = OrderRefund::create([
                'order_id' => $order->id,
                'created_by' => $userId,
                'subtotal_refund' => round($batchSubtotal, 2),
                'tax_refund' => round($batchTax, 2),
                'total_refund' => $batchTotal,
                'notes' => $notes,
            ]);

            foreach ($computedLines as $cl) {
                $item = OrderItem::where('order_id', $order->id)->whereKey($cl['item_id'])->lockForUpdate()->firstOrFail();
                $oldTotal = (float) $item->total_price;

                $refundLine = OrderRefundLine::create([
                    'order_refund_id' => $orderRefund->id,
                    'order_item_id' => $item->id,
                    'quantity' => $cl['qty'],
                    'refund_subtotal' => $cl['refund_subtotal'],
                    'refund_tax' => $cl['refund_tax'],
                    'restock_inventory' => true,
                    'line_notes' => $cl['line_notes'],
                ]);

                $item->quantity_refunded = round((float) $item->quantity_refunded + (float) $cl['qty'], 2);
                $item->total_price = round(max(0, $oldTotal - (float) $cl['refund_subtotal']), 2);
                $item->save();

                $this->inventoryService->restockOrderItemForRefund(
                    $item->fresh([
                        'menuItem.defaultRecipe.items.ingredient',
                        'menuItem.variantRecipes.recipe.items.ingredient',
                        'menuItem.legacyRecipeLines.ingredient',
                    ]),
                    (float) $cl['qty'],
                    (int) $order->branch_id,
                    $userId,
                    $refundLine->id,
                    $order->order_number
                );
            }

            $order->subtotal = round(max(0, $preSubtotal - $batchSubtotal), 2);
            $order->tax_amount = round(max(0, $preTax - $batchTax), 2);
            $order->total_amount = round(
                (float) $order->subtotal + (float) $order->tax_amount + (float) $order->service_charge + (float) $order->delivery_fee - (float) $order->discount_amount,
                2
            );

            [$cashReverse, $creditReverse] = $this->splitRefundBetweenCashAndCredit($preTotal, $prePaid, $batchTotal);

            if (in_array($previousPaymentStatus, ['paid', 'partial', 'refunded'], true) && $batchTotal > 0.001) {
                if ($cashReverse > 0.001) {
                    $this->createCashRefundTransactions($order, $cashReverse, $userId, $notes);
                }

                if ($creditReverse > 0.001 && $order->customer_id) {
                    $this->reverseCustomerCreditPortion($order, $creditReverse);
                }
            }

            $order->paid_amount = round(max(0, $prePaid - $cashReverse), 2);
            $order->paid_at_sale = round(max(0, (float) $order->paid_at_sale - $cashReverse), 2);
            $order->payment_status = $this->resolvePaymentStatusAfterRefund(
                (float) $order->total_amount,
                (float) $order->paid_amount,
                $previousPaymentStatus
            );

            $order->save();

            return $orderRefund->fresh(['lines']);
        });
    }

    /**
     * Proportional split of refund amount across remaining cash paid vs credit outstanding.
     *
     * @return array{0: float, 1: float} [cashReverse, creditReverse]
     */
    protected function splitRefundBetweenCashAndCredit(float $remainingTotal, float $remainingPaid, float $batchTotal): array
    {
        if ($batchTotal <= 0.001 || $remainingTotal <= 0.001) {
            return [0.0, 0.0];
        }

        $cashShare = min(1.0, max(0.0, $remainingPaid / $remainingTotal));
        $cashReverse = round($batchTotal * $cashShare, 2);
        $cashReverse = min($cashReverse, $remainingPaid, $batchTotal);
        $creditReverse = round($batchTotal - $cashReverse, 2);

        if ($creditReverse < 0) {
            $creditReverse = 0.0;
            $cashReverse = $batchTotal;
        }

        return [$cashReverse, $creditReverse];
    }

    protected function createCashRefundTransactions(Order $order, float $cashReverse, int $userId, string $notes): void
    {
        $accountId = $this->resolveRefundAccountId($order);
        if (! $accountId) {
            throw new InvalidArgumentException(
                'Cannot reverse payment: no Sales account (or prior sale transaction) found for this company.'
            );
        }

        $allocations = $this->allocateCashAcrossMoneySources($order, $cashReverse);
        $paymentMethodMap = [
            'cash' => 'cash',
            'card' => 'card',
            'digital_wallet' => 'online',
            'online' => 'online',
            'transfer' => 'transfer',
            'credit' => 'cash',
            'split' => 'cash',
        ];

        foreach ($allocations as $row) {
            $amount = (float) $row['amount'];
            if ($amount <= 0.001) {
                continue;
            }

            Transaction::create([
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'account_id' => $accountId,
                'amount' => $amount,
                'type' => 'out',
                'payment_method' => $paymentMethodMap[$row['payment_method']] ?? 'cash',
                'money_source_id' => $row['money_source_id'],
                'reference_type' => 'refund',
                'date' => now()->toDateString(),
                'ref_id' => $order->id,
                'created_by' => $userId,
                'shift_id' => $order->shift_id,
                'notes' => 'Refund for order #'.$order->order_number.'. '.mb_substr($notes, 0, 500),
            ]);
        }
    }

    /**
     * @return list<array{money_source_id: ?int, amount: float, payment_method: string}>
     */
    protected function allocateCashAcrossMoneySources(Order $order, float $cashReverse): array
    {
        $nets = $this->remainingCashByMoneySource($order);
        $totalNet = round(array_sum(array_column($nets, 'amount')), 2);

        if ($totalNet <= 0.001) {
            return [[
                'money_source_id' => $order->money_source_id ? (int) $order->money_source_id : null,
                'amount' => $cashReverse,
                'payment_method' => (string) ($order->payment_method ?? 'cash'),
            ]];
        }

        $allocated = 0.0;
        $rows = [];
        $count = count($nets);
        foreach ($nets as $idx => $net) {
            if ($idx === $count - 1) {
                $amount = round($cashReverse - $allocated, 2);
            } else {
                $share = $net['amount'] / $totalNet;
                $amount = round($cashReverse * $share, 2);
                $allocated += $amount;
            }

            $rows[] = [
                'money_source_id' => $net['money_source_id'],
                'amount' => max(0, $amount),
                'payment_method' => $net['payment_method'],
            ];
        }

        return $rows;
    }

    /**
     * Net cash still attributed to this order per money source (sale in − refund out).
     *
     * @return list<array{money_source_id: ?int, amount: float, payment_method: string}>
     */
    protected function remainingCashByMoneySource(Order $order): array
    {
        $transactions = Transaction::query()
            ->where('company_id', $order->company_id)
            ->where('ref_id', $order->id)
            ->whereIn('reference_type', ['sale', 'refund'])
            ->get();

        $nets = [];
        foreach ($transactions as $txn) {
            $key = $txn->money_source_id !== null ? (string) $txn->money_source_id : 'null';
            if (! isset($nets[$key])) {
                $nets[$key] = [
                    'money_source_id' => $txn->money_source_id !== null ? (int) $txn->money_source_id : null,
                    'amount' => 0.0,
                    'payment_method' => (string) ($txn->payment_method ?? 'cash'),
                ];
            }

            $amount = (float) $txn->amount;
            if ($txn->reference_type === 'sale' && $txn->type === 'in') {
                $nets[$key]['amount'] += $amount;
            } elseif ($txn->reference_type === 'refund' && $txn->type === 'out') {
                $nets[$key]['amount'] -= $amount;
            }
        }

        return array_values(array_filter(
            $nets,
            fn (array $row) => $row['amount'] > 0.001
        ));
    }

    protected function resolveRefundAccountId(Order $order): ?int
    {
        $fromSale = Transaction::query()
            ->where('company_id', $order->company_id)
            ->where('ref_id', $order->id)
            ->where('reference_type', 'sale')
            ->whereNotNull('account_id')
            ->value('account_id');

        if ($fromSale) {
            return (int) $fromSale;
        }

        $sales = Account::query()
            ->where('company_id', $order->company_id)
            ->where('name', 'Sales')
            ->where('type', 'income')
            ->where('is_active', true)
            ->value('id');

        return $sales ? (int) $sales : null;
    }

    protected function reverseCustomerCreditPortion(Order $order, float $creditReverse): void
    {
        $customer = Customer::withoutTenantScope()
            ->whereKey($order->customer_id)
            ->lockForUpdate()
            ->first();

        if (! $customer) {
            return;
        }

        // Credit sales increased balance by (total − paid). Reverse that portion.
        $customer->balance = round((float) ($customer->balance ?? 0) - $creditReverse, 2);
        $customer->save();
    }

    protected function resolvePaymentStatusAfterRefund(float $total, float $paid, string $previous): string
    {
        if ($total < 0.01) {
            return 'refunded';
        }

        if ($paid + 0.01 >= $total) {
            return 'paid';
        }

        if ($paid > 0.01) {
            return 'partial';
        }

        if (in_array($previous, ['paid', 'partial', 'refunded'], true)) {
            return 'unpaid';
        }

        return $previous;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{order_item_id:int, quantity:float, line_notes:?string}>
     */
    protected function normalizeLines(Order $order, array $lines): array
    {
        $out = [];
        foreach ($lines as $row) {
            if (empty($row['order_item_id']) || empty($row['quantity'])) {
                continue;
            }
            $qty = (float) $row['quantity'];
            if ($qty <= 0) {
                continue;
            }
            $item = OrderItem::where('order_id', $order->id)->whereKey($row['order_item_id'])->first();
            if (! $item) {
                throw new InvalidArgumentException('Order line does not belong to this order.');
            }
            $out[] = [
                'order_item_id' => (int) $row['order_item_id'],
                'quantity' => $qty,
                'line_notes' => isset($row['line_notes']) ? (string) $row['line_notes'] : null,
            ];
        }

        if ($out === []) {
            throw new InvalidArgumentException('Add at least one line with a refund quantity greater than zero.');
        }

        return $out;
    }
}
