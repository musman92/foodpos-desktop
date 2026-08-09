<?php

namespace App\Support;

use App\Models\MoneySourceFundMovement;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Shift;
use App\Models\Transaction;
use App\Services\ShiftService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ShiftZReport
{
    private const ORDER_TYPE_LABELS = [
        'dine_in' => 'Dine In',
        'takeaway' => 'Takeaway',
        'delivery' => 'Delivery',
    ];

    private const PAYMENT_METHOD_LABELS = [
        'cash' => 'Cash',
        'card' => 'Card',
        'transfer' => 'Transfer',
        'online' => 'Online',
        'digital_wallet' => 'Digital Wallet',
        'split' => 'Split',
        'credit' => 'Credit',
    ];

    /**
     * @return array{
     *   shift: Shift,
     *   is_interim: bool,
     *   generated_at: string,
     *   sales: array{
     *     order_count: int,
     *     cancelled_count: int,
     *     gross_sales: float,
     *     discounts: float,
     *     tax: float,
     *     service_charge: float,
     *     delivery_fees: float,
     *     gross_total: float,
     *     refund_count: int,
     *     refunds: float,
     *     net_sales: float,
     *     average_ticket: float
     *   },
     *   order_types: Collection<int, array{type: string, label: string, count: int, amount: float}>,
     *   payment_methods: Collection<int, array{method: string, label: string, sales: float, refunds: float, net: float}>,
     *   money_sources: Collection<int, array{
     *     id: int,
     *     name: string,
     *     type: string,
     *     opening: float,
     *     expected: float,
     *     closing: ?float,
     *     difference: ?float
     *   }>,
     *   cash_summary: array{expected: float, actual: ?float, difference: ?float},
     *   fund_movements: Collection<int, array{
     *     date: string,
     *     type: string,
     *     from: string,
     *     to: string,
     *     amount: float,
     *     notes: ?string
     *   }>,
     *   transaction_summary: array{
     *     sales: int,
     *     refunds: int,
     *     customer_payments: int,
     *     other: int
     *   }
     * }
     */
    public static function build(Shift $shift, ShiftService $shiftService): array
    {
        $shift->loadMissing(['branch', 'company', 'openedBy', 'closedBy', 'moneySources']);

        $ordersQuery = self::ordersQuery($shift);
        $orderIds = (clone $ordersQuery)->pluck('id');

        $completedOrders = (clone $ordersQuery)->where('status', 'completed');
        $orderCount = (int) (clone $completedOrders)->count();
        $cancelledCount = (int) (clone $ordersQuery)->where('status', 'cancelled')->count();

        $grossSales = round((float) (clone $completedOrders)->sum('subtotal'), 2);
        $discounts = round((float) (clone $completedOrders)->sum('discount_amount'), 2);
        $tax = round((float) (clone $completedOrders)->sum('tax_amount'), 2);
        $serviceCharge = round((float) (clone $completedOrders)->sum('service_charge'), 2);
        $deliveryFees = round((float) (clone $completedOrders)->sum('delivery_fee'), 2);
        $grossTotal = round((float) (clone $completedOrders)->sum('total_amount'), 2);

        $refundsQuery = OrderRefund::query()->whereIn('order_id', $orderIds);
        $refundCount = (int) (clone $refundsQuery)->count();
        $refundsTotal = round((float) (clone $refundsQuery)->sum('total_refund'), 2);
        $netSales = round($grossTotal - $refundsTotal, 2);
        $averageTicket = $orderCount > 0 ? round($grossTotal / $orderCount, 2) : 0.0;

        $orderTypes = self::buildOrderTypeBreakdown($completedOrders);
        $paymentMethods = self::buildPaymentBreakdown(self::transactionsQuery($shift)->get());
        $fundMovements = self::buildFundMovements(self::fundMovementsQuery($shift)->get());
        $transactionSummary = self::buildTransactionSummary(self::transactionsQuery($shift)->get());

        if ($shift->isActive()) {
            $expectedBalances = $shiftService->calculateExpectedBalances($shift);
        } else {
            $expectedBalances = [];
            foreach ($shift->moneySources as $moneySource) {
                $expectedBalances[(int) $moneySource->id] = (float) ($moneySource->pivot->expected_balance ?? $moneySource->pivot->opening_balance ?? 0);
            }
        }

        $moneySources = $shift->moneySources->map(function ($moneySource) use ($shift, $expectedBalances) {
            $id = (int) $moneySource->id;
            $opening = (float) ($moneySource->pivot->opening_balance ?? 0);
            $expected = (float) ($expectedBalances[$id] ?? $opening);
            $closingRaw = $moneySource->pivot->closing_balance ?? null;
            $closing = $closingRaw !== null && $closingRaw !== '' ? (float) $closingRaw : null;
            $difference = $shift->isClosed()
                ? (float) ($moneySource->pivot->difference ?? 0)
                : null;

            return [
                'id' => $id,
                'name' => $moneySource->name,
                'type' => $moneySource->type,
                'opening' => $opening,
                'expected' => $expected,
                'closing' => $closing,
                'difference' => $difference,
            ];
        })->values();

        $cashSources = $moneySources->filter(fn (array $row) => strtoupper((string) $row['type']) === 'CASH');
        $expectedCash = round((float) $cashSources->sum('expected'), 2);

        if ($shift->isClosed()) {
            $actualCash = round((float) ($shift->actual_cash ?? $cashSources->sum('closing')), 2);
            $cashDifference = round((float) ($shift->cash_difference ?? ($actualCash - $expectedCash)), 2);
        } else {
            $actualCash = null;
            $cashDifference = null;
        }

        return [
            'shift' => $shift,
            'is_interim' => $shift->isActive(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'sales' => [
                'order_count' => $orderCount,
                'cancelled_count' => $cancelledCount,
                'gross_sales' => $grossSales,
                'discounts' => $discounts,
                'tax' => $tax,
                'service_charge' => $serviceCharge,
                'delivery_fees' => $deliveryFees,
                'gross_total' => $grossTotal,
                'refund_count' => $refundCount,
                'refunds' => $refundsTotal,
                'net_sales' => $netSales,
                'average_ticket' => $averageTicket,
            ],
            'order_types' => $orderTypes,
            'payment_methods' => $paymentMethods,
            'money_sources' => $moneySources,
            'cash_summary' => [
                'expected' => $expectedCash,
                'actual' => $actualCash,
                'difference' => $cashDifference,
            ],
            'fund_movements' => $fundMovements,
            'transaction_summary' => $transactionSummary,
        ];
    }

    public static function ordersQuery(Shift $shift): Builder
    {
        return Order::withoutGlobalScope('branch')
            ->where(function (Builder $query) use ($shift) {
                $query->where('shift_id', $shift->id)
                    ->orWhere(function (Builder $legacy) use ($shift) {
                        $legacy->whereNull('shift_id')
                            ->where('branch_id', $shift->branch_id)
                            ->where('cashier_id', $shift->opened_by)
                            ->where('created_at', '>=', $shift->opened_at)
                            ->when($shift->closed_at, function (Builder $query) use ($shift) {
                                $query->where('created_at', '<=', $shift->closed_at);
                            });
                    });
            });
    }

    public static function transactionsQuery(Shift $shift): Builder
    {
        $shiftDate = $shift->shift_date->format('Y-m-d');

        return Transaction::withoutGlobalScope('branch')
            ->where(function (Builder $query) use ($shift, $shiftDate) {
                $query->where('shift_id', $shift->id)
                    ->orWhere(function (Builder $legacy) use ($shift, $shiftDate) {
                        $legacy->whereNull('shift_id')
                            ->where('branch_id', $shift->branch_id)
                            ->where('created_by', $shift->opened_by)
                            ->whereDate('date', $shiftDate)
                            ->where('created_at', '>=', $shift->opened_at)
                            ->when($shift->closed_at, function (Builder $query) use ($shift) {
                                $query->where('created_at', '<=', $shift->closed_at);
                            });
                    });
            });
    }

    public static function fundMovementsQuery(Shift $shift): Builder
    {
        return MoneySourceFundMovement::query()
            ->where(function (Builder $query) use ($shift) {
                $query->where('shift_id', $shift->id)
                    ->orWhere(function (Builder $legacy) use ($shift) {
                        $legacy->whereNull('shift_id')
                            ->where('branch_id', $shift->branch_id)
                            ->where('created_by', $shift->opened_by)
                            ->whereDate('movement_date', $shift->shift_date->format('Y-m-d'))
                            ->where('created_at', '>=', $shift->opened_at)
                            ->when($shift->closed_at, function (Builder $query) use ($shift) {
                                $query->where('created_at', '<=', $shift->closed_at);
                            });
                    });
            });
    }

    /**
     * @return Collection<int, array{type: string, label: string, count: int, amount: float}>
     */
    private static function buildOrderTypeBreakdown(Builder $ordersQuery): Collection
    {
        $rows = (clone $ordersQuery)
            ->selectRaw('type, COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->groupBy('type')
            ->get();

        return $rows->map(function ($row) {
            $type = (string) ($row->type ?? 'unknown');

            return [
                'type' => $type,
                'label' => self::ORDER_TYPE_LABELS[$type] ?? ucwords(str_replace('_', ' ', $type)),
                'count' => (int) $row->order_count,
                'amount' => round((float) $row->total_amount, 2),
            ];
        })->sortByDesc('amount')->values();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return Collection<int, array{method: string, label: string, sales: float, refunds: float, net: float}>
     */
    private static function buildPaymentBreakdown(Collection $transactions): Collection
    {
        $totals = [];

        foreach ($transactions as $transaction) {
            if (! in_array($transaction->reference_type, ['sale', 'refund', 'customer_payment'], true)) {
                continue;
            }

            $method = (string) ($transaction->payment_method ?: 'unknown');
            if (! isset($totals[$method])) {
                $totals[$method] = ['sales' => 0.0, 'refunds' => 0.0];
            }

            $amount = (float) $transaction->amount;

            if ($transaction->type === 'in') {
                $totals[$method]['sales'] += $amount;
            } else {
                $totals[$method]['refunds'] += $amount;
            }
        }

        return collect($totals)
            ->map(function (array $amounts, string $method) {
                $sales = round($amounts['sales'], 2);
                $refunds = round($amounts['refunds'], 2);

                return [
                    'method' => $method,
                    'label' => self::PAYMENT_METHOD_LABELS[$method] ?? ucwords(str_replace('_', ' ', $method)),
                    'sales' => $sales,
                    'refunds' => $refunds,
                    'net' => round($sales - $refunds, 2),
                ];
            })
            ->sortByDesc('net')
            ->values();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array{sales: int, refunds: int, customer_payments: int, other: int}
     */
    private static function buildTransactionSummary(Collection $transactions): array
    {
        $summary = [
            'sales' => 0,
            'refunds' => 0,
            'customer_payments' => 0,
            'other' => 0,
        ];

        foreach ($transactions as $transaction) {
            match ($transaction->reference_type) {
                'sale' => $summary['sales']++,
                'refund' => $summary['refunds']++,
                'customer_payment' => $summary['customer_payments']++,
                default => $summary['other']++,
            };
        }

        return $summary;
    }

    /**
     * @param  Collection<int, MoneySourceFundMovement>  $movements
     * @return Collection<int, array{date: string, type: string, from: string, to: string, amount: float, notes: ?string}>
     */
    private static function buildFundMovements(Collection $movements): Collection
    {
        $movements->loadMissing(['fromMoneySource', 'toMoneySource']);

        return $movements->map(function (MoneySourceFundMovement $movement) {
            $type = $movement->movement_type === MoneySourceFundMovement::TYPE_OWNER_WITHDRAWAL
                ? 'Owner Withdrawal'
                : 'Transfer';

            return [
                'date' => $movement->movement_date?->format('Y-m-d') ?? '',
                'type' => $type,
                'from' => $movement->fromMoneySource?->name ?? '—',
                'to' => $movement->toMoneySource?->name ?? '—',
                'amount' => round((float) $movement->amount, 2),
                'notes' => $movement->notes,
            ];
        })->values();
    }
}
