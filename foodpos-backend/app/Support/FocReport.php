<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FocReport
{
    private const ORDER_TYPE_LABELS = [
        'dine_in' => 'Dine In',
        'takeaway' => 'Takeaway',
        'delivery' => 'Delivery',
    ];

    /**
     * @return array{
     *   summary: array{order_count: int, total_value: float},
     *   rows: Collection<int, array{
     *     id: int,
     *     date: string,
     *     order_number: string,
     *     branch: string,
     *     type: string,
     *     type_label: string,
     *     customer: string,
     *     cashier: string,
     *     item_count: int,
     *     subtotal: float,
     *     discount_amount: float,
     *     tax_amount: float,
     *     total_amount: float
     *   }>
     * }
     */
    public static function build(User $user, ?int $branchId, string $from, string $to): array
    {
        $query = Order::query()
            ->withoutGlobalScopes()
            ->where('payment_method', 'foc')
            ->where('status', 'completed')
            ->with(['branch:id,name', 'cashier:id,name', 'customer:id,name', 'items:id,order_id'])
            ->orderByDesc('created_at');

        self::applyBranchScope($query, $user, $branchId);
        tz()->applyBusinessDateRange($query, $from, $to, $branchId);

        $orders = $query->get();

        $rows = $orders->map(function (Order $order) {
            $type = (string) $order->type;

            return [
                'id' => (int) $order->id,
                'date' => $order->created_at?->format('Y-m-d H:i') ?? '—',
                'order_number' => (string) ($order->order_number ?: '#'.$order->id),
                'branch' => (string) ($order->branch?->name ?? '—'),
                'type' => $type,
                'type_label' => self::ORDER_TYPE_LABELS[$type] ?? ucwords(str_replace('_', ' ', $type)),
                'customer' => (string) ($order->customer?->name
                    ?: ($order->customer_name ?: 'Walk-in')),
                'cashier' => (string) ($order->cashier?->name ?? '—'),
                'item_count' => (int) $order->items->count(),
                'subtotal' => round((float) $order->subtotal, 2),
                'discount_amount' => round((float) $order->discount_amount, 2),
                'tax_amount' => round((float) $order->tax_amount, 2),
                'total_amount' => round((float) $order->total_amount, 2),
            ];
        })->values();

        return [
            'summary' => [
                'order_count' => $rows->count(),
                'total_value' => round((float) $rows->sum('total_amount'), 2),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Builder<\App\Models\Order>  $query
     */
    protected static function applyBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return;
        }

        if ($user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif (! $user->isCompanyAdmin() && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
    }
}
