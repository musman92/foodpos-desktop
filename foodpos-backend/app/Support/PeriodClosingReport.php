<?php

namespace App\Support;

use App\Models\BranchStock;
use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\MenuItemStock;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PeriodClosingReport
{
    public const MAX_WEEKS = 4;

    public const WEEKDAY_OPTIONS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public static function weekStartsOn(?User $user = null): string
    {
        $user ??= auth()->user();
        $settings = $user?->company?->settings ?? [];
        $value = $settings['week_starts_on'] ?? 'monday';

        return in_array($value, self::WEEKDAY_OPTIONS, true) ? $value : 'monday';
    }

    /**
     * @return list<array{from: string, to: string, label: string, mode: string}>
     */
    public static function resolveWeeklyPeriods(string $weekOf, int $weekCount, ?int $branchId, ?User $user = null): array
    {
        $weekCount = max(1, min(self::MAX_WEEKS, $weekCount));
        $weekStartsOn = self::weekStartsOn($user);
        $tz = tz()->resolveForBranch($branchId);
        $anchor = self::snapToWeekStart(Carbon::parse($weekOf, $tz), $weekStartsOn);

        $periods = [];
        for ($i = 0; $i < $weekCount; $i++) {
            $start = $anchor->copy()->addWeeks($i);
            $end = $start->copy()->addDays(6);
            $periods[] = [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'label' => 'Week of '.format_date($start->toDateString()),
                'mode' => 'week',
            ];
        }

        return $periods;
    }

    /**
     * @return list<array{from: string, to: string, label: string, mode: string}>
     */
    public static function resolveMonthlyPeriod(string $month, ?int $branchId): array
    {
        $tz = tz()->resolveForBranch($branchId);
        $start = Carbon::parse($month.'-01', $tz);
        $end = $start->copy()->endOfMonth();

        return [[
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'label' => $start->format('F Y'),
            'mode' => 'month',
        ]];
    }

    public static function defaultWeekOf(?int $branchId, ?User $user = null): string
    {
        $tz = tz()->resolveForBranch($branchId);

        return self::snapToWeekStart(Carbon::now($tz), self::weekStartsOn($user))->toDateString();
    }

    public static function snapToWeekStart(Carbon $date, string $weekStartsOn): Carbon
    {
        $map = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];
        $target = $map[$weekStartsOn] ?? 1;
        $diff = ($date->dayOfWeek - $target + 7) % 7;

        return $date->copy()->subDays($diff)->startOfDay();
    }

    /**
     * @param  list<array{from: string, to: string, label: string, mode: string}>  $periods
     * @return array{
     *     periods: list<array>,
     *     grand_closing: array,
     *     payment_columns: list<array{key: string, label: string}>,
     *     week_starts_on: string
     * }
     */
    public static function build(User $user, ?int $branchId, array $periods): array
    {
        $paymentColumns = self::paymentColumns($user, $branchId);
        // Current inventory once — same for every week, so only the first section shows the table.
        $stockLines = self::stockLines($user, $branchId);
        $stockTotal = round((float) collect($stockLines)->sum('amount'), 2);
        $sections = [];

        foreach ($periods as $index => $period) {
            $isFirst = $index === 0;
            $isLast = $index === count($periods) - 1;
            $sections[] = self::buildSection(
                $user,
                $branchId,
                $period,
                $paymentColumns,
                stockLines: $isFirst ? $stockLines : [],
                stockTotal: $stockTotal,
                showStock: $isFirst,
                includeStockInClosing: $isLast,
            );
        }

        return [
            'periods' => $sections,
            'grand_closing' => self::aggregateClosing($sections),
            'payment_columns' => $paymentColumns,
            'week_starts_on' => self::weekStartsOn($user),
        ];
    }

    /**
     * @param  list<array{key: string, label: string}>  $paymentColumns
     * @param  list<array<string, mixed>>  $stockLines
     * @return array<string, mixed>
     */
    protected static function buildSection(
        User $user,
        ?int $branchId,
        array $period,
        array $paymentColumns,
        array $stockLines,
        float $stockTotal,
        bool $showStock,
        bool $includeStockInClosing
    ): array {
        $from = $period['from'];
        $to = $period['to'];

        $dailySales = self::dailySales($user, $branchId, $from, $to, $paymentColumns);
        $totalSale = round((float) collect($dailySales)->sum('total_sale'), 2);
        $cogsTotal = ProfitLossReport::cogsForPeriod($user, $branchId, $from, $to);
        $expenseTotal = self::expenseTotal($user, $branchId, $from, $to);
        $cogsAndExpense = round($cogsTotal + $expenseTotal, 2);
        $pnl = round($totalSale - $cogsAndExpense, 2);
        // Match the Available stock table (available qty, not reserved).
        $stockInHand = $includeStockInClosing ? $stockTotal : null;
        $closingAmount = $stockInHand !== null ? round($stockInHand + $pnl, 2) : null;

        return [
            'from' => $from,
            'to' => $to,
            'label' => $period['label'],
            'mode' => $period['mode'],
            'show_stock' => $showStock,
            'stock' => $stockLines,
            'stock_total' => $showStock ? $stockTotal : 0.0,
            'daily_sales' => $dailySales,
            'closing' => [
                'total_sale' => $totalSale,
                'cogs_total' => $cogsTotal,
                'purchase_total' => $cogsTotal, // legacy alias for exports/views
                'expense_total' => $expenseTotal,
                'purchase_and_expense' => $cogsAndExpense,
                'pnl' => $pnl,
                'stock_in_hand' => $stockInHand,
                'closing_amount' => $closingAmount,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, float|null>
     */
    protected static function aggregateClosing(array $sections): array
    {
        if ($sections === []) {
            return [
                'total_sale' => 0.0,
                'cogs_total' => 0.0,
                'purchase_total' => 0.0,
                'expense_total' => 0.0,
                'purchase_and_expense' => 0.0,
                'pnl' => 0.0,
                'stock_in_hand' => null,
                'closing_amount' => null,
            ];
        }

        $totalSale = round((float) collect($sections)->sum(fn ($s) => $s['closing']['total_sale']), 2);
        $cogsTotal = round((float) collect($sections)->sum(fn ($s) => $s['closing']['cogs_total'] ?? $s['closing']['purchase_total']), 2);
        $expenseTotal = round((float) collect($sections)->sum(fn ($s) => $s['closing']['expense_total']), 2);
        $cogsAndExpense = round($cogsTotal + $expenseTotal, 2);
        $pnl = round($totalSale - $cogsAndExpense, 2);
        $lastStock = collect($sections)->last()['closing']['stock_in_hand'] ?? null;
        $closingAmount = $lastStock !== null ? round((float) $lastStock + $pnl, 2) : null;

        return [
            'total_sale' => $totalSale,
            'cogs_total' => $cogsTotal,
            'purchase_total' => $cogsTotal,
            'expense_total' => $expenseTotal,
            'purchase_and_expense' => $cogsAndExpense,
            'pnl' => $pnl,
            'stock_in_hand' => $lastStock,
            'closing_amount' => $closingAmount,
        ];
    }

    /**
     * @return list<array{key: string, label: string, type: string}>
     */
    public static function paymentColumns(User $user, ?int $branchId): array
    {
        $query = MoneySource::query()
            ->where('active', true)
            ->where('exclude_from_dashboard_profit', false)
            ->orderBy('name');

        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->whereHas('branches', fn (Builder $branchQuery) => $branchQuery->where('branches.id', $branchId));
            }
        } elseif ($user->company_id) {
            $query->where('company_id', $user->company_id);
            if ($branchId) {
                $query->whereHas('branches', fn (Builder $branchQuery) => $branchQuery->where('branches.id', $branchId));
            }
        }

        $columns = $query->get(['id', 'name', 'type'])->map(fn (MoneySource $source) => [
            'key' => 'ms_'.$source->id,
            'label' => $source->name,
            'type' => strtoupper((string) $source->type),
        ])->values()->all();

        array_unshift($columns, ['key' => 'credit', 'label' => 'Credit Sale', 'type' => 'CREDIT']);

        return $columns;
    }

    /**
     * Current available stock (ingredients + tracked menu items), same column shape as the old purchases table.
     *
     * @return list<array{sno: int, product: string, rate: float, qty: float, amount: float}>
     */
    protected static function stockLines(User $user, ?int $branchId): array
    {
        $grouped = [];

        $ingredientQuery = BranchStock::withoutBranchScope()
            ->with([
                'ingredient' => fn ($q) => $q->withoutGlobalScopes()->with('purchaseUnit'),
            ])
            ->where('quantity', '>', 0);
        self::applyBranchOnlyScope($ingredientQuery, $user, $branchId);

        foreach ($ingredientQuery->get() as $stock) {
            /** @var Ingredient|null $ingredient */
            $ingredient = $stock->ingredient;
            if (! $ingredient) {
                continue;
            }

            $availableConsumption = max(0.0, (float) $stock->available_quantity);
            if ($availableConsumption <= 0) {
                continue;
            }

            $key = 'ingredient|'.$ingredient->id;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'product' => (string) $ingredient->name,
                    'qty_consumption' => 0.0,
                    'amount' => 0.0,
                    'ingredient' => $ingredient,
                ];
            }

            $avgCost = (float) $stock->average_cost;
            $grouped[$key]['qty_consumption'] += $availableConsumption;
            $grouped[$key]['amount'] += $availableConsumption * $avgCost;
        }

        $menuQuery = MenuItemStock::withoutBranchScope()
            ->with(['menuItem' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('quantity', '>', 0);
        self::applyBranchOnlyScope($menuQuery, $user, $branchId);

        foreach ($menuQuery->get() as $stock) {
            /** @var MenuItem|null $menuItem */
            $menuItem = $stock->menuItem;
            if (! $menuItem) {
                continue;
            }

            $qty = max(0.0, (float) $stock->quantity);
            if ($qty <= 0) {
                continue;
            }

            $key = 'menu_item|'.$menuItem->id;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'product' => (string) $menuItem->name,
                    'qty_consumption' => 0.0,
                    'amount' => 0.0,
                    'ingredient' => null,
                ];
            }

            $unitPrice = (float) $stock->unit_price;
            $grouped[$key]['qty_consumption'] += $qty;
            $grouped[$key]['amount'] += $qty * $unitPrice;
        }

        $lines = [];
        foreach ($grouped as $row) {
            $amount = round((float) $row['amount'], 2);
            $qtyConsumption = (float) $row['qty_consumption'];

            /** @var Ingredient|null $ingredient */
            $ingredient = $row['ingredient'] ?? null;
            $qty = $ingredient instanceof Ingredient
                ? round($ingredient->toPurchaseQuantity($qtyConsumption), 2)
                : round($qtyConsumption, 2);
            $rate = $qty > 0 ? round($amount / $qty, 2) : 0.0;

            if ($qty <= 0 && $amount <= 0) {
                continue;
            }

            $lines[] = [
                'sno' => 0,
                'product' => $row['product'],
                'rate' => $rate,
                'qty' => $qty,
                'amount' => $amount,
            ];
        }

        usort($lines, static fn ($a, $b) => strcmp($a['product'], $b['product']));
        foreach ($lines as $index => &$line) {
            $line['sno'] = $index + 1;
        }

        return $lines;
    }

    /**
     * @param  list<array{key: string, label: string}>  $paymentColumns
     * @return list<array<string, mixed>>
     */
    protected static function dailySales(
        User $user,
        ?int $branchId,
        string $from,
        string $to,
        array $paymentColumns
    ): array {
        $days = self::dateRangeDays($from, $to, $branchId);
        $rows = [];

        foreach ($days as $date) {
            $rows[] = self::dailySalesForDate($user, $branchId, $date, $paymentColumns);
        }

        return $rows;
    }

    /**
     * @param  list<array{key: string, label: string}>  $paymentColumns
     * @return array<string, mixed>
     */
    protected static function dailySalesForDate(
        User $user,
        ?int $branchId,
        string $date,
        array $paymentColumns
    ): array {
        $ordersQuery = Order::query()
            ->where('status', 'completed')
            ->with(['moneySource:id,name', 'payments']);
        tz()->applyBusinessDateRange($ordersQuery, $date, $date, $branchId);
        self::applyBranchScope($ordersQuery, $user, $branchId);

        $orders = $ordersQuery->get([
            'id',
            'branch_id',
            'created_at',
            'business_date',
            'total_amount',
            'payment_method',
            'money_source_id',
            'paid_at_sale',
            'paid_amount',
        ]);

        $columnKeys = collect($paymentColumns)->pluck('key')->all();

        $bucket = [
            'total_sale' => 0.0,
            'payments' => [],
            'cash_receivable' => 0.0,
            'excluded_sale' => 0.0,
        ];

        foreach ($orders as $order) {
            // When viewing all branches, confirm the order's own branch agrees on the business day.
            if ($branchId === null) {
                $orderBusinessDate = filled($order->business_date)
                    ? substr((string) $order->business_date, 0, 10)
                    : tz()->toLocal($order->created_at, $order->branch_id)->toDateString();
                if ($orderBusinessDate !== $date) {
                    continue;
                }
            }

            $amount = (float) $order->total_amount;
            $bucket['total_sale'] += $amount;

            $paidAtSale = round(min(
                (float) ($order->paid_at_sale ?? $order->paid_amount ?? 0),
                $amount
            ), 2);

            if ($order->payment_method === 'credit') {
                // Full credit order on Credit Sale so:
                // Total − Credit + Cash receivable keeps only cash taken / collected.
                $bucket['payments']['credit'] = ($bucket['payments']['credit'] ?? 0) + $amount;
                if ($paidAtSale > 0) {
                    $bucket['cash_receivable'] += $paidAtSale;
                }

                continue;
            }

            if ($order->payment_method === 'foc') {
                $bucket['payments']['foc'] = ($bucket['payments']['foc'] ?? 0) + $amount;

                continue;
            }

            if ($order->payment_method === 'split') {
                self::bucketSplitOrderPayments($order, $amount, $columnKeys, $bucket);

                continue;
            }

            $paymentKey = $order->money_source_id ? 'ms_'.$order->money_source_id : 'other';

            // Flagged / inactive sources: still in total sale, deducted from cash-in-hand formula.
            if ($paymentKey !== 'other' && ! in_array($paymentKey, $columnKeys, true)) {
                $bucket['excluded_sale'] += $amount;

                continue;
            }

            if (! isset($bucket['payments'][$paymentKey])) {
                $bucket['payments'][$paymentKey] = 0.0;
            }
            $bucket['payments'][$paymentKey] += $amount;
        }

        // Later AR collections: Cash / Total receivable only (not mixed into payment lines).
        // total_sale / closing PnL stay sale-based; collections are not added to total_sale.
        $customerCollectionsTotal = self::customerPaymentCollectionsTotal($user, $branchId, $date);
        $customerCollectionsCash = self::customerPaymentCollectionsTotal($user, $branchId, $date, cashOnly: true);

        $payments = [];
        $totalCollected = 0.0;
        $nonCashPayments = 0.0;

        foreach ($paymentColumns as $column) {
            $amount = round((float) ($bucket['payments'][$column['key']] ?? 0), 2);
            $totalCollected += $amount;

            // CASH till sales stay off the payment list; they remain in cash-in-hand via the formula.
            if (($column['type'] ?? '') === 'CASH') {
                continue;
            }

            if ($amount <= 0) {
                continue;
            }

            $nonCashPayments += $amount;
            $payments[] = [
                'key' => $column['key'],
                'label' => $column['label'],
                'amount' => $amount,
            ];
        }

        foreach ([
            'foc' => 'FOC',
            'split_payment' => 'Split payment',
            'other' => 'Other',
        ] as $extraKey => $extraLabel) {
            if (empty($bucket['payments'][$extraKey])) {
                continue;
            }

            $extraAmount = round((float) $bucket['payments'][$extraKey], 2);
            if ($extraAmount <= 0) {
                continue;
            }

            $payments[] = [
                'key' => $extraKey,
                'label' => $extraLabel,
                'amount' => $extraAmount,
            ];
            $totalCollected += $extraAmount;
            $nonCashPayments += $extraAmount;
        }

        $atSaleCashReceivable = round((float) $bucket['cash_receivable'], 2);
        $totalCollected += $atSaleCashReceivable;
        // Cash receivable = at-sale partials + later collections received into CASH sources only.
        $cashReceivable = round($atSaleCashReceivable + $customerCollectionsCash, 2);
        // Total receivable = at-sale partials + all non-excluded collections (info only; no calc impact).
        $totalReceivable = round($atSaleCashReceivable + $customerCollectionsTotal, 2);

        // Daily card expenses: only outs paid from CASH money sources.
        $expenseBreakdown = self::expenseBreakdown($user, $branchId, $date, $date, cashOnly: true);
        $excludedSale = round((float) $bucket['excluded_sale'], 2);
        $totalSale = round((float) $bucket['total_sale'], 2);

        // Cash in hand = Total − Credit − Bank − Jazz − FOC − … − excluded + Cash receivable − cash expenses.
        $cashInHand = round(
            $totalSale - $nonCashPayments - $excludedSale + $cashReceivable - (float) $expenseBreakdown['total'],
            2
        );

        return [
            'date' => $date,
            'label' => Carbon::parse($date, tz()->resolveForBranch($branchId))->format('l'),
            'total_sale' => $totalSale,
            'payments' => $payments,
            'total_collected' => round($totalCollected, 2),
            'cash_in_hand' => $cashInHand,
            'cash_receivable' => $cashReceivable,
            'total_receivable' => $totalReceivable,
            'expense_total' => $expenseBreakdown['total'],
            'expense_lines' => $expenseBreakdown['lines'],
        ];
    }

    /**
     * Customer payment inflows for a day (excludes flagged money sources).
     * When $cashOnly is true, only CASH money sources are included.
     */
    protected static function customerPaymentCollectionsTotal(
        User $user,
        ?int $branchId,
        string $date,
        bool $cashOnly = false
    ): float {
        $query = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('type', 'in')
            ->where('reference_type', 'customer_payment')
            ->whereDate('date', '>=', $date)
            ->whereDate('date', '<=', $date);

        if ($cashOnly) {
            $query->whereHas('moneySource', function (Builder $sourceQuery) {
                $sourceQuery->where('type', 'CASH')
                    ->where(function (Builder $inner) {
                        $inner->where('exclude_from_dashboard_profit', false)
                            ->orWhereNull('exclude_from_dashboard_profit');
                    });
            });
        } else {
            $query->where(function (Builder $query) {
                $query->whereNull('money_source_id')
                    ->orWhereDoesntHave('moneySource', function (Builder $sourceQuery) {
                        $sourceQuery->where('exclude_from_dashboard_profit', true);
                    });
            });
        }

        self::applyBranchScope($query, $user, $branchId);

        return round((float) $query->sum('amount'), 2);
    }

    /**
     * Allocate split order lines to money-source columns; leftover stays under Split payment.
     *
     * @param  list<string>  $columnKeys
     * @param  array{total_sale: float, payments: array<string, float>, cash_receivable: float}  $bucket
     */
    protected static function bucketSplitOrderPayments(Order $order, float $orderTotal, array $columnKeys, array &$bucket): void
    {
        $lines = $order->relationLoaded('payments')
            ? $order->payments
            : $order->payments()->get();

        $allocated = 0.0;

        foreach ($lines as $line) {
            $lineAmount = round((float) $line->amount, 2);
            if ($lineAmount <= 0) {
                continue;
            }

            if (! $line->money_source_id) {
                $bucket['payments']['split_payment'] = ($bucket['payments']['split_payment'] ?? 0) + $lineAmount;
                $allocated = round($allocated + $lineAmount, 2);

                continue;
            }

            $paymentKey = 'ms_'.$line->money_source_id;

            // Flagged / inactive sources are omitted from columns — deduct from cash-in-hand formula.
            if (! in_array($paymentKey, $columnKeys, true)) {
                $bucket['excluded_sale'] = ($bucket['excluded_sale'] ?? 0) + $lineAmount;
                $allocated = round($allocated + $lineAmount, 2);

                continue;
            }

            $bucket['payments'][$paymentKey] = ($bucket['payments'][$paymentKey] ?? 0) + $lineAmount;
            $allocated = round($allocated + $lineAmount, 2);
        }

        $remainder = round($orderTotal - $allocated, 2);
        if ($remainder > 0.009) {
            $bucket['payments']['split_payment'] = ($bucket['payments']['split_payment'] ?? 0) + $remainder;
        }
    }

    /**
     * @return list<string>
     */
    protected static function dateRangeDays(string $from, string $to, ?int $branchId): array
    {
        $tz = tz()->resolveForBranch($branchId);
        $cursor = Carbon::parse($from, $tz)->startOfDay();
        $end = Carbon::parse($to, $tz)->startOfDay();
        $days = [];

        while ($cursor->lte($end)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $days;
    }

    protected static function expenseTotal(User $user, ?int $branchId, string $from, string $to): float
    {
        return self::expenseBreakdown($user, $branchId, $from, $to)['total'];
    }

    /**
     * @return array{total: float, lines: list<array{label: string, detail: ?string, amount: float, source: string}>}
     */
    protected static function expenseBreakdown(User $user, ?int $branchId, string $from, string $to, bool $cashOnly = false): array
    {
        $lines = [];

        // Expense rows have no money source — only include them when not filtering to cash till.
        if (! $cashOnly) {
            $expensesQuery = Expense::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->whereDate('expense_date', '>=', $from)
                ->whereDate('expense_date', '<=', $to)
                ->orderBy('expense_date')
                ->orderBy('id');
            self::applyBranchScope($expensesQuery, $user, $branchId);

            foreach ($expensesQuery->get(['id', 'category', 'description', 'notes', 'amount', 'expense_date']) as $expense) {
                $label = trim((string) ($expense->category ?: 'Expense'));
                $detail = trim(implode(' · ', array_filter([
                    $expense->description ? (string) $expense->description : null,
                    $expense->notes ? (string) $expense->notes : null,
                ])));

                $lines[] = [
                    'label' => $label !== '' ? $label : 'Expense',
                    'detail' => $detail !== '' ? $detail : null,
                    'amount' => round((float) $expense->amount, 2),
                    'source' => 'expense',
                ];
            }
        }

        $transactionsQuery = Transaction::withoutGlobalScopes()
            ->with('account:id,name')
            ->whereNull('deleted_at')
            ->where('type', 'out')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->where(function (Builder $query) {
                $query->where('reference_type', 'expense')
                    ->orWhereNull('reference_type')
                    ->orWhere('reference_type', '');
            })
            // Internal transfers only move cash between sources — never operating expense.
            ->where(function (Builder $query) {
                $query->whereNull('reference_type')
                    ->orWhere('reference_type', '!=', 'transfer');
            })
            // FOC complimentary sales post as expense transactions, but cost is already in COGS —
            // do not count them again as cash/operating expense in closing.
            ->where(function (Builder $query) {
                $query->whereNull('notes')
                    ->orWhere('notes', 'not like', 'FOC Order #%');
            })
            ->whereDoesntHave('account', function (Builder $query) {
                $query->where('name', 'FOC')->where('type', 'expense');
            });

        if ($cashOnly) {
            $transactionsQuery->whereHas('moneySource', function (Builder $sourceQuery) {
                $sourceQuery->where('type', 'CASH')
                    ->where(function (Builder $inner) {
                        $inner->where('exclude_from_dashboard_profit', false)
                            ->orWhereNull('exclude_from_dashboard_profit');
                    });
            });
        } else {
            // Same as dashboard net profit: ignore outs paid from flagged money sources.
            $transactionsQuery->where(function (Builder $query) {
                $query->whereNull('money_source_id')
                    ->orWhereDoesntHave('moneySource', function (Builder $sourceQuery) {
                        $sourceQuery->where('exclude_from_dashboard_profit', true);
                    });
            });
        }

        $transactionsQuery->orderBy('date')->orderBy('id');
        self::applyBranchScope($transactionsQuery, $user, $branchId);

        foreach ($transactionsQuery->get(['id', 'account_id', 'amount', 'notes', 'reference_type', 'date']) as $transaction) {
            $label = $transaction->account?->name ?: 'Other expenses';
            $detail = trim((string) ($transaction->notes ?? ''));

            $lines[] = [
                'label' => $label,
                'detail' => $detail !== '' ? $detail : null,
                'amount' => round((float) $transaction->amount, 2),
                'source' => 'transaction',
            ];
        }

        $total = round((float) collect($lines)->sum('amount'), 2);

        return [
            'total' => $total,
            'lines' => $lines,
        ];
    }

    protected static function stockInHandValue(User $user, ?int $branchId): float
    {
        return round((float) collect(self::stockLines($user, $branchId))->sum('amount'), 2);
    }

    protected static function applyBranchOnlyScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($branchId) {
            $query->where($query->getModel()->getTable().'.branch_id', $branchId);

            return;
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isCompanyAdmin() && $user->company_id) {
            $branchIds = \App\Models\Branch::where('company_id', $user->company_id)
                ->where('status', 'active')
                ->pluck('id')
                ->all();
            if (! empty($branchIds)) {
                $query->whereIn($query->getModel()->getTable().'.branch_id', $branchIds);
            }

            return;
        }

        $branchIds = $user->branches()->where('status', 'active')->pluck('branches.id')->toArray();
        if (! empty($branchIds)) {
            $query->whereIn($query->getModel()->getTable().'.branch_id', $branchIds);
        } elseif ($user->branch_id) {
            $query->where($query->getModel()->getTable().'.branch_id', $user->branch_id);
        }
    }

    protected static function applyBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        $table = $query->getModel()->getTable();

        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->where($table.'.branch_id', $branchId);
            }

            return;
        }

        if ($user->isCompanyAdmin() && $user->company_id) {
            $query->where($table.'.company_id', $user->company_id);
            if ($branchId) {
                $query->where($table.'.branch_id', $branchId);
            }

            return;
        }

        $query->where($table.'.company_id', $user->company_id);

        if ($branchId) {
            $query->where($table.'.branch_id', $branchId);
        } else {
            $branchIds = $user->branches()->where('status', 'active')->pluck('branches.id')->toArray();
            if (! empty($branchIds)) {
                $query->whereIn($table.'.branch_id', $branchIds);
            } elseif ($user->branch_id) {
                $query->where($table.'.branch_id', $user->branch_id);
            }
        }
    }
}
