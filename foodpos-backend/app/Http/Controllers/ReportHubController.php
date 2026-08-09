<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MoneySource;
use App\Support\FocReport;
use App\Support\GrossMarginReport;
use App\Support\IngredientLedgerReport;
use App\Support\OrderHistoryReport;
use App\Support\ReportHubCatalog;
use App\Support\TransactionsByMoneySourceReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportHubController extends ReportController
{
    /** @var array<string, array{pdf?: string, excel?: string}> */
    private const EXPORT_ROUTES = [
        'daily' => ['pdf' => 'reports.daily.pdf', 'excel' => 'reports.daily.excel'],
        'top-selling' => ['pdf' => 'reports.top-selling.pdf', 'excel' => 'reports.top-selling.excel'],
        'payment-methods' => ['pdf' => 'reports.payment-methods.pdf', 'excel' => 'reports.payment-methods.excel'],
        'sales' => ['pdf' => 'reports.sales.pdf', 'excel' => 'reports.sales.excel'],
        'sales-by-item' => ['pdf' => 'reports.sales-by-item.pdf', 'excel' => 'reports.sales-by-item.excel'],
        'z-report' => ['pdf' => 'reports.z-report.pdf', 'excel' => 'reports.z-report.excel'],
        'foc' => ['pdf' => 'reports.foc.pdf', 'excel' => 'reports.foc.excel'],
        'transactions-by-money-source' => [
            'pdf' => 'reports.transactions-by-money-source.pdf',
            'excel' => 'reports.transactions-by-money-source.excel',
        ],
        'consumption' => ['pdf' => 'reports.consumption.pdf', 'excel' => 'reports.consumption.excel'],
        'ingredient-ledger' => ['pdf' => 'reports.ingredient-ledger.pdf', 'excel' => 'reports.ingredient-ledger.excel'],
        'gross-margin' => ['pdf' => 'reports.gross-margin.pdf', 'excel' => 'reports.gross-margin.excel'],
        'profit-loss' => ['pdf' => 'reports.profit-loss.pdf', 'excel' => 'reports.profit-loss.excel'],
        'order-history' => ['pdf' => 'reports.order-history.pdf', 'excel' => 'reports.order-history.excel'],
        'account-statement' => ['pdf' => 'account-statements.pdf'],
        'weekly-closing' => ['pdf' => 'reports.weekly-closing.pdf', 'excel' => 'reports.weekly-closing.excel'],
        'monthly-closing' => ['pdf' => 'reports.monthly-closing.pdf', 'excel' => 'reports.monthly-closing.excel'],
        'accounts-receivable' => ['pdf' => 'reports.accounts-receivable.pdf', 'excel' => 'reports.accounts-receivable.excel'],
        'accounts-payable' => ['pdf' => 'reports.accounts-payable.pdf', 'excel' => 'reports.accounts-payable.excel'],
        'customer-credits' => ['pdf' => 'reports.customer-credits.pdf', 'excel' => 'reports.customer-credits.excel'],
        'supplier-prepayments' => ['pdf' => 'reports.supplier-prepayments.pdf', 'excel' => 'reports.supplier-prepayments.excel'],
    ];

    /** @var array<string, string> */
    private const GROUP_LABELS = [
        'sales' => 'Sales',
        'inventory' => 'Inventory',
        'financial' => 'Financial',
        'closings' => 'Closings',
        'outstanding' => 'Outstanding',
    ];

    public function index(Request $request): View
    {
        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $selectedBranchId = $request->get('branch_id', $user->branch_id);
        $selectedBranch = $availableBranches->firstWhere('id', (int) $selectedBranchId)
            ?? $availableBranches->first();

        $flatReports = ReportHubCatalog::forUser($user);
        $reportsByGroup = collect($flatReports)->groupBy('group')->all();

        $availableKeys = array_column($flatReports, 'key');
        $requestedKey = $request->get('report');
        $initialReport = in_array($requestedKey, $availableKeys, true)
            ? $requestedKey
            : ($availableKeys[0] ?? null);

        $branchId = $selectedBranch?->id ?? $user->branch_id;
        $today = local_today($branchId);

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => (int) $category->id,
                'name' => $category->displayLabel(),
            ])
            ->values();

        $moneySources = MoneySource::query()
            ->operational()
            ->orderBy('name')
            ->when($user->company_id && ! $user->isSuperAdmin(), fn ($q) => $q->where('company_id', $user->company_id))
            ->get(['id', 'name'])
            ->map(fn (MoneySource $source) => ['id' => (int) $source->id, 'name' => $source->name])
            ->values();

        $ingredients = collect(IngredientLedgerReport::ingredientsForUser($user))
            ->map(fn (array $option) => [
                'id' => (int) $option['id'],
                'name' => $option['sku']
                    ? $option['name'].' ('.$option['sku'].')'
                    : $option['name'],
            ])
            ->values();

        return view('reports.hub.index', [
            'reports' => $reportsByGroup,
            'flatReports' => $flatReports,
            'catalogJson' => $flatReports,
            'initialReport' => $initialReport,
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'groupLabels' => self::GROUP_LABELS,
            'filterDefaults' => [
                'branch_id' => $branchId ? (string) $branchId : '',
                'from' => local_now($branchId)->startOfMonth()->format('Y-m-d'),
                'to' => $today,
                'limit' => '20',
                'week_of' => local_now($branchId)->startOfWeek()->format('Y-m-d'),
                'week_count' => '1',
                'month' => local_now($branchId)->format('Y-m'),
                'type' => 'customer',
                'party_id' => '',
                'party_label' => '',
                'party_query' => '',
            ],
            'filterOptions' => [
                'categories' => $categories,
                'menuItems' => \App\Models\MenuItem::query()
                    ->where('is_available', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'category_id'])
                    ->map(fn ($item) => [
                        'id' => (int) $item->id,
                        'name' => $item->name,
                        'category_id' => $item->category_id ? (int) $item->category_id : null,
                    ])
                    ->values(),
                'moneySources' => $moneySources,
                'ingredients' => $ingredients,
                'customers' => OrderHistoryReport::customersForFilter($user)->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                ])->values(),
                'staff' => OrderHistoryReport::staffForFilter($user, $branchId ? (int) $branchId : null, $availableBranches)->map(fn ($s) => [
                    'id' => (int) $s->id,
                    'name' => $s->name,
                ])->values(),
            ],
        ]);
    }

    public function panel(Request $request): JsonResponse
    {
        $key = $request->query('report');
        abort_unless(is_string($key) && $key !== '', 404);

        $def = ReportHubCatalog::definition($key);
        abort_unless($def !== null, 404);
        abort_unless($this->userCanViewReport(Auth::user(), $def), 403);

        $data = $this->buildPanelData($key, $request);
        $html = view('reports.hub.partials.'.$key, $data)->render();

        return response()->json([
            'key' => $key,
            'title' => $def['label'],
            'html' => $html,
            'exports' => $this->exportUrls($key, $request),
        ]);
    }

    /**
     * @return array{pdf: ?string, excel: ?string}
     */
    protected function exportUrls(string $key, Request $request): array
    {
        $routes = self::EXPORT_ROUTES[$key] ?? [];
        $params = collect($request->query())
            ->except(['report'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        return [
            'pdf' => isset($routes['pdf']) ? route($routes['pdf'], $params) : null,
            'excel' => isset($routes['excel']) ? route($routes['excel'], $params) : null,
        ];
    }

    /**
     * @param  array{key: string, permission: string|null}  $def
     */
    protected function userCanViewReport($user, array $def): bool
    {
        foreach (ReportHubCatalog::forUser($user) as $report) {
            if ($report['key'] === $def['key']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPanelData(string $key, Request $request): array
    {
        return match ($key) {
            'daily' => $this->buildDailyReport($request),
            'top-selling' => $this->buildTopSellingReport($request),
            'payment-methods' => $this->buildPaymentMethodsReport($request),
            'sales' => $this->buildSalesReport($request),
            'sales-by-item' => $this->buildSalesByItemPanelData($request),
            'z-report' => $this->buildZReportList($request),
            'foc' => $this->buildFocPanelData($request),
            'transactions-by-money-source' => $this->buildTransactionsPanelData($request),
            'consumption' => (function () use ($request) {
                $ctx = $this->resolveConsumption($request);

                return array_merge($ctx, [
                    'exportParams' => $this->consumptionFilterParams($request, $ctx),
                ]);
            })(),
            'ingredient-ledger' => $this->buildIngredientLedgerPanelData($request),
            'gross-margin' => $this->buildGrossMarginPanelData($request),
            'profit-loss' => $this->resolveProfitLoss($request, buildReport: true),
            'order-history' => $this->resolveOrderHistory($request, buildReport: true),
            'account-statement' => app(AccountStatementController::class)->resolveStatement($request),
            'weekly-closing' => array_merge(
                $this->resolvePeriodClosing($request, 'weekly', buildReport: true),
                ['reportMode' => 'weekly']
            ),
            'monthly-closing' => array_merge(
                $this->resolvePeriodClosing($request, 'monthly', buildReport: true),
                ['reportMode' => 'monthly']
            ),
            'accounts-receivable' => $this->resolveOutstandingReport($request, 'receivable', buildReport: true),
            'accounts-payable' => $this->resolveOutstandingReport($request, 'payable', buildReport: true),
            'customer-credits' => $this->resolveOutstandingReport($request, 'customer-credit', buildReport: true),
            'supplier-prepayments' => $this->resolveOutstandingReport($request, 'supplier-prepayment', buildReport: true),
            default => abort(404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildFocPanelData(Request $request): array
    {
        $user = Auth::user();
        abort_unless($user->hasAppPermission('reports.foc'), 403);

        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));

        $report = FocReport::build(
            $user,
            $branchId ? (int) $branchId : null,
            $from,
            $to
        );

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $availableBranches->firstWhere('id', (int) $branchId) ?? $availableBranches->first(),
            'from' => $from,
            'to' => $to,
            'summary' => $report['summary'],
            'rows' => $report['rows'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTransactionsPanelData(Request $request): array
    {
        $user = Auth::user();
        abort_unless($user->hasAppPermission('reports.transactions-by-money-source'), 403);

        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id ?: current_branch_id());
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));

        $moneySourceIds = collect($request->input('money_source_ids', []))
            ->when($request->filled('money_source_id'), fn ($ids) => $ids->push($request->input('money_source_id')))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $type = $request->get('type');
        if (! in_array($type, ['in', 'out'], true)) {
            $type = null;
        }

        $perPage = (int) $request->get('per_page', 25);

        $report = TransactionsByMoneySourceReport::build(
            $user,
            $branchId ? (int) $branchId : null,
            $from,
            $to,
            $moneySourceIds,
            $type,
            $perPage
        );

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $availableBranches->firstWhere('id', (int) $branchId) ?? $availableBranches->first(),
            'from' => $from,
            'to' => $to,
            'summary' => $report['summary'],
            'bySource' => $report['by_source'],
            'rows' => $report['rows'],
            'moneySourceIds' => $moneySourceIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildIngredientLedgerPanelData(Request $request): array
    {
        abort_unless(Auth::user()->hasAppPermission('reports.consumption'), 403);

        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));
        $ingredientId = $request->filled('ingredient_id') ? (int) $request->get('ingredient_id') : null;

        $ledger = null;
        if ($ingredientId) {
            $ledger = IngredientLedgerReport::build(
                $user,
                $ingredientId,
                $branchId ? (int) $branchId : null,
                $from,
                $to
            );
        }

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $availableBranches->firstWhere('id', (int) $branchId) ?? $availableBranches->first(),
            'branchId' => $branchId,
            'from' => $from,
            'to' => $to,
            'ingredientId' => $ingredientId,
            'ledger' => $ledger,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildGrossMarginPanelData(Request $request): array
    {
        $user = Auth::user();
        $built = GrossMarginReport::build($request);
        $filters = $built['filters'];
        $summary = GrossMarginReport::summary($built['rows']);
        $rows = GrossMarginReport::paginateCollection($built['rows'], $filters['per_page'], $request);

        return [
            'rows' => $rows,
            'filters' => $filters,
            'summary' => $summary,
            'hubMode' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSalesByItemPanelData(Request $request): array
    {
        abort_unless(Auth::user()->hasAppPermission('reports.sales-by-item'), 403);

        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));

        $categoryIds = $this->normalizePositiveIntIds(
            $request->input('category_ids', $request->filled('category_id') ? [$request->input('category_id')] : []),
            Category::query()->where('is_active', true)->pluck('id')
        );
        $menuItemIds = $this->normalizePositiveIntIds(
            collect($request->input('menu_item_ids', []))
                ->when($request->filled('menu_item_id'), fn ($ids) => $ids->push($request->input('menu_item_id')))
                ->all(),
            \App\Models\MenuItem::query()->pluck('id')
        );

        $resolvedMenuItemIds = $menuItemIds;
        if ($resolvedMenuItemIds === [] && $categoryIds !== []) {
            $resolvedMenuItemIds = \App\Models\MenuItem::withTrashed()
                ->whereIn('category_id', $categoryIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($resolvedMenuItemIds === []) {
            return [
                'error' => 'Select at least one category or menu item.',
                'summary' => null,
                'orders' => null,
                'selectionLabel' => null,
                'from' => $from,
                'to' => $to,
                'selectedBranch' => $availableBranches->firstWhere('id', (int) $branchId),
                'availableBranches' => $availableBranches,
            ];
        }

        $selectedCategories = Category::query()->whereIn('id', $categoryIds)->get();
        $selectedMenuItems = \App\Models\MenuItem::query()->whereIn('id', $menuItemIds)->get();
        $selectionLabel = $this->salesByItemSelectionLabel($selectedMenuItems, $selectedCategories);

        $orderQuery = \App\Models\Order::query()->where('status', 'completed');
        $this->applyCreatedAtDateRange($orderQuery, $from, $to, $branchId);
        $this->applyBranchScope($orderQuery, $user, $branchId);

        $lineQuery = \App\Models\OrderItem::query()
            ->whereIn('order_id', (clone $orderQuery)->select('orders.id'))
            ->whereIn('menu_item_id', $resolvedMenuItemIds);
        $quantitySql = 'CASE WHEN quantity > COALESCE(quantity_refunded, 0) '
            .'THEN quantity - COALESCE(quantity_refunded, 0) ELSE 0 END';
        $summaryRow = (clone $lineQuery)
            ->selectRaw('COUNT(DISTINCT order_id) AS order_count')
            ->selectRaw("COALESCE(SUM({$quantitySql}), 0) AS matched_quantity")
            ->selectRaw('COALESCE(SUM(total_price), 0) AS matched_sales')
            ->first();
        $summary = [
            'order_count' => (int) ($summaryRow?->order_count ?? 0),
            'matched_quantity' => (float) ($summaryRow?->matched_quantity ?? 0),
            'matched_sales' => (float) ($summaryRow?->matched_sales ?? 0),
        ];

        $quantitySubquery = \App\Models\OrderItem::query()
            ->selectRaw("COALESCE(SUM({$quantitySql}), 0)")
            ->whereColumn('order_items.order_id', 'orders.id')
            ->whereIn('menu_item_id', $resolvedMenuItemIds);
        $salesSubquery = \App\Models\OrderItem::query()
            ->selectRaw('COALESCE(SUM(total_price), 0)')
            ->whereColumn('order_items.order_id', 'orders.id')
            ->whereIn('menu_item_id', $resolvedMenuItemIds);

        $orders = (clone $orderQuery)
            ->whereIn('orders.id', (clone $lineQuery)->select('order_id')->distinct())
            ->addSelect([
                'matched_quantity' => $quantitySubquery,
                'matched_sales' => $salesSubquery,
            ])
            ->with(['customer'])
            ->orderByDesc('orders.created_at')
            ->paginate(25)
            ->withQueryString();

        return [
            'error' => null,
            'summary' => $summary,
            'orders' => $orders,
            'selectionLabel' => $selectionLabel,
            'from' => $from,
            'to' => $to,
            'selectedBranch' => $availableBranches->firstWhere('id', (int) $branchId),
            'availableBranches' => $availableBranches,
        ];
    }
}
