<?php

namespace App\Support;

use App\Models\BranchStock;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\MenuItemStock;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardMetrics
{
    /**
     * @return array{
     *     revenue: float,
     *     cost_of_goods: float,
     *     net_profit: float,
     *     net_profit_breakdown: array{
     *         total_sale: float,
     *         cogs: float,
     *         expenses_total: float,
     *         expenses: list<array{date: string, label: string, detail: string, amount: float}>,
     *         payouts_total: float,
     *         payouts: list<array{date: string, label: string, account: string, detail: string, amount: float}>,
     *         payout_groups: list<array{label: string, total: float, rows: list}>,
     *         net_profit: float
     *     },
     *     transactions: int,
     *     customers: int,
     *     average_receipt: float,
     *     label: string,
     *     start_date: string,
     *     end_date: string
     * }
     */
    public static function summaryForRange(User $user, int $branchId, string $startDate, string $endDate): array
    {
        $ordersQuery = self::ordersQuery($user, $branchId, $startDate, $endDate);

        $revenue = round((float) (clone $ordersQuery)->sum('total_amount'), 2);
        $transactions = (int) (clone $ordersQuery)->count();
        $customers = self::countUniqueCustomers($ordersQuery);
        $averageReceipt = $transactions > 0 ? round($revenue / $transactions, 2) : 0.0;
        $costOfGoods = self::costOfGoodsForOrders((clone $ordersQuery)->pluck('id'));

        $pl = ProfitLossReport::build($user, $branchId, $startDate, $endDate);
        $breakdown = self::dashboardNetProfitBreakdown(
            $branchId,
            $startDate,
            $endDate,
            (float) $pl['revenue']['net_sales'],
            (float) $pl['cogs']['total'],
        );

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $label = $start->isSameDay($end)
            ? $start->format('M j, Y')
            : $start->format('M j, Y').' – '.$end->format('M j, Y');

        return [
            'revenue' => $revenue,
            'cost_of_goods' => $costOfGoods,
            'net_profit' => $breakdown['net_profit'],
            'net_profit_breakdown' => $breakdown,
            'transactions' => $transactions,
            'customers' => $customers,
            'average_receipt' => $averageReceipt,
            'label' => $label,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * @return array{
     *     revenue: float,
     *     cost_of_goods: float,
     *     net_profit: float,
     *     transactions: int,
     *     customers: int,
     *     average_receipt: float,
     *     label: string,
     *     start_date: string,
     *     end_date: string
     * }
     */
    public static function summaryForToday(User $user, int $branchId): array
    {
        $today = local_now($branchId)->toDateString();

        return self::summaryForRange($user, $branchId, $today, $today);
    }

    /**
     * Daily revenue points for charting (client can roll up to week/month).
     *
     * @return array{labels: list<string>, revenue: list<float>, dates: list<string>}
     */
    public static function dailyRevenueSeries(User $user, int $branchId, string $startDate, string $endDate): array
    {
        $labels = [];
        $revenue = [];
        $dates = [];

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();

            $dayRevenue = (float) self::ordersQuery($user, $branchId, $dateStr, $dateStr)->sum('total_amount');

            $dates[] = $dateStr;
            $labels[] = $date->format('d M');
            $revenue[] = round($dayRevenue, 2);
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'dates' => $dates,
        ];
    }

    /**
     * Daily expense points for charting (client can roll up to week/month).
     *
     * @return array{labels: list<string>, expenses: list<float>, dates: list<string>}
     */
    public static function dailyExpensesSeries(int $branchId, string $startDate, string $endDate): array
    {
        $labels = [];
        $expenses = [];
        $dates = [];

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();

            $expensesFromTable = (float) Expense::where('branch_id', $branchId)
                ->whereDate('expense_date', $dateStr)
                ->sum('amount');

            $expensesFromTransactions = (float) Transaction::where('branch_id', $branchId)
                ->whereDate('date', $dateStr)
                ->where('type', 'out')
                ->where(function ($query) {
                    $query->where('reference_type', 'expense')
                        ->orWhereNull('reference_type')
                        ->orWhere('reference_type', '');
                })
                ->sum('amount');

            $dates[] = $dateStr;
            $labels[] = $date->format('d M');
            $expenses[] = round($expensesFromTable + $expensesFromTransactions, 2);
        }

        return [
            'labels' => $labels,
            'expenses' => $expenses,
            'dates' => $dates,
        ];
    }

    /**
     * Order counts by type for the selected period (pie chart).
     *
     * @return array{
     *     labels: list<string>,
     *     counts: list<int>,
     *     types: list<string>,
     *     total: int
     * }
     */
    public static function orderTypeBreakdown(User $user, int $branchId, string $startDate, string $endDate): array
    {
        $countsByType = self::ordersQuery($user, $branchId, $startDate, $endDate)
            ->select('type', DB::raw('COUNT(*) as order_count'))
            ->groupBy('type')
            ->pluck('order_count', 'type');

        $typeMap = [
            'dine_in' => 'Dine In',
            'takeaway' => 'Takeaway',
            'delivery' => 'Delivery',
        ];

        $labels = [];
        $counts = [];
        $types = [];
        $total = 0;

        foreach ($typeMap as $type => $label) {
            $count = (int) ($countsByType[$type] ?? 0);
            $labels[] = $label;
            $counts[] = $count;
            $types[] = $type;
            $total += $count;
        }

        return [
            'labels' => $labels,
            'counts' => $counts,
            'types' => $types,
            'total' => $total,
        ];
    }

    /**
     * Operational cash flow comparison for the selected period.
     *
     * Category bars show operational volume (purchases / sales) and cash movement
     * (supplier payments, customer collections, expenses). Inflow/outflow totals use
     * cash movement only so paying a credit purchase is not counted twice.
     *
     * @return array{
     *     labels: list<string>,
     *     values: list<float>,
     *     keys: list<string>,
     *     cash_inflow: float,
     *     cash_outflow: float,
     *     net_flow: float
     * }
     */
    public static function operationalComparison(User $user, int $branchId, string $startDate, string $endDate): array
    {
        $purchases = round((float) Purchase::query()
            ->where('branch_id', $branchId)
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->sum('total_amount'), 2);

        $sales = round((float) self::ordersQuery($user, $branchId, $startDate, $endDate)->sum('total_amount'), 2);

        $expenses = self::expensesTotalForRange($branchId, $startDate, $endDate);

        $supplierPayments = round((float) SupplierPayment::query()
            ->where('branch_id', $branchId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('total_amount'), 2);

        $customerReceived = round((float) CustomerPayment::query()
            ->where('branch_id', $branchId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount'), 2);

        $cashFromSales = round((float) Transaction::query()
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', 'in')
            ->where('reference_type', 'sale')
            ->sum('amount'), 2);

        $cashInflow = round($cashFromSales + $customerReceived, 2);
        $cashOutflow = round($supplierPayments + $expenses, 2);

        return [
            'labels' => ['Purchases', 'Sales', 'Expenses', 'Supplier Payments', 'Customer Received'],
            'values' => [$purchases, $sales, $expenses, $supplierPayments, $customerReceived],
            'keys' => ['purchases', 'sales', 'expenses', 'supplier_payments', 'customer_received'],
            'cash_inflow' => $cashInflow,
            'cash_outflow' => $cashOutflow,
            'net_flow' => round($cashInflow - $cashOutflow, 2),
        ];
    }

    private static function expensesTotalForRange(int $branchId, string $startDate, string $endDate): float
    {
        $fromTable = (float) Expense::query()
            ->where('branch_id', $branchId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        $fromTransactions = (float) Transaction::query()
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', 'out')
            ->where(function ($query) {
                $query->where('reference_type', 'expense')
                    ->orWhereNull('reference_type')
                    ->orWhere('reference_type', '');
            })
            ->sum('amount');

        return round($fromTable + $fromTransactions, 2);
    }

    /**
     * Dashboard-only net profit:
     * total sale − COGS − expenses − payouts (money outs not from flagged sources).
     *
     * Internal transfers (reference_type = transfer) are excluded — they only move
     * cash between money sources. Flagged sources (exclude_from_dashboard_profit)
     * are ignored entirely. P&L report is unchanged.
     *
     * @return array{
     *     total_sale: float,
     *     cogs: float,
     *     expenses_total: float,
     *     expenses: list<array{date: string, label: string, detail: string, amount: float}>,
     *     payouts_total: float,
     *     payouts: list<array{date: string, label: string, account: string, detail: string, amount: float}>,
     *     payout_groups: list<array{label: string, total: float, rows: list<array{date: string, label: string, account: string, detail: string, amount: float}>}>,
     *     net_profit: float
     * }
     */
    private static function dashboardNetProfitBreakdown(
        int $branchId,
        string $startDate,
        string $endDate,
        float $netSales,
        float $cogs,
    ): array {
        $flaggedIds = MoneySource::query()
            ->where('exclude_from_dashboard_profit', true)
            ->pluck('id');

        $expenseRows = Expense::query()
            ->where('branch_id', $branchId)
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get(['id', 'category', 'description', 'amount', 'expense_date', 'notes']);

        $expenses = $expenseRows->map(function (Expense $expense) {
            $category = trim((string) ($expense->category ?? ''));
            $description = trim((string) ($expense->description ?? ''));
            $notes = trim((string) ($expense->notes ?? ''));
            $label = $description !== '' ? $description : ($category !== '' ? $category : 'Expense');
            $detailParts = array_values(array_filter([
                $category !== '' && $category !== $label ? $category : null,
                $notes !== '' ? $notes : null,
            ]));

            return [
                'date' => $expense->expense_date?->format('Y-m-d') ?? $expense->expense_date,
                'label' => $label,
                'detail' => implode(' · ', $detailParts),
                'amount' => round((float) $expense->amount, 2),
            ];
        })->all();

        $expensesTotal = round((float) $expenseRows->sum('amount'), 2);

        $outsQuery = Transaction::query()
            ->with(['moneySource:id,name', 'account:id,name'])
            ->where('branch_id', $branchId)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->where('type', 'out')
            ->where(function ($query) {
                $query->whereNull('reference_type')
                    ->orWhere('reference_type', '!=', 'transfer');
            });

        if ($flaggedIds->isNotEmpty()) {
            $outsQuery->where(function ($query) use ($flaggedIds) {
                $query->whereNull('money_source_id')
                    ->orWhereNotIn('money_source_id', $flaggedIds);
            });
        }

        $outRows = $outsQuery
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $payouts = $outRows->map(function (Transaction $txn) {
            $ref = (string) ($txn->reference_type ?? '');
            $label = self::payoutReferenceLabel($ref);
            $source = trim((string) ($txn->moneySource?->name ?? ''));
            $account = trim((string) ($txn->account?->name ?? ''));
            $notes = trim((string) ($txn->notes ?? ''));
            $detailParts = array_values(array_filter([
                $source !== '' ? $source : null,
                $notes !== '' ? $notes : null,
            ]));

            return [
                'date' => $txn->date?->format('Y-m-d') ?? (string) $txn->date,
                'label' => $label,
                'account' => $account !== '' ? $account : 'Unassigned',
                'detail' => implode(' · ', $detailParts),
                'amount' => round((float) $txn->amount, 2),
            ];
        })->all();

        $payoutGroups = collect($payouts)
            ->groupBy('account')
            ->map(function ($rows, string $account) {
                return [
                    'label' => $account,
                    'total' => round((float) $rows->sum('amount'), 2),
                    'rows' => $rows->values()->all(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $payoutsTotal = round((float) $outRows->sum('amount'), 2);
        $totalSale = round($netSales, 2);
        $cogsRounded = round($cogs, 2);

        return [
            'total_sale' => $totalSale,
            'cogs' => $cogsRounded,
            'expenses_total' => $expensesTotal,
            'expenses' => $expenses,
            'payouts_total' => $payoutsTotal,
            'payouts' => $payouts,
            'payout_groups' => $payoutGroups,
            'net_profit' => round($totalSale - $cogsRounded - $expensesTotal - $payoutsTotal, 2),
        ];
    }

    private static function payoutReferenceLabel(string $referenceType): string
    {
        return match ($referenceType) {
            'sale' => 'Sale',
            'purchase' => 'Purchase',
            'refund' => 'Refund',
            'expense' => 'Expense',
            'customer_payment' => 'Customer payment',
            'employee_payment' => 'Employee payment',
            'transfer' => 'Transfer',
            'reconciliation' => 'Reconciliation',
            'adjustment' => 'Adjustment',
            '' => 'Payout',
            default => ucwords(str_replace('_', ' ', $referenceType)),
        };
    }

    /**
     * @return array{
     *     items: \Illuminate\Support\Collection,
     *     label: string,
     *     total_quantity: float,
     *     total_revenue: float
     * }
     */
    public static function topFoodItems(User $user, int $branchId, string $startDate, string $endDate, int $limit = 10): array
    {
        $items = TopSellingItemsReport::aggregate(
            OrderItem::query()
                ->whereHas('order', function (Builder $query) use ($branchId, $startDate, $endDate) {
                    $query->where('branch_id', $branchId)
                        ->where('status', 'completed');
                    tz()->applyBusinessDateRange($query, $startDate, $endDate, $branchId);
                }),
            $limit
        );

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $label = $start->isSameDay($end)
            ? $start->format('M j, Y')
            : $start->format('M j, Y').' – '.$end->format('M j, Y');

        return [
            'items' => $items,
            'label' => $label,
            'total_quantity' => round((float) $items->sum('total_quantity'), 2),
            'total_revenue' => round((float) $items->sum('total_revenue'), 2),
        ];
    }

    /**
     * @return array{
     *     rows: \Illuminate\Support\Collection<int, array{name: string, current: float, min_level: float, unit: string}>,
     *     total: int
     * }
     */
    public static function lowStockItems(int $branchId): array
    {
        $ingredientRows = BranchStock::query()
            ->with(['ingredient.consumptionUnit'])
            ->where('branch_id', $branchId)
            ->get()
            ->filter(fn (BranchStock $stock) => $stock->isLowStock())
            ->map(function (BranchStock $stock) {
                $ingredient = $stock->ingredient;

                return [
                    'kind' => 'ingredient',
                    'name' => $ingredient?->name ?? 'Unknown ingredient',
                    'current' => round((float) $stock->available_quantity, 2),
                    'min_level' => round((float) ($ingredient?->min_stock_level ?? 0), 2),
                    'unit' => $ingredient?->unit_name ?? $ingredient?->base_unit_id ?? 'units',
                ];
            });

        $menuItemRows = MenuItemStock::query()
            ->with('menuItem')
            ->where('branch_id', $branchId)
            ->get()
            ->groupBy('menu_item_id')
            ->map(function ($stocks) use ($branchId) {
                $menuItem = $stocks->first()?->menuItem;
                if (! $menuItem || ! $menuItem->isLowStockAtBranch($branchId)) {
                    return null;
                }

                return [
                    'kind' => 'menu_item',
                    'name' => $menuItem->name,
                    'current' => round($menuItem->totalStockAtBranch($branchId), 2),
                    'min_level' => round((float) $menuItem->min_stock_level, 2),
                    'unit' => 'pcs',
                ];
            })
            ->filter()
            ->values();

        $rows = $ingredientRows->concat($menuItemRows)->sortBy('current')->values();

        return [
            'rows' => $rows,
            'total' => $rows->count(),
        ];
    }

    /**
     * Checked-out sales only. Open/unpaid POS tabs must not inflate dashboard revenue.
     */
    private static function ordersQuery(User $user, int $branchId, string $startDate, string $endDate): Builder
    {
        $query = Order::query()
            ->where('branch_id', $branchId)
            ->where('status', 'completed');

        tz()->applyBusinessDateRange($query, $startDate, $endDate, $branchId);

        return $query;
    }

    /**
     * Recipe / item cost for completed orders in the summary period.
     *
     * @param  \Illuminate\Support\Collection<int, int|string>  $orderIds
     */
    private static function costOfGoodsForOrders($orderIds): float
    {
        if ($orderIds->isEmpty()) {
            return 0.0;
        }

        $items = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->with([
                'menuItem.defaultRecipe.items.ingredient',
                'menuItem.variantRecipes.recipe.items.ingredient',
                'menuItem.legacyRecipeLines.ingredient',
                'deal.menuItems.defaultRecipe.items.ingredient',
                'deal.menuItems.variantRecipes.recipe.items.ingredient',
                'deal.menuItems.legacyRecipeLines.ingredient',
            ])
            ->get();

        $total = 0.0;
        foreach ($items as $item) {
            $unitCost = ProfitLossReport::unitCostForOrderItem($item);
            $total += (float) $item->quantity * $unitCost;
        }

        return round($total, 2);
    }

    private static function countUniqueCustomers(Builder $ordersQuery): int
    {
        $row = (clone $ordersQuery)
            ->selectRaw("COUNT(DISTINCT CASE
                WHEN customer_id IS NOT NULL THEN CONCAT('c', customer_id)
                WHEN COALESCE(customer_phone, '') != '' THEN customer_phone
                ELSE CONCAT('o', id)
            END) as customer_count")
            ->first();

        return (int) ($row->customer_count ?? 0);
    }
}
