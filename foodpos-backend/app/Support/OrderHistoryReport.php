<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrderHistoryReport
{
    public const PDF_LIMIT = 2000;

    public const WEB_PER_PAGE = 50;

    /**
     * @param  array{
     *     branch_id?: int|string|null,
     *     from: string,
     *     to: string,
     *     customer_id?: int|string|null,
     *     waiter_id?: int|string|null,
     *     delivery_rider_id?: int|string|null,
     *     type?: string|null,
     *     order_number?: string|null
     * }  $filters
     */
    public static function baseQuery(User $user, array $filters): Builder
    {
        $branchId = self::nullableInt($filters['branch_id'] ?? null);

        $query = Order::query()
            ->with(['branch', 'customer', 'waiter', 'deliveryRider', 'table', 'cashier'])
            ->withCount('items')
            ->where('status', 'completed');

        self::applyBranchScope($query, $user, $branchId);

        if (! empty($filters['from']) && ! empty($filters['to'])) {
            tz()->applyBusinessDateRange(
                $query,
                (string) $filters['from'],
                (string) $filters['to'],
                $branchId
            );
        }

        if ($customerId = self::nullableInt($filters['customer_id'] ?? null)) {
            $query->where('customer_id', $customerId);
        }

        if ($waiterId = self::nullableInt($filters['waiter_id'] ?? null)) {
            $query->where('waiter_id', $waiterId);
        }

        if ($riderId = self::nullableInt($filters['delivery_rider_id'] ?? null)) {
            $query->where('delivery_rider_id', $riderId);
        }

        $type = $filters['type'] ?? null;
        if ($type && in_array($type, ['dine_in', 'takeaway', 'delivery'], true)) {
            $query->where('type', $type);
        }

        $orderNumber = trim((string) ($filters['order_number'] ?? ''));
        if ($orderNumber !== '') {
            $query->where('order_number', 'like', '%'.$orderNumber.'%');
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return array{
     *     order_count: int,
     *     total_amount: float,
     *     by_type: array<string, array{count: int, amount: float}>
     * }
     */
    public static function summarizeFromQuery(Builder $baseQuery): array
    {
        $byType = [];
        foreach (['dine_in', 'takeaway', 'delivery'] as $type) {
            $typeQuery = clone $baseQuery;
            $byType[$type] = [
                'count' => (int) (clone $typeQuery)->where('type', $type)->count(),
                'amount' => round((float) (clone $typeQuery)->where('type', $type)->sum('total_amount'), 2),
            ];
        }

        return [
            'order_count' => (int) (clone $baseQuery)->count(),
            'total_amount' => round((float) (clone $baseQuery)->sum('total_amount'), 2),
            'by_type' => $byType,
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array{
     *     order_count: int,
     *     total_amount: float,
     *     by_type: array<string, array{count: int, amount: float}>
     * }
     */
    public static function summarize(Collection $orders): array
    {
        $byType = [];
        foreach (['dine_in', 'takeaway', 'delivery'] as $type) {
            $subset = $orders->where('type', $type);
            $byType[$type] = [
                'count' => $subset->count(),
                'amount' => round((float) $subset->sum('total_amount'), 2),
            ];
        }

        return [
            'order_count' => $orders->count(),
            'total_amount' => round((float) $orders->sum('total_amount'), 2),
            'by_type' => $byType,
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            'dine_in' => 'Dine in',
            'takeaway' => 'Take away',
            'delivery' => 'Delivery',
            default => $type ? ucfirst(str_replace('_', ' ', $type)) : '—',
        };
    }

    public static function orderCountLabel(int $count): string
    {
        $formatted = number_format($count);

        return $count === 1 ? "{$formatted} order" : "{$formatted} orders";
    }

    /**
     * @param  array{
     *     order_count: int,
     *     total_amount: float,
     *     by_type: array<string, array{count: int, amount: float}>
     * }  $summary
     * @return list<array{key: string, label: string, count: int, amount: float, count_label: string}>
     */
    public static function typeRowsForDisplay(array $summary): array
    {
        $rows = [];

        foreach (['dine_in', 'takeaway', 'delivery'] as $type) {
            $bucket = $summary['by_type'][$type] ?? ['count' => 0, 'amount' => 0.0];
            if ((int) $bucket['count'] <= 0) {
                continue;
            }

            $count = (int) $bucket['count'];
            $rows[] = [
                'key' => $type,
                'label' => self::typeLabel($type),
                'count' => $count,
                'amount' => (float) $bucket['amount'],
                'count_label' => self::orderCountLabel($count),
            ];
        }

        return $rows;
    }

    public static function customerDisplayName(Order $order): string
    {
        if ($order->customer) {
            return $order->customer->name;
        }

        $name = trim((string) ($order->customer_name ?? ''));

        return $name !== '' ? $name : '—';
    }

    public static function formatOrderDate(Order $order): string
    {
        $branchId = $order->branch_id ? (int) $order->branch_id : null;

        return tz()->formatHistoryTimestamp($order->created_at, $branchId);
    }

    /**
     * @return Collection<int, Customer>
     */
    public static function customersForFilter(User $user): Collection
    {
        return Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  Collection<int, Branch>  $availableBranches
     * @return Collection<int, User>
     */
    public static function staffForFilter(User $user, ?int $branchId, Collection $availableBranches): Collection
    {
        $query = User::query()
            ->where('status', 'active')
            ->orderBy('name');

        if ($branchId) {
            $companyId = Branch::find($branchId)?->company_id ?? $user->company_id;
            if ($companyId) {
                $query->where('company_id', $companyId);
            }
            $query->where(function (Builder $staffQuery) use ($branchId) {
                $staffQuery->where('branch_id', $branchId)
                    ->orWhereHas('branches', function (Builder $branchQuery) use ($branchId) {
                        $branchQuery->where('branches.id', $branchId);
                    });
            });
        } elseif ($user->company_id) {
            $query->where('company_id', $user->company_id);
        } elseif ($user->isSuperAdmin()) {
            $branchIds = $availableBranches->pluck('id')->all();
            if (! empty($branchIds)) {
                $query->where(function (Builder $staffQuery) use ($branchIds) {
                    $staffQuery->whereIn('branch_id', $branchIds)
                        ->orWhereHas('branches', function (Builder $branchQuery) use ($branchIds) {
                            $branchQuery->whereIn('branches.id', $branchIds);
                        });
                });
            }
        } else {
            return collect();
        }

        return $query->get(['id', 'name']);
    }

    /**
     * @return array{
     *     period_label: string,
     *     period_start: string,
     *     period_end: string
     * }
     */
    public static function periodMeta(string $from, string $to): array
    {
        return [
            'period_label' => format_date($from).' – '.format_date($to),
            'period_start' => $from,
            'period_end' => $to,
        ];
    }

    protected static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected static function applyBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->where($query->getModel()->getTable().'.branch_id', $branchId);
            }

            return;
        }

        if ($user->isCompanyAdmin() && $user->company_id) {
            $query->where($query->getModel()->getTable().'.company_id', $user->company_id);
            if ($branchId) {
                $query->where($query->getModel()->getTable().'.branch_id', $branchId);
            }

            return;
        }

        $query->where($query->getModel()->getTable().'.company_id', $user->company_id);

        if ($branchId) {
            $query->where($query->getModel()->getTable().'.branch_id', $branchId);
        } else {
            $branchIds = $user->branches()->where('status', 'active')->pluck('branches.id')->toArray();
            if (! empty($branchIds)) {
                $query->whereIn($query->getModel()->getTable().'.branch_id', $branchIds);
            } elseif ($user->branch_id) {
                $query->where($query->getModel()->getTable().'.branch_id', $user->branch_id);
            }
        }
    }
}
