<?php

namespace App\Support;

use App\Models\Deal;
use App\Models\Expense;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\OrderRefundLine;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProfitLossReport
{
  /**
   * @return array{
   *   period_label: string,
   *   period_start: string,
   *   period_end: string,
   *   revenue: array{
   *     gross_sales: float,
   *     discounts: float,
   *     refunds: float,
   *     net_sales: float,
   *     order_count: int
   *   },
   *   cogs: array{
   *     sold_cost: float,
   *     refund_cost: float,
   *     total: float,
   *     gross_profit: float,
   *     gross_margin_percent: ?float,
   *     lines_without_cost: int,
   *     lines_with_cost: int
   *   },
   *   operating_expenses: array{
   *     categories: Collection<int, array{label: string, amount: float}>,
   *     total: float
   *   },
   *   net_profit: float,
   *   net_margin_percent: ?float
   * }
   */
    public static function build(User $user, ?int $branchId, string $startDate, string $endDate): array
    {
    [$periodStart, $periodEnd] = tz()->localRangeToUtcRange($startDate, $endDate, $branchId);

    $ordersQuery = Order::query()
      ->where('status', 'completed');
    tz()->applyBusinessDateRange($ordersQuery, $startDate, $endDate, $branchId);
    self::applyBranchScope($ordersQuery, $user, $branchId);

    $grossSales = (float) (clone $ordersQuery)->sum('subtotal');
    $discounts = (float) (clone $ordersQuery)->sum('discount_amount');
    $orderCount = (clone $ordersQuery)->count();

    $refundsQuery = OrderRefund::query()
      ->whereBetween('created_at', [$periodStart, $periodEnd])
      ->whereHas('order', function (Builder $query) use ($user, $branchId) {
        $query->where('status', 'completed');
        self::applyBranchScope($query, $user, $branchId);
      });

    $refunds = (float) (clone $refundsQuery)->sum('subtotal_refund');
    $netSales = round($grossSales - $discounts - $refunds, 2);

    $cogs = self::cogsBreakdown($user, $branchId, $startDate, $endDate, $periodStart, $periodEnd);
    $soldCost = $cogs['sold_cost'];
    $refundCost = $cogs['refund_cost'];
    $totalCogs = $cogs['total'];
    $linesWithoutCost = $cogs['lines_without_cost'];
    $linesWithCost = $cogs['lines_with_cost'];
    $grossProfit = round($netSales - $totalCogs, 2);
    $grossMarginPercent = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 1) : null;

    $expenseCategories = self::operatingExpenseCategories($user, $branchId, $periodStart, $periodEnd);
    $operatingTotal = round((float) $expenseCategories->sum('amount'), 2);
    $netProfit = round($grossProfit - $operatingTotal, 2);
    $netMarginPercent = $netSales > 0 ? round(($netProfit / $netSales) * 100, 1) : null;

    return [
      'period_label' => $periodStart->isSameDay($periodEnd)
        ? $periodStart->format('M j, Y')
        : $periodStart->format('M j, Y').' – '.$periodEnd->format('M j, Y'),
      'period_start' => $periodStart->toDateString(),
      'period_end' => $periodEnd->toDateString(),
      'revenue' => [
        'gross_sales' => round($grossSales, 2),
        'discounts' => round($discounts, 2),
        'refunds' => round($refunds, 2),
        'net_sales' => $netSales,
        'order_count' => $orderCount,
      ],
      'cogs' => [
        'sold_cost' => $soldCost,
        'refund_cost' => $refundCost,
        'total' => $totalCogs,
        'gross_profit' => $grossProfit,
        'gross_margin_percent' => $grossMarginPercent,
        'lines_without_cost' => $linesWithoutCost,
        'lines_with_cost' => $linesWithCost,
      ],
      'operating_expenses' => [
        'categories' => $expenseCategories,
        'total' => $operatingTotal,
      ],
      'net_profit' => $netProfit,
      'net_margin_percent' => $netMarginPercent,
    ];
  }

  /**
   * Cost of goods sold for a business-date period (sold cost minus refund cost).
   */
  public static function cogsForPeriod(User $user, ?int $branchId, string $startDate, string $endDate): float
  {
    [$periodStart, $periodEnd] = tz()->localRangeToUtcRange($startDate, $endDate, $branchId);

    return self::cogsBreakdown($user, $branchId, $startDate, $endDate, $periodStart, $periodEnd)['total'];
  }

  /**
   * @return array{
   *   sold_cost: float,
   *   refund_cost: float,
   *   total: float,
   *   lines_without_cost: int,
   *   lines_with_cost: int
   * }
   */
  protected static function cogsBreakdown(
    User $user,
    ?int $branchId,
    string $startDate,
    string $endDate,
    Carbon $periodStart,
    Carbon $periodEnd
  ): array {
    $ordersQuery = Order::query()
      ->where('status', 'completed');
    tz()->applyBusinessDateRange($ordersQuery, $startDate, $endDate, $branchId);
    self::applyBranchScope($ordersQuery, $user, $branchId);

    $orderIds = (clone $ordersQuery)->pluck('id');

    $soldCost = 0.0;
    $linesWithoutCost = 0;
    $linesWithCost = 0;

    if ($orderIds->isNotEmpty()) {
      $orderItems = OrderItem::query()
        ->whereIn('order_id', $orderIds)
        ->with([
          'menuItem.recipes.ingredient',
          'deal.menuItems.recipes.ingredient',
        ])
        ->get();

      foreach ($orderItems as $item) {
        $unitCost = self::unitCostForOrderItem($item);
        $qty = (float) $item->quantity;

        if ($unitCost <= 0 && $qty > 0) {
          $linesWithoutCost++;
        } elseif ($qty > 0) {
          $linesWithCost++;
        }

        $soldCost += $qty * $unitCost;
      }
    }

    $refundCost = 0.0;
    $refundLines = OrderRefundLine::query()
      ->whereHas('orderRefund', function (Builder $query) use ($periodStart, $periodEnd) {
        $query->whereBetween('created_at', [$periodStart, $periodEnd]);
      })
      ->whereHas('orderRefund.order', function (Builder $query) use ($user, $branchId) {
        $query->where('status', 'completed');
        self::applyBranchScope($query, $user, $branchId);
      })
      ->with([
        'orderItem.menuItem.recipes.ingredient',
        'orderItem.deal.menuItems.recipes.ingredient',
      ])
      ->get();

    foreach ($refundLines as $line) {
      if (! $line->orderItem) {
        continue;
      }

      $unitCost = self::unitCostForOrderItem($line->orderItem);
      $refundCost += (float) $line->quantity * $unitCost;
    }

    $soldCost = round($soldCost, 2);
    $refundCost = round($refundCost, 2);

    return [
      'sold_cost' => $soldCost,
      'refund_cost' => $refundCost,
      'total' => round($soldCost - $refundCost, 2),
      'lines_without_cost' => $linesWithoutCost,
      'lines_with_cost' => $linesWithCost,
    ];
  }

  protected static function operatingExpenseCategories(
    User $user,
    ?int $branchId,
    Carbon $periodStart,
    Carbon $periodEnd
  ): Collection {
    $startDate = $periodStart->toDateString();
    $endDate = $periodEnd->toDateString();

    $expensesQuery = Expense::query()
      ->whereBetween('expense_date', [$startDate, $endDate]);
    self::applyBranchScope($expensesQuery, $user, $branchId);

    $fromExpenses = $expensesQuery
      ->selectRaw('category as label, SUM(amount) as amount')
      ->groupBy('category')
      ->get()
      ->map(fn ($row) => [
        'label' => (string) $row->label,
        'amount' => round((float) $row->amount, 2),
      ]);

    $transactionsQuery = Transaction::query()
      ->with('account')
      ->where('type', 'out')
      ->whereBetween('date', [$startDate, $endDate])
      ->where(function (Builder $query) {
        $query->where('reference_type', 'expense')
          ->orWhereNull('reference_type')
          ->orWhere('reference_type', '');
      });
    self::applyBranchScope($transactionsQuery, $user, $branchId);

    $fromTransactions = $transactionsQuery->get();

    $transactionRows = $fromTransactions
      ->groupBy(fn (Transaction $transaction) => $transaction->account?->name ?: 'Other expenses')
      ->map(fn (Collection $group, string $label) => [
        'label' => $label,
        'amount' => round((float) $group->sum('amount'), 2),
      ])
      ->values();

    return $fromExpenses
      ->concat($transactionRows)
      ->groupBy('label')
      ->map(fn (Collection $group, string $label) => [
        'label' => $label,
        'amount' => round((float) $group->sum('amount'), 2),
      ])
      ->values()
      ->sortByDesc('amount')
      ->values();
  }

  public static function unitCostForOrderItem(OrderItem $item): float
  {
    if ($item->deal_id && $item->relationLoaded('deal') && $item->deal) {
      return self::dealUnitCost($item->deal);
    }

    if ($item->menu_item_id && $item->relationLoaded('menuItem') && $item->menuItem) {
      return self::menuItemUnitCost($item->menuItem, $item->variants);
    }

    if ($item->deal_id) {
      $deal = Deal::with('menuItems.recipes.ingredient')->find($item->deal_id);

      return $deal ? self::dealUnitCost($deal) : 0.0;
    }

    if ($item->menu_item_id) {
      $menuItem = MenuItem::with('defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient', 'legacyRecipeLines.ingredient')->find($item->menu_item_id);

      return $menuItem ? self::menuItemUnitCost($menuItem, $item->variants) : 0.0;
    }

    return 0.0;
  }

  protected static function menuItemUnitCost(MenuItem $menuItem, ?array $variants): float
  {
    [$variantId, $optionName] = MenuItem::variantContextFromOrderSelection($variants);

    if ($menuItem->type === 'recipe') {
      return (float) $menuItem->resolveRecipes($variantId, $optionName)->sum(fn ($recipe) => $recipe->lineCost());
    }

    return (float) $menuItem->cost;
  }

  protected static function dealUnitCost(Deal $deal): float
  {
    if (! $deal->relationLoaded('menuItems')) {
      $deal->load('menuItems.recipes.ingredient');
    }

    $total = 0.0;

    foreach ($deal->menuItems as $menuItem) {
      $qty = (float) ($menuItem->pivot->quantity ?? 1);
      $variantId = $menuItem->pivot->variant_id ? (int) $menuItem->pivot->variant_id : null;
      $optionName = $menuItem->pivot->option_name ? (string) $menuItem->pivot->option_name : null;

      if ($menuItem->type === 'recipe') {
        $total += $menuItem->resolveRecipes($variantId, $optionName)->sum(fn ($recipe) => $recipe->lineCost()) * $qty;
      } else {
        $total += (float) $menuItem->cost * $qty;
      }
    }

    return round($total, 2);
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
