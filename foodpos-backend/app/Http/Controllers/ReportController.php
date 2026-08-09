<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shift;
use App\Support\ListingPerPage;
use App\Services\CompanyReceiptBrandingService;
use App\Support\AccountsOutstandingReport;
use App\Support\ConsumptionReport;
use App\Support\ConsumptionReportDetail;
use App\Support\ConsumptionReportExcelExport;
use App\Support\FocReport;
use App\Support\GrossMarginReport;
use App\Support\IngredientLedgerReport;
use App\Support\OrderHistoryReport;
use App\Support\OutstandingReportExcelExport;
use App\Support\PeriodClosingExcelExport;
use App\Support\PeriodClosingReport;
use App\Support\ProfitLossReport;
use App\Support\ReportTableExcelExport;
use App\Support\TopSellingItemsReport;
use App\Support\TransactionsByMoneySourceReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    protected function redirectToHub(Request $request, string $reportKey): RedirectResponse
    {
        return redirect()->route('reports.index', array_merge($request->query(), ['report' => $reportKey]));
    }

    /**
     * Z Report hub: pick a shift to view end-of-shift reconciliation.
     */
    public function zReport(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'z-report');
    }

    /**
     * Sales report: period summary KPIs, sales by category, optional category order drilldown.
     */
    public function sales(Request $request): RedirectResponse
    {
        abort_unless(
            Auth::user()->hasAppPermission('reports.sales')
            || Auth::user()->hasAppPermission('reports.sales-by-category'),
            403
        );

        return $this->redirectToHub($request, 'sales');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSalesReport(Request $request): array
    {
        $user = Auth::user();
        abort_unless(
            $user->hasAppPermission('reports.sales')
            || $user->hasAppPermission('reports.sales-by-category'),
            403
        );

        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));

        $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::in($availableBranches->pluck('id')->map(fn ($id) => (string) $id)->all()),
            ],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $orderQuery = Order::query()->where('status', 'completed');
        $this->applyCreatedAtDateRange($orderQuery, $from, $to, $branchId);
        $this->applyBranchScope($orderQuery, $user, $branchId);

        $totals = (clone $orderQuery)
            ->selectRaw('COUNT(*) AS order_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total_revenue')
            ->selectRaw('COALESCE(SUM(subtotal), 0) AS subtotal')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) AS tax_amount')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) AS discount_amount')
            ->first();

        $orderCount = (int) ($totals?->order_count ?? 0);
        $totalRevenue = (float) ($totals?->total_revenue ?? 0);
        $subtotal = (float) ($totals?->subtotal ?? 0);
        $taxAmount = (float) ($totals?->tax_amount ?? 0);
        $discountAmount = (float) ($totals?->discount_amount ?? 0);
        $avgOrderValue = $orderCount > 0 ? $totalRevenue / $orderCount : 0.0;

        $quantitySql = 'CASE WHEN order_items.quantity > COALESCE(order_items.quantity_refunded, 0) '
            .'THEN order_items.quantity - COALESCE(order_items.quantity_refunded, 0) ELSE 0 END';
        $categoryIdSql = 'CASE WHEN categories.id IS NOT NULL THEN categories.id '
            .'WHEN order_items.deal_id IS NOT NULL THEN -1 ELSE -2 END';
        $categoryNameSql = "CASE WHEN categories.id IS NOT NULL THEN categories.name "
            ."WHEN order_items.deal_id IS NOT NULL THEN 'Deals' ELSE 'Uncategorized' END";

        $categoryRows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->leftJoin('categories', 'categories.id', '=', 'menu_items.category_id')
            ->whereIn('orders.id', (clone $orderQuery)->select('orders.id'))
            ->selectRaw("{$categoryIdSql} AS category_id")
            ->selectRaw("{$categoryNameSql} AS category_name")
            ->selectRaw('MAX(categories.code) AS category_code')
            ->selectRaw('COUNT(DISTINCT orders.id) AS order_count')
            ->selectRaw("COALESCE(SUM({$quantitySql}), 0) AS quantity")
            ->selectRaw('COALESCE(SUM(order_items.total_price), 0) AS sales')
            ->groupByRaw("{$categoryIdSql}, {$categoryNameSql}")
            ->orderByDesc('sales')
            ->get()
            ->map(function ($row) {
                $categoryId = (int) $row->category_id;
                $name = (string) $row->category_name;
                $code = $row->category_code;

                return [
                    'category_id' => $categoryId > 0 ? $categoryId : null,
                    'category_name' => $name,
                    'category_label' => $code ? "{$code} — {$name}" : $name,
                    'order_count' => (int) $row->order_count,
                    'quantity' => (float) $row->quantity,
                    'sales' => (float) $row->sales,
                ];
            });

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $selectedCategoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $selectedCategory = $selectedCategoryId
            ? $categories->firstWhere('id', $selectedCategoryId)
            : null;

        $categoryOrders = null;
        $categorySummary = null;
        if ($selectedCategory) {
            $menuItemIds = MenuItem::withTrashed()
                ->where('category_id', $selectedCategory->id)
                ->pluck('id')
                ->all();

            $lineQuery = OrderItem::query()
                ->whereIn('order_id', (clone $orderQuery)->select('orders.id'))
                ->whereIn('menu_item_id', $menuItemIds);
            $netQtySql = 'CASE WHEN quantity > COALESCE(quantity_refunded, 0) '
                .'THEN quantity - COALESCE(quantity_refunded, 0) ELSE 0 END';
            $summaryRow = (clone $lineQuery)
                ->selectRaw('COUNT(DISTINCT order_id) AS order_count')
                ->selectRaw("COALESCE(SUM({$netQtySql}), 0) AS matched_quantity")
                ->selectRaw('COALESCE(SUM(total_price), 0) AS matched_sales')
                ->first();
            $categorySummary = [
                'order_count' => (int) ($summaryRow?->order_count ?? 0),
                'matched_quantity' => (float) ($summaryRow?->matched_quantity ?? 0),
                'matched_sales' => (float) ($summaryRow?->matched_sales ?? 0),
            ];

            $quantitySubquery = OrderItem::query()
                ->selectRaw("COALESCE(SUM({$netQtySql}), 0)")
                ->whereColumn('order_items.order_id', 'orders.id')
                ->whereIn('menu_item_id', $menuItemIds);
            $salesSubquery = OrderItem::query()
                ->selectRaw('COALESCE(SUM(total_price), 0)')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->whereIn('menu_item_id', $menuItemIds);

            $categoryOrders = (clone $orderQuery)
                ->whereIn('orders.id', (clone $lineQuery)->select('order_id')->distinct())
                ->addSelect([
                    'matched_quantity' => $quantitySubquery,
                    'matched_sales' => $salesSubquery,
                ])
                ->with(['branch', 'customer'])
                ->orderByDesc('orders.created_at')
                ->paginate(25)
                ->withQueryString();
        }

        $selectedBranch = $availableBranches->firstWhere('id', (int) $branchId) ?? $availableBranches->first();

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'branchId' => $branchId,
            'from' => $from,
            'to' => $to,
            'totalRevenue' => $totalRevenue,
            'subtotal' => $subtotal,
            'taxAmount' => $taxAmount,
            'discountAmount' => $discountAmount,
            'orderCount' => $orderCount,
            'avgOrderValue' => $avgOrderValue,
            'categoryRows' => $categoryRows,
            'categories' => $categories,
            'selectedCategoryId' => $selectedCategoryId,
            'selectedCategory' => $selectedCategory,
            'categoryOrders' => $categoryOrders,
            'categorySummary' => $categorySummary,
            'hubMode' => true,
        ];
    }

    /**
     * Legacy URL: Sales by Category is now part of the Sales report.
     */
    public function salesByCategory(Request $request): RedirectResponse
    {
        $query = $request->query();
        unset($query['generate']);

        return redirect()->route('reports.index', array_merge($query, ['report' => 'sales']));
    }

    /**
     * Sales by Item: AJAX still returns the results partial; full page redirects to the hub.
     */
    public function salesByItem(Request $request): RedirectResponse|Response
    {
        abort_unless(Auth::user()->hasAppPermission('reports.sales-by-item'), 403);

        if ($request->ajax() && $request->boolean('generate')) {
            return $this->salesByItemResults($request);
        }

        return $this->redirectToHub($request, 'sales-by-item');
    }

    /**
     * AJAX partial: matched orders for Sales by Item.
     */
    protected function salesByItemResults(Request $request): Response
    {
        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));

        $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::in($availableBranches->pluck('id')->map(fn ($id) => (string) $id)->all()),
            ],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'menu_item_ids' => ['nullable', 'array'],
            'menu_item_ids.*' => ['integer'],
            'menu_item_id' => ['nullable', 'integer'],
        ]);

        $categoryIds = $this->normalizePositiveIntIds(
            $request->input('category_ids', []),
            Category::query()->where('is_active', true)->pluck('id')
        );
        $menuItemIds = $this->normalizePositiveIntIds(
            collect($request->input('menu_item_ids', []))
                ->when($request->filled('menu_item_id'), fn ($ids) => $ids->push($request->input('menu_item_id')))
                ->all(),
            MenuItem::query()->pluck('id')
        );

        $resolvedMenuItemIds = $menuItemIds;
        if ($resolvedMenuItemIds === [] && $categoryIds !== []) {
            $resolvedMenuItemIds = MenuItem::withTrashed()
                ->whereIn('category_id', $categoryIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($resolvedMenuItemIds === []) {
            return response()->view('reports.partials.sales-by-item-results', [
                'error' => 'Select at least one category or menu item.',
                'summary' => null,
                'orders' => null,
                'selectionLabel' => null,
                'from' => $from,
                'to' => $to,
                'selectedBranch' => $availableBranches->firstWhere('id', (int) $branchId),
                'availableBranches' => $availableBranches,
            ], 422);
        }

        $selectedCategories = Category::query()->whereIn('id', $categoryIds)->get();
        $selectedMenuItems = MenuItem::query()->whereIn('id', $menuItemIds)->get();
        $selectionLabel = $this->salesByItemSelectionLabel($selectedMenuItems, $selectedCategories);

        $orderQuery = Order::query()->where('status', 'completed');
        $this->applyCreatedAtDateRange($orderQuery, $from, $to, $branchId);
        $this->applyBranchScope($orderQuery, $user, $branchId);

        $lineQuery = OrderItem::query()
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

        $quantitySubquery = OrderItem::query()
            ->selectRaw("COALESCE(SUM({$quantitySql}), 0)")
            ->whereColumn('order_items.order_id', 'orders.id')
            ->whereIn('menu_item_id', $resolvedMenuItemIds);
        $salesSubquery = OrderItem::query()
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

        return response()->view('reports.partials.sales-by-item-results', [
            'error' => null,
            'summary' => $summary,
            'orders' => $orders,
            'selectionLabel' => $selectionLabel,
            'from' => $from,
            'to' => $to,
            'selectedBranch' => $availableBranches->firstWhere('id', (int) $branchId),
            'availableBranches' => $availableBranches,
        ]);
    }

    /**
     * @param  iterable<int|string>  $ids
     * @param  \Illuminate\Support\Collection<int, int|string>  $allowedIds
     * @return list<int>
     */
    protected function normalizePositiveIntIds(iterable $ids, $allowedIds): array
    {
        $allowed = $allowedIds->map(fn ($id) => (int) $id)->all();

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->filter(fn ($id) => in_array($id, $allowed, true))
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MenuItem>  $selectedMenuItems
     * @param  \Illuminate\Support\Collection<int, Category>  $selectedCategories
     */
    protected function salesByItemSelectionLabel($selectedMenuItems, $selectedCategories): string
    {
        if ($selectedMenuItems->count() === 1) {
            return $selectedMenuItems->first()->name;
        }
        if ($selectedMenuItems->count() > 1) {
            return $selectedMenuItems->count().' items';
        }
        if ($selectedCategories->count() === 1) {
            return $selectedCategories->first()->displayLabel().' (all items)';
        }
        if ($selectedCategories->count() > 1) {
            return $selectedCategories->count().' categories (all items)';
        }

        return 'Selected items';
    }

    /**
     * Inventory consumption: ingredients and tracked menu items used in a date range, with cost.
     */
    public function consumption(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.consumption'), 403);

        return $this->redirectToHub($request, 'consumption');
    }

    /**
     * Lightweight options for consumption category / menu-item filters (loaded via AJAX).
     */
    public function consumptionFilterOptions(Request $request)
    {
        abort_unless(Auth::user()->hasAppPermission('reports.consumption'), 403);

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'company_id']);

        $menuItems = MenuItem::query()
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'category_id']);

        return response()->json([
            'categories' => $categories->map(fn (Category $category) => [
                'id' => (int) $category->id,
                'name' => $category->displayLabel(),
                'code' => $category->code,
                'search_text' => trim($category->displayLabel().' '.($category->code ?? '')),
            ])->values(),
            'menu_items' => $menuItems->map(fn (MenuItem $item) => [
                'id' => (int) $item->id,
                'name' => $item->name,
                'code' => $item->sku,
                'category_id' => $item->category_id ? (int) $item->category_id : null,
                'search_text' => trim($item->name.' '.($item->sku ?? '')),
            ])->values(),
        ]);
    }

    public function consumptionDetail(Request $request, string $itemType, int $itemId)
    {
        abort_unless(Auth::user()->hasAppPermission('reports.consumption'), 403);
        abort_unless(in_array($itemType, ['ingredient', 'menu_item'], true), 404);

        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));
        $request->merge(['from' => $from, 'to' => $to]);
        $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::in($availableBranches->pluck('id')->map(fn ($id) => (string) $id)->all()),
            ],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $detail = ConsumptionReportDetail::build(
            $user,
            $branchId ? (int) $branchId : null,
            $from,
            $to,
            $itemType,
            $itemId
        );
        abort_unless($detail, 404);

        return view('reports.consumption-detail', [
            'detail' => $detail,
            'availableBranches' => $availableBranches,
            'selectedBranch' => $availableBranches->firstWhere('id', (int) $branchId),
            'branchId' => $branchId,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function consumptionPdf(Request $request): Response|RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.consumption'), 403);

        $ctx = $this->resolveConsumption($request);
        $filename = $this->consumptionFilename($ctx, 'pdf');

        return Pdf::loadView('reports.consumption-pdf', $ctx)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function consumptionExcel(Request $request): StreamedResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.consumption'), 403);

        $ctx = $this->resolveConsumption($request);
        $filename = $this->consumptionFilename($ctx, 'xlsx');
        $branchLabel = $ctx['selectedBranch']?->name
            ?? ($ctx['availableBranches']->count() > 1 ? 'All branches' : ($ctx['availableBranches']->first()?->name ?? null));

        return (new ConsumptionReportExcelExport(
            summary: $ctx['summary'],
            rows: $ctx['rows'],
            businessName: $ctx['businessName'],
            branchLabel: $branchLabel,
            from: $ctx['from'],
            to: $ctx['to'],
            generatedAt: $ctx['generatedAt'],
            search: $ctx['search'],
        ))->download($filename);
    }

    /**
     * Single-ingredient ledger: purchases, sales, and adjustments on one timeline.
     */
    public function ingredientLedger(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.consumption'), 403);

        return $this->redirectToHub($request, 'ingredient-ledger');
    }

    /**
     * @return array{
     *     availableBranches: \Illuminate\Support\Collection,
     *     selectedBranch: mixed,
     *     from: string,
     *     to: string,
     *     search: string,
     *     categoryId: ?int,
     *     menuItemId: ?int,
     *     summary: array{total_cost: float, sales_cost: float, adjustment_cost: float, item_count: int},
     *     rows: \Illuminate\Support\Collection,
     *     businessName: string,
     *     businessAddress: ?string,
     *     businessPhone: ?string,
     *     generatedAt: \Illuminate\Support\Carbon
     * }
     */
    protected function resolveConsumption(Request $request): array
    {
        abort_unless(Auth::user()->hasAppPermission('reports.consumption'), 403);

        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));
        $search = trim((string) $request->get('search', ''));
        $categoryId = $request->filled('category_id') ? (int) $request->get('category_id') : null;
        $menuItemId = $request->filled('menu_item_id') ? (int) $request->get('menu_item_id') : null;

        $request->merge(['from' => $from, 'to' => $to]);
        $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::in($availableBranches->pluck('id')->map(fn ($id) => (string) $id)->all()),
            ],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'menu_item_id' => ['nullable', 'integer'],
        ]);

        $report = ConsumptionReport::build(
            $user,
            $branchId ? (int) $branchId : null,
            $from,
            $to,
            $search,
            $categoryId,
            $menuItemId
        );

        $selectedBranch = $availableBranches->firstWhere('id', (int) $branchId) ?? $availableBranches->first();

        $branding = CompanyReceiptBrandingService::get($user->company);
        $companyBranding = $branding['company'] ?? [];
        $branchBranding = $branchId && isset($branding['branches'][(int) $branchId])
            ? $branding['branches'][(int) $branchId]
            : null;

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'categoryId' => $categoryId,
            'menuItemId' => $menuItemId,
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'businessName' => $companyBranding['name'] ?? $user->company?->name ?? config('app.name'),
            'businessAddress' => $companyBranding['address'] ?? $branchBranding['address'] ?? $user->company?->address,
            'businessPhone' => $companyBranding['phone'] ?? $branchBranding['phone'] ?? $user->company?->phone,
            'generatedAt' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{branch_id?: int|string, from: string, to: string, search?: string, category_id?: int, menu_item_id?: int}
     */
    protected function consumptionFilterParams(Request $request, array $ctx): array
    {
        $params = [
            'from' => $ctx['from'],
            'to' => $ctx['to'],
        ];

        if ($request->filled('branch_id') || ($ctx['selectedBranch']?->id ?? null)) {
            $params['branch_id'] = $request->get('branch_id', $ctx['selectedBranch']?->id);
        }

        if (($ctx['search'] ?? '') !== '') {
            $params['search'] = $ctx['search'];
        }

        if (! empty($ctx['categoryId'])) {
            $params['category_id'] = $ctx['categoryId'];
        }

        if (! empty($ctx['menuItemId'])) {
            $params['menu_item_id'] = $ctx['menuItemId'];
        }

        return $params;
    }

    /**
     * @param  array{from: string, to: string, selectedBranch: mixed}  $ctx
     */
    protected function consumptionFilename(array $ctx, string $extension): string
    {
        $periodSlug = sprintf('%s_%s', $ctx['from'], $ctx['to']);
        $branchSlug = $ctx['selectedBranch'] ? Str::slug($ctx['selectedBranch']->name) : 'all-branches';

        return sprintf('consumption-%s-%s.%s', $periodSlug, $branchSlug, $extension);
    }

    /**
     * Top Selling Items: by quantity and revenue (menu items + deals).
     */
    public function topSelling(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'top-selling');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTopSellingReport(Request $request): array
    {
        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));
        $limit = (int) $request->get('limit', 20);

        $orderSubQuery = Order::where('status', 'completed');
        $this->applyCreatedAtDateRange($orderSubQuery, $from, $to, $branchId);
        $orderSubQuery->select('id');
        $this->applyBranchScope($orderSubQuery, $user, $branchId);

        $items = TopSellingItemsReport::aggregate(
            OrderItem::whereIn('order_id', $orderSubQuery),
            $limit
        );

        $selectedBranch = $availableBranches->firstWhere('id', (int) $branchId) ?? $availableBranches->first();

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'from' => $from,
            'to' => $to,
            'items' => $items,
            'limit' => $limit,
        ];
    }

    public function daily(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'daily');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDailyReport(Request $request): array
    {
        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->subDays(30)->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));

        $query = Order::where('status', 'completed');
        $this->applyCreatedAtDateRange($query, $from, $to, $branchId);
        $this->applyBranchScope($query, $user, $branchId);

        $dateSql = tz()->localDateSql('created_at', $branchId);
        $businessDaySql = "COALESCE(business_date, {$dateSql})";
        $daily = $query
            ->select(DB::raw("{$businessDaySql} as date"), DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $selectedBranch = $availableBranches->firstWhere('id', (int) $branchId) ?? $availableBranches->first();

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'from' => $from,
            'to' => $to,
            'daily' => $daily,
        ];
    }

    public function grossMargin(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'gross-margin');
    }

    public function profitLoss(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'profit-loss');
    }

    public function profitLossPdf(Request $request): Response|RedirectResponse
    {
        $ctx = $this->resolveProfitLoss($request, buildReport: true);

        if (! $ctx['report']) {
            return redirect()
                ->route('reports.index', array_merge($request->only(['branch_id', 'from', 'to']), ['report' => 'profit-loss']))
                ->with('error', 'Generate the report before exporting to PDF.');
        }

        $periodSlug = sprintf('%s_%s', $ctx['from'], $ctx['to']);
        $branchSlug = $ctx['selectedBranch'] ? Str::slug($ctx['selectedBranch']->name) : 'all-branches';
        $filename = sprintf('profit-loss-%s-%s.pdf', $periodSlug, $branchSlug);

        return Pdf::loadView('reports.profit-loss-pdf', $ctx)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * Order History: filtered order listing with PDF export.
     */
    public function orderHistory(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'order-history');
    }

    public function orderHistoryPdf(Request $request): Response|RedirectResponse
    {
        $ctx = $this->resolveOrderHistory($request, buildReport: true, forPdf: true);

        if (! $ctx['showReport']) {
            return redirect()
                ->route('reports.index', array_merge($this->orderHistoryFilterParams($request), ['report' => 'order-history']))
                ->with('error', 'Generate the report before exporting to PDF.');
        }

        $periodSlug = sprintf('%s_%s', $ctx['from'], $ctx['to']);
        $branchSlug = $ctx['selectedBranch'] ? Str::slug($ctx['selectedBranch']->name) : 'all-branches';
        $filename = sprintf('order-history-%s-%s.pdf', $periodSlug, $branchSlug);

        return Pdf::loadView('reports.order-history-pdf', $ctx)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function weeklyClosing(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'weekly-closing');
    }

    public function weeklyClosingPdf(Request $request): Response|RedirectResponse
    {
        return $this->periodClosingPdf($request, 'weekly');
    }

    public function weeklyClosingExcel(Request $request): StreamedResponse|RedirectResponse
    {
        return $this->periodClosingExcel($request, 'weekly');
    }

    public function monthlyClosing(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'monthly-closing');
    }

    public function monthlyClosingPdf(Request $request): Response|RedirectResponse
    {
        return $this->periodClosingPdf($request, 'monthly');
    }

    public function monthlyClosingExcel(Request $request): StreamedResponse|RedirectResponse
    {
        return $this->periodClosingExcel($request, 'monthly');
    }

    protected function periodClosingPdf(Request $request, string $mode): Response|RedirectResponse
    {
        $ctx = $this->resolvePeriodClosing($request, $mode, buildReport: true);
        $route = $mode === 'weekly' ? 'reports.index' : 'reports.index';
        $reportKey = $mode === 'weekly' ? 'weekly-closing' : 'monthly-closing';

        if (! $ctx['showReport'] || ! $ctx['report']) {
            return redirect()
                ->route($route, array_merge($this->periodClosingFilterParams($request, $mode), ['report' => $reportKey]))
                ->with('error', 'Generate the report before exporting to PDF.');
        }

        $filename = $this->periodClosingFilename($ctx, $mode, 'pdf');

        return Pdf::loadView('reports.period-closing-pdf', array_merge($ctx, ['reportMode' => $mode]))
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    protected function periodClosingExcel(Request $request, string $mode): StreamedResponse|RedirectResponse
    {
        $ctx = $this->resolvePeriodClosing($request, $mode, buildReport: true);
        $reportKey = $mode === 'weekly' ? 'weekly-closing' : 'monthly-closing';

        if (! $ctx['showReport'] || ! $ctx['report']) {
            return redirect()
                ->route('reports.index', array_merge($this->periodClosingFilterParams($request, $mode), ['report' => $reportKey]))
                ->with('error', 'Generate the report before exporting to Excel.');
        }

        $filename = $this->periodClosingFilename($ctx, $mode, 'xlsx');
        $branchLabel = $ctx['selectedBranch']?->name
            ?? ($ctx['availableBranches']->count() > 1 ? 'All branches' : ($ctx['availableBranches']->first()?->name ?? null));
        $title = $mode === 'weekly' ? 'Weekly Closing Report' : 'Monthly Closing Report';

        return (new PeriodClosingExcelExport(
            report: $ctx['report'],
            reportTitle: $title,
            businessName: $ctx['businessName'],
            branchLabel: $branchLabel,
            generatedAt: $ctx['generatedAt'],
        ))->download($filename);
    }

    protected function periodClosingFilename(array $ctx, string $mode, string $extension): string
    {
        $slug = $mode === 'weekly' ? 'weekly-closing' : 'monthly-closing';
        $periodSlug = $mode === 'weekly'
            ? sprintf('%s_%dw', $ctx['week_of'], $ctx['week_count'])
            : str_replace('-', '', (string) $ctx['month']);
        $branchSlug = $ctx['selectedBranch'] ? Str::slug($ctx['selectedBranch']->name) : 'all-branches';

        return sprintf('%s-%s-%s.%s', $slug, $periodSlug, $branchSlug, $extension);
    }

    /**
     * Accounts Receivable: outstanding customer credit balances.
     */
    public function accountsReceivable(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'accounts-receivable');
    }

    public function accountsReceivablePdf(Request $request): Response|RedirectResponse
    {
        return $this->outstandingReportPdf($request, 'receivable', 'accounts-receivable');
    }

    public function accountsReceivableExcel(Request $request): StreamedResponse|RedirectResponse
    {
        return $this->outstandingReportExcel($request, 'receivable', 'accounts-receivable');
    }

    /**
     * Accounts Payable: outstanding supplier balances.
     */
    public function accountsPayable(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'accounts-payable');
    }

    public function accountsPayablePdf(Request $request): Response|RedirectResponse
    {
        return $this->outstandingReportPdf($request, 'payable', 'accounts-payable');
    }

    public function accountsPayableExcel(Request $request): StreamedResponse|RedirectResponse
    {
        return $this->outstandingReportExcel($request, 'payable', 'accounts-payable');
    }

    public function customerCredits(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'customer-credits');
    }

    public function customerCreditsPdf(Request $request): Response|RedirectResponse
    {
        return $this->outstandingReportPdf($request, 'customer-credit', 'customer-credits');
    }

    public function customerCreditsExcel(Request $request): StreamedResponse|RedirectResponse
    {
        return $this->outstandingReportExcel($request, 'customer-credit', 'customer-credits');
    }

    public function supplierPrepayments(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'supplier-prepayments');
    }

    public function supplierPrepaymentsPdf(Request $request): Response|RedirectResponse
    {
        return $this->outstandingReportPdf($request, 'supplier-prepayment', 'supplier-prepayments');
    }

    public function supplierPrepaymentsExcel(Request $request): StreamedResponse|RedirectResponse
    {
        return $this->outstandingReportExcel($request, 'supplier-prepayment', 'supplier-prepayments');
    }

    protected function outstandingReportPdf(Request $request, string $type, string $slug): Response|RedirectResponse
    {
        $ctx = $this->resolveOutstandingReport($request, $type, buildReport: true);
        $route = match ($type) {
            'receivable' => 'accounts-receivable',
            'payable' => 'accounts-payable',
            'customer-credit' => 'customer-credits',
            'supplier-prepayment' => 'supplier-prepayments',
            default => 'daily',
        };

        if (! $ctx['report']) {
            return redirect()
                ->route('reports.index', array_merge($request->only(['branch_id']), ['report' => $route]))
                ->with('error', 'Generate the report before exporting to PDF.');
        }

        $branchSlug = $ctx['selectedBranch'] ? Str::slug($ctx['selectedBranch']->name) : 'all-branches';
        $filename = sprintf('%s-%s-%s.pdf', $slug, $ctx['report']['as_of'], $branchSlug);

        return Pdf::loadView('reports.outstanding-pdf', array_merge($ctx, ['reportType' => $type]))
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    protected function outstandingReportExcel(Request $request, string $type, string $slug): StreamedResponse|RedirectResponse
    {
        $ctx = $this->resolveOutstandingReport($request, $type, buildReport: true);
        $reportKey = match ($type) {
            'receivable' => 'accounts-receivable',
            'payable' => 'accounts-payable',
            'customer-credit' => 'customer-credits',
            'supplier-prepayment' => 'supplier-prepayments',
            default => 'daily',
        };

        if (! $ctx['report']) {
            return redirect()
                ->route('reports.index', array_merge($request->only(['branch_id']), ['report' => $reportKey]))
                ->with('error', 'Generate the report before exporting to Excel.');
        }

        $meta = $this->outstandingReportMeta($type);
        $branchSlug = $ctx['selectedBranch'] ? Str::slug($ctx['selectedBranch']->name) : 'all-branches';
        $filename = sprintf('%s-%s-%s.xlsx', $slug, $ctx['report']['as_of'], $branchSlug);
        $branchLabel = $ctx['selectedBranch']?->name
            ?? ($ctx['availableBranches']->count() > 1 ? 'All branches (company total)' : ($ctx['availableBranches']->first()?->name ?? null));

        return (new OutstandingReportExcelExport(
            report: $ctx['report'],
            reportTitle: $meta['title'],
            partyLabel: $meta['partyLabel'],
            amountLabel: $meta['amountLabel'],
            businessName: $ctx['businessName'],
            branchLabel: $branchLabel,
            generatedAt: $ctx['generatedAt'],
        ))->download($filename);
    }

    /**
     * @return array{title: string, partyLabel: string, amountLabel: string}
     */
    protected function outstandingReportMeta(string $type): array
    {
        return match ($type) {
            'receivable' => [
                'title' => 'Accounts Receivable',
                'partyLabel' => 'Customer',
                'amountLabel' => 'Outstanding',
            ],
            'payable' => [
                'title' => 'Accounts Payable',
                'partyLabel' => 'Supplier',
                'amountLabel' => 'Outstanding',
            ],
            'customer-credit' => [
                'title' => 'Customer Credits',
                'partyLabel' => 'Customer',
                'amountLabel' => 'Credit available',
            ],
            'supplier-prepayment' => [
                'title' => 'Supplier Prepayments',
                'partyLabel' => 'Supplier',
                'amountLabel' => 'Prepaid',
            ],
            default => [
                'title' => 'Outstanding Report',
                'partyLabel' => 'Party',
                'amountLabel' => 'Amount',
            ],
        };
    }

    /**
     * @return array{
     *     availableBranches: \Illuminate\Support\Collection,
     *     selectedBranch: ?Branch,
     *     showReport: bool,
     *     report: ?array,
     *     reportType: string,
     *     businessName: string,
     *     businessAddress: ?string,
     *     businessPhone: ?string,
     *     generatedAt: \Illuminate\Support\Carbon
     * }
     */
    protected function resolveOutstandingReport(Request $request, string $type, bool $buildReport): array
    {
        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchIdRaw = $request->has('branch_id') ? $request->input('branch_id') : $user->branch_id;
        $branchId = ($branchIdRaw !== null && $branchIdRaw !== '') ? (int) $branchIdRaw : null;
        $selectedBranch = $branchId
            ? ($availableBranches->firstWhere('id', $branchId) ?? $availableBranches->first())
            : null;

        $report = $buildReport
            ? match ($type) {
                'receivable' => AccountsOutstandingReport::receivables($user, $branchId),
                'payable' => AccountsOutstandingReport::payables($user, $branchId),
                'customer-credit' => AccountsOutstandingReport::customerCredits($user, $branchId),
                'supplier-prepayment' => AccountsOutstandingReport::supplierPrepayments($user, $branchId),
                default => null,
            }
            : null;

        $branding = CompanyReceiptBrandingService::get($user->company);
        $companyBranding = $branding['company'] ?? [];
        $branchBranding = $branchId && isset($branding['branches'][(int) $branchId])
            ? $branding['branches'][(int) $branchId]
            : null;

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'showReport' => $buildReport,
            'report' => $report,
            'reportType' => $type,
            'businessName' => $companyBranding['name'] ?? $user->company?->name ?? config('app.name'),
            'businessAddress' => $companyBranding['address'] ?? $branchBranding['address'] ?? $user->company?->address,
            'businessPhone' => $companyBranding['phone'] ?? $branchBranding['phone'] ?? $user->company?->phone,
            'generatedAt' => now(),
        ];
    }

    /**
     * @return array{
     *     availableBranches: \Illuminate\Support\Collection,
     *     selectedBranch: ?Branch,
     *     from: string,
     *     to: string,
     *     showReport: bool,
     *     report: ?array,
     *     businessName: string,
     *     businessAddress: ?string,
     *     businessPhone: ?string,
     *     generatedAt: \Illuminate\Support\Carbon
     * }
     */
    protected function resolveProfitLoss(Request $request, bool $buildReport): array
    {
        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));

        $selectedBranch = $availableBranches->firstWhere('id', (int) $branchId) ?? $availableBranches->first();
        $report = $buildReport
            ? ProfitLossReport::build($user, $branchId ? (int) $branchId : null, $from, $to)
            : null;

        $branding = CompanyReceiptBrandingService::get($user->company);
        $companyBranding = $branding['company'] ?? [];
        $branchBranding = $branchId && isset($branding['branches'][(int) $branchId])
            ? $branding['branches'][(int) $branchId]
            : null;

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'from' => $from,
            'to' => $to,
            'showReport' => $buildReport,
            'report' => $report,
            'businessName' => $companyBranding['name'] ?? $user->company?->name ?? config('app.name'),
            'businessAddress' => $companyBranding['address'] ?? $branchBranding['address'] ?? $user->company?->address,
            'businessPhone' => $companyBranding['phone'] ?? $branchBranding['phone'] ?? $user->company?->phone,
            'generatedAt' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveOrderHistory(Request $request, bool $buildReport, bool $forPdf = false): array
    {
        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchIdRaw = $request->has('branch_id') ? $request->input('branch_id') : $user->branch_id;
        $branchId = ($branchIdRaw !== null && $branchIdRaw !== '') ? (int) $branchIdRaw : null;
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));

        $filters = [
            'branch_id' => $branchId,
            'from' => $from,
            'to' => $to,
            'customer_id' => $request->get('customer_id'),
            'waiter_id' => $request->get('waiter_id'),
            'delivery_rider_id' => $request->get('delivery_rider_id'),
            'type' => $request->get('type'),
            'order_number' => $request->get('order_number'),
        ];

        $selectedBranch = $branchId
            ? ($availableBranches->firstWhere('id', $branchId) ?? $availableBranches->first())
            : null;

        $customers = OrderHistoryReport::customersForFilter($user);
        $staff = OrderHistoryReport::staffForFilter($user, $branchId, $availableBranches);

        $orders = null;
        $ordersForPdf = collect();
        $summary = null;
        $period = OrderHistoryReport::periodMeta($from, $to);

        if ($buildReport) {
            $baseQuery = OrderHistoryReport::baseQuery($user, $filters);
            $summary = OrderHistoryReport::summarizeFromQuery($baseQuery);

            if ($forPdf) {
                $ordersForPdf = (clone $baseQuery)->limit(OrderHistoryReport::PDF_LIMIT)->get();
            } else {
                $orders = (clone $baseQuery)
                    ->paginate(OrderHistoryReport::WEB_PER_PAGE)
                    ->withQueryString()
                    ->withPath(route('reports.index'));
            }
        }

        $branding = CompanyReceiptBrandingService::get($user->company);
        $companyBranding = $branding['company'] ?? [];
        $branchBranding = $branchId && isset($branding['branches'][(int) $branchId])
            ? $branding['branches'][(int) $branchId]
            : null;

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'customers' => $customers,
            'staff' => $staff,
            'showReport' => $buildReport,
            'orders' => $orders,
            'ordersForPdf' => $ordersForPdf,
            'summary' => $summary,
            'period' => $period,
            'businessName' => $companyBranding['name'] ?? $user->company?->name ?? config('app.name'),
            'businessAddress' => $companyBranding['address'] ?? $branchBranding['address'] ?? $user->company?->address,
            'businessPhone' => $companyBranding['phone'] ?? $branchBranding['phone'] ?? $user->company?->phone,
            'generatedAt' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function orderHistoryFilterParams(Request $request): array
    {
        return $request->only([
            'branch_id',
            'from',
            'to',
            'customer_id',
            'waiter_id',
            'delivery_rider_id',
            'type',
            'order_number',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvePeriodClosing(Request $request, string $mode, bool $buildReport): array
    {
        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchIdRaw = $request->has('branch_id') ? $request->input('branch_id') : $user->branch_id;
        $branchId = ($branchIdRaw !== null && $branchIdRaw !== '') ? (int) $branchIdRaw : null;
        $selectedBranch = $branchId
            ? ($availableBranches->firstWhere('id', $branchId) ?? $availableBranches->first())
            : null;

        $weekOf = $request->get('week_of', PeriodClosingReport::defaultWeekOf($branchId, $user));
        $weekCount = max(1, min(PeriodClosingReport::MAX_WEEKS, (int) $request->get('week_count', 1)));
        $month = $request->get('month', local_now($branchId)->format('Y-m'));

        $report = null;

        if ($buildReport) {
            if ($mode === 'weekly') {
                $request->validate([
                    'week_of' => 'required|date',
                    'week_count' => 'required|integer|min:1|max:'.PeriodClosingReport::MAX_WEEKS,
                ]);
                $periods = PeriodClosingReport::resolveWeeklyPeriods($weekOf, $weekCount, $branchId, $user);
            } else {
                $request->validate([
                    'month' => ['required', 'date_format:Y-m'],
                ]);
                $periods = PeriodClosingReport::resolveMonthlyPeriod($month, $branchId);
            }

            $report = PeriodClosingReport::build($user, $branchId, $periods);
        }

        $branding = CompanyReceiptBrandingService::get($user->company);
        $companyBranding = $branding['company'] ?? [];
        $branchBranding = $branchId && isset($branding['branches'][(int) $branchId])
            ? $branding['branches'][(int) $branchId]
            : null;

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'showReport' => $buildReport && $report !== null,
            'report' => $report,
            'week_of' => $weekOf,
            'week_count' => $weekCount,
            'month' => $month,
            'week_starts_on' => PeriodClosingReport::weekStartsOn($user),
            'businessName' => $companyBranding['name'] ?? $user->company?->name ?? config('app.name'),
            'businessAddress' => $companyBranding['address'] ?? $branchBranding['address'] ?? $user->company?->address,
            'businessPhone' => $companyBranding['phone'] ?? $branchBranding['phone'] ?? $user->company?->phone,
            'generatedAt' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function periodClosingFilterParams(Request $request, string $mode): array
    {
        $params = ['branch_id'];

        if ($mode === 'weekly') {
            return array_merge($params, $request->only(['week_of', 'week_count']));
        }

        return array_merge($params, $request->only(['month']));
    }

    /**
     * FOC (complimentary) orders for a date range.
     */
    public function foc(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.foc'), 403);

        return $this->redirectToHub($request, 'foc');
    }

    /**
     * All money-source transactions (in & out) for a date range.
     */
    public function transactionsByMoneySource(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.transactions-by-money-source'), 403);

        return $this->redirectToHub($request, 'transactions-by-money-source');
    }

    public function paymentMethods(Request $request): RedirectResponse
    {
        return $this->redirectToHub($request, 'payment-methods');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPaymentMethodsReport(Request $request): array
    {
        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));

        $query = Order::where('status', 'completed')
            ->with('moneySource');
        $this->applyCreatedAtDateRange($query, $from, $to, $branchId);
        $this->applyBranchScope($query, $user, $branchId);

        $bySource = $query->get()
            ->groupBy('money_source_id')
            ->map(function ($orders, $sourceId) {
                $first = $orders->first();
                $sourceName = $first->moneySource ? $first->moneySource->name : (is_numeric($sourceId) ? 'ID '.$sourceId : 'Unknown');

                return [
                    'name' => $sourceName,
                    'order_count' => $orders->count(),
                    'revenue' => $orders->sum('total_amount'),
                ];
            })
            ->values()
            ->sortByDesc('revenue')
            ->values();

        $totalRevenue = $bySource->sum('revenue');
        $selectedBranch = $availableBranches->firstWhere('id', (int) $branchId) ?? $availableBranches->first();

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'from' => $from,
            'to' => $to,
            'bySource' => $bySource,
            'totalRevenue' => $totalRevenue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildZReportList(Request $request): array
    {
        $user = Auth::user();
        $availableBranches = $this->getAvailableBranches($user);
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->toDateString());
        $to = $request->get('to', local_today($branchId));
        $perPage = ListingPerPage::fromRequest($request);

        $query = Shift::with(['branch', 'openedBy', 'closedBy'])
            ->whereBetween('shift_date', [$from, $to])
            ->latest('shift_date')
            ->latest('opened_at');

        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        } elseif ($user->isCompanyAdmin() && $user->company_id) {
            $query->where('company_id', $user->company_id);
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        } else {
            $branchIds = $user->branches()->pluck('branches.id')->toArray();
            if (empty($branchIds) && $user->branch_id) {
                $branchIds = [$user->branch_id];
            }
            if (! empty($branchIds)) {
                $query->whereIn('branch_id', $branchIds);
            }
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        }

        $shifts = $query->paginate($perPage);
        $selectedBranch = $availableBranches->firstWhere('id', (int) $branchId) ?? $availableBranches->first();

        return [
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'from' => $from,
            'to' => $to,
            'shifts' => $shifts,
            'perPage' => $perPage,
        ];
    }

    public function dailyPdf(Request $request): Response
    {
        $ctx = $this->buildDailyReport($request);

        return $this->tablePdfDownload(
            'Daily Sales',
            ['Date', 'Orders', 'Revenue'],
            $ctx['daily']->map(fn ($row) => [
                format_date($row->date),
                number_format($row->order_count),
                number_format($row->revenue, 2),
            ])->all(),
            $ctx,
            sprintf('daily-sales-%s_%s.pdf', $ctx['from'], $ctx['to'])
        );
    }

    public function dailyExcel(Request $request): StreamedResponse
    {
        $ctx = $this->buildDailyReport($request);

        return $this->tableExcelDownload(
            'Daily Sales',
            ['Date', 'Orders', 'Revenue'],
            $ctx['daily']->map(fn ($row) => [
                format_date($row->date),
                $row->order_count,
                $row->revenue,
            ])->all(),
            $ctx,
            sprintf('daily-sales-%s_%s.xlsx', $ctx['from'], $ctx['to'])
        );
    }

    public function topSellingPdf(Request $request): Response
    {
        $ctx = $this->buildTopSellingReport($request);

        return $this->tablePdfDownload(
            'Top Selling Items',
            ['#', 'Item', 'Quantity', 'Revenue'],
            collect($ctx['items'])->values()->map(fn ($row, $i) => [
                $i + 1,
                $row->item_name,
                number_format($row->total_quantity, 2),
                number_format($row->total_revenue, 2),
            ])->all(),
            $ctx,
            sprintf('top-selling-%s_%s.pdf', $ctx['from'], $ctx['to'])
        );
    }

    public function topSellingExcel(Request $request): StreamedResponse
    {
        $ctx = $this->buildTopSellingReport($request);

        return $this->tableExcelDownload(
            'Top Selling Items',
            ['#', 'Item', 'Quantity', 'Revenue'],
            collect($ctx['items'])->values()->map(fn ($row, $i) => [
                $i + 1,
                $row->item_name,
                $row->total_quantity,
                $row->total_revenue,
            ])->all(),
            $ctx,
            sprintf('top-selling-%s_%s.xlsx', $ctx['from'], $ctx['to'])
        );
    }

    public function paymentMethodsPdf(Request $request): Response
    {
        $ctx = $this->buildPaymentMethodsReport($request);

        return $this->tablePdfDownload(
            'Payment Methods',
            ['Payment Source', 'Orders', 'Revenue', '% of Total'],
            $ctx['bySource']->map(fn ($row) => [
                $row['name'],
                number_format($row['order_count']),
                number_format($row['revenue'], 2),
                $ctx['totalRevenue'] > 0 ? number_format(($row['revenue'] / $ctx['totalRevenue']) * 100, 1).'%' : '0%',
            ])->all(),
            $ctx,
            sprintf('payment-methods-%s_%s.pdf', $ctx['from'], $ctx['to'])
        );
    }

    public function paymentMethodsExcel(Request $request): StreamedResponse
    {
        $ctx = $this->buildPaymentMethodsReport($request);

        return $this->tableExcelDownload(
            'Payment Methods',
            ['Payment Source', 'Orders', 'Revenue', '% of Total'],
            $ctx['bySource']->map(fn ($row) => [
                $row['name'],
                $row['order_count'],
                $row['revenue'],
                $ctx['totalRevenue'] > 0 ? round(($row['revenue'] / $ctx['totalRevenue']) * 100, 1) : 0,
            ])->all(),
            $ctx,
            sprintf('payment-methods-%s_%s.xlsx', $ctx['from'], $ctx['to'])
        );
    }

    public function salesPdf(Request $request): Response
    {
        abort_unless(
            Auth::user()->hasAppPermission('reports.sales')
            || Auth::user()->hasAppPermission('reports.sales-by-category'),
            403
        );
        $ctx = $this->buildSalesReport($request);

        return $this->tablePdfDownload(
            'Sales by Category',
            ['Category', 'Orders', 'Qty sold', 'Sales'],
            $ctx['categoryRows']->map(fn ($row) => [
                $row['category_label'],
                number_format($row['order_count']),
                number_format($row['quantity'], 2),
                number_format($row['sales'], 2),
            ])->all(),
            $ctx,
            sprintf('sales-%s_%s.pdf', $ctx['from'], $ctx['to'])
        );
    }

    public function salesExcel(Request $request): StreamedResponse
    {
        abort_unless(
            Auth::user()->hasAppPermission('reports.sales')
            || Auth::user()->hasAppPermission('reports.sales-by-category'),
            403
        );
        $ctx = $this->buildSalesReport($request);

        return $this->tableExcelDownload(
            'Sales by Category',
            ['Category', 'Orders', 'Qty sold', 'Sales'],
            $ctx['categoryRows']->map(fn ($row) => [
                $row['category_label'],
                $row['order_count'],
                $row['quantity'],
                $row['sales'],
            ])->all(),
            $ctx,
            sprintf('sales-%s_%s.xlsx', $ctx['from'], $ctx['to'])
        );
    }

    public function salesByItemPdf(Request $request): Response|RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.sales-by-item'), 403);
        $data = $this->salesByItemExportData($request);
        if ($data === null) {
            return redirect()->route('reports.index', array_merge($request->query(), ['report' => 'sales-by-item']))
                ->with('error', 'Select at least one category or menu item.');
        }

        return $this->tablePdfDownload(
            'Sales by Item',
            ['Order #', 'Date', 'Type', 'Customer', 'Matched qty', 'Matched sales', 'Order total'],
            $data['rows'],
            $data['ctx'],
            sprintf('sales-by-item-%s_%s.pdf', $data['ctx']['from'], $data['ctx']['to'])
        );
    }

    public function salesByItemExcel(Request $request): StreamedResponse|RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.sales-by-item'), 403);
        $data = $this->salesByItemExportData($request);
        if ($data === null) {
            return redirect()->route('reports.index', array_merge($request->query(), ['report' => 'sales-by-item']))
                ->with('error', 'Select at least one category or menu item.');
        }

        return $this->tableExcelDownload(
            'Sales by Item',
            ['Order #', 'Date', 'Type', 'Customer', 'Matched qty', 'Matched sales', 'Order total'],
            $data['rows'],
            $data['ctx'],
            sprintf('sales-by-item-%s_%s.xlsx', $data['ctx']['from'], $data['ctx']['to'])
        );
    }

    public function focPdf(Request $request): Response
    {
        abort_unless(Auth::user()->hasAppPermission('reports.foc'), 403);
        $user = Auth::user();
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));
        $report = FocReport::build($user, $branchId ? (int) $branchId : null, $from, $to);
        $ctx = ['from' => $from, 'to' => $to, 'selectedBranch' => $this->getAvailableBranches($user)->firstWhere('id', (int) $branchId)];

        return $this->tablePdfDownload(
            'FOC Report',
            ['Date', 'Order', 'Branch', 'Type', 'Customer', 'By', 'Items', 'Total'],
            collect($report['rows'])->map(fn ($row) => [
                $row['date'],
                $row['order_number'],
                $row['branch'],
                $row['type_label'],
                $row['customer'],
                $row['cashier'],
                number_format($row['item_count']),
                number_format($row['total_amount'], 2),
            ])->all(),
            $ctx,
            sprintf('foc-%s_%s.pdf', $from, $to)
        );
    }

    public function focExcel(Request $request): StreamedResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.foc'), 403);
        $user = Auth::user();
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));
        $report = FocReport::build($user, $branchId ? (int) $branchId : null, $from, $to);
        $ctx = ['from' => $from, 'to' => $to, 'selectedBranch' => $this->getAvailableBranches($user)->firstWhere('id', (int) $branchId)];

        return $this->tableExcelDownload(
            'FOC Report',
            ['Date', 'Order', 'Branch', 'Type', 'Customer', 'By', 'Items', 'Total'],
            collect($report['rows'])->map(fn ($row) => [
                $row['date'],
                $row['order_number'],
                $row['branch'],
                $row['type_label'],
                $row['customer'],
                $row['cashier'],
                $row['item_count'],
                $row['total_amount'],
            ])->all(),
            $ctx,
            sprintf('foc-%s_%s.xlsx', $from, $to)
        );
    }

    public function transactionsByMoneySourcePdf(Request $request): Response
    {
        abort_unless(Auth::user()->hasAppPermission('reports.transactions-by-money-source'), 403);
        $data = $this->transactionsExportData($request);

        return $this->tablePdfDownload(
            'Transactions by Money Source',
            ['Date', 'Money source', 'Type', 'Amount', 'Reference', 'Account', 'Branch', 'By', 'Notes'],
            $data['rows'],
            $data['ctx'],
            sprintf('transactions-%s_%s.pdf', $data['ctx']['from'], $data['ctx']['to'])
        );
    }

    public function transactionsByMoneySourceExcel(Request $request): StreamedResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.transactions-by-money-source'), 403);
        $data = $this->transactionsExportData($request);

        return $this->tableExcelDownload(
            'Transactions by Money Source',
            ['Date', 'Money source', 'Type', 'Amount', 'Reference', 'Account', 'Branch', 'By', 'Notes'],
            $data['rows'],
            $data['ctx'],
            sprintf('transactions-%s_%s.xlsx', $data['ctx']['from'], $data['ctx']['to'])
        );
    }

    public function grossMarginPdf(Request $request): Response
    {
        $built = GrossMarginReport::build($request);
        $ctx = ['from' => null, 'to' => null, 'selectedBranch' => null];

        return $this->tablePdfDownload(
            'Gross Margin',
            ['Item', 'Category', 'Type', 'Sale Price', 'Cost', 'Gross Margin', 'Margin %', 'Status'],
            $built['rows']->map(fn ($row) => [
                $row['menu_item']->name,
                $row['category_name'] ?: '—',
                ucfirst($row['menu_item']->type),
                number_format($row['price'], 2),
                number_format($row['cost'], 2),
                number_format($row['margin'], 2),
                $row['margin_percent'] !== null ? number_format($row['margin_percent'], 1).'%' : '—',
                $row['menu_item']->is_available ? 'Available' : 'Unavailable',
            ])->all(),
            $ctx,
            'gross-margin.pdf'
        );
    }

    public function grossMarginExcel(Request $request): StreamedResponse
    {
        $built = GrossMarginReport::build($request);
        $ctx = ['from' => null, 'to' => null, 'selectedBranch' => null];

        return $this->tableExcelDownload(
            'Gross Margin',
            ['Item', 'Category', 'Type', 'Sale Price', 'Cost', 'Gross Margin', 'Margin %', 'Status'],
            $built['rows']->map(fn ($row) => [
                $row['menu_item']->name,
                $row['category_name'] ?: '—',
                ucfirst($row['menu_item']->type),
                $row['price'],
                $row['cost'],
                $row['margin'],
                $row['margin_percent'],
                $row['menu_item']->is_available ? 'Available' : 'Unavailable',
            ])->all(),
            $ctx,
            'gross-margin.xlsx'
        );
    }

    public function ingredientLedgerPdf(Request $request): Response|RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.consumption'), 403);
        $data = $this->ingredientLedgerExportData($request);
        if ($data === null) {
            return redirect()->route('reports.index', array_merge($request->query(), ['report' => 'ingredient-ledger']))
                ->with('error', 'Select an ingredient to export.');
        }

        return $this->tablePdfDownload(
            'Ingredient Ledger',
            ['When', 'Type', 'Reference', 'Detail', 'Qty change', 'Balance', 'Line cost', 'By'],
            $data['rows'],
            $data['ctx'],
            sprintf('ingredient-ledger-%s_%s.pdf', $data['ctx']['from'], $data['ctx']['to'])
        );
    }

    public function ingredientLedgerExcel(Request $request): StreamedResponse|RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('reports.consumption'), 403);
        $data = $this->ingredientLedgerExportData($request);
        if ($data === null) {
            return redirect()->route('reports.index', array_merge($request->query(), ['report' => 'ingredient-ledger']))
                ->with('error', 'Select an ingredient to export.');
        }

        return $this->tableExcelDownload(
            'Ingredient Ledger',
            ['When', 'Type', 'Reference', 'Detail', 'Qty change', 'Balance', 'Line cost', 'By'],
            $data['rows'],
            $data['ctx'],
            sprintf('ingredient-ledger-%s_%s.xlsx', $data['ctx']['from'], $data['ctx']['to'])
        );
    }

    public function zReportListPdf(Request $request): Response
    {
        $ctx = $this->buildZReportList($request);
        $allShifts = $this->buildZReportListAll($request);

        return $this->tablePdfDownload(
            'Z Report — Shifts',
            ['Branch', 'Shift Date', 'Cashier', 'Opened', 'Closed', 'Status', 'Cash Diff.'],
            $allShifts->map(fn ($shift) => [
                $shift->branch->name,
                format_date($shift->shift_date),
                $shift->openedBy->name,
                $shift->opened_at->format('Y-m-d H:i'),
                $shift->closed_at?->format('Y-m-d H:i') ?? '—',
                $shift->status === 'active' ? 'Active' : 'Closed',
                $shift->status === 'closed' ? number_format($shift->cash_difference, 2) : '—',
            ])->all(),
            $ctx,
            sprintf('z-report-%s_%s.pdf', $ctx['from'], $ctx['to'])
        );
    }

    public function zReportListExcel(Request $request): StreamedResponse
    {
        $ctx = $this->buildZReportList($request);
        $allShifts = $this->buildZReportListAll($request);

        return $this->tableExcelDownload(
            'Z Report — Shifts',
            ['Branch', 'Shift Date', 'Cashier', 'Opened', 'Closed', 'Status', 'Cash Diff.'],
            $allShifts->map(fn ($shift) => [
                $shift->branch->name,
                format_date($shift->shift_date),
                $shift->openedBy->name,
                $shift->opened_at->format('Y-m-d H:i'),
                $shift->closed_at?->format('Y-m-d H:i') ?? '—',
                $shift->status === 'active' ? 'Active' : 'Closed',
                $shift->status === 'closed' ? $shift->cash_difference : null,
            ])->all(),
            $ctx,
            sprintf('z-report-%s_%s.xlsx', $ctx['from'], $ctx['to'])
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Shift>
     */
    protected function buildZReportListAll(Request $request)
    {
        $user = Auth::user();
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->toDateString());
        $to = $request->get('to', local_today($branchId));

        $query = Shift::with(['branch', 'openedBy', 'closedBy'])
            ->whereBetween('shift_date', [$from, $to])
            ->latest('shift_date')
            ->latest('opened_at');

        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        } elseif ($user->isCompanyAdmin() && $user->company_id) {
            $query->where('company_id', $user->company_id);
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        } else {
            $branchIds = $user->branches()->pluck('branches.id')->toArray();
            if (empty($branchIds) && $user->branch_id) {
                $branchIds = [$user->branch_id];
            }
            if (! empty($branchIds)) {
                $query->whereIn('branch_id', $branchIds);
            }
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        }

        return $query->get();
    }

    public function profitLossExcel(Request $request): StreamedResponse|RedirectResponse
    {
        $ctx = $this->resolveProfitLoss($request, buildReport: true);
        if (! $ctx['report']) {
            return redirect()->route('reports.index', array_merge($request->only(['branch_id', 'from', 'to']), ['report' => 'profit-loss']))
                ->with('error', 'Generate the report before exporting to Excel.');
        }

        $report = $ctx['report'];
        $rows = [
            ['Gross sales', $report['revenue']['gross_sales']],
            ['Less: Discounts', -$report['revenue']['discounts']],
            ['Less: Refunds', -$report['revenue']['refunds']],
            ['Net sales', $report['revenue']['net_sales']],
            ['COGS (sold)', $report['cogs']['sold_cost']],
            ['Gross profit', $report['cogs']['gross_profit']],
            ['Operating expenses', $report['operating_expenses']['total']],
            ['Net profit', $report['net_profit']],
        ];

        return $this->tableExcelDownload(
            'Profit & Loss',
            ['Line', 'Amount'],
            $rows,
            $ctx,
            sprintf('profit-loss-%s_%s.xlsx', $ctx['from'], $ctx['to'])
        );
    }

    public function orderHistoryExcel(Request $request): StreamedResponse|RedirectResponse
    {
        $ctx = $this->resolveOrderHistory($request, buildReport: true, forPdf: true);
        if (! $ctx['showReport']) {
            return redirect()->route('reports.index', array_merge($this->orderHistoryFilterParams($request), ['report' => 'order-history']))
                ->with('error', 'Generate the report before exporting to Excel.');
        }

        $rows = $ctx['ordersForPdf']->map(fn ($order) => [
            $order->order_number,
            OrderHistoryReport::formatOrderDate($order),
            OrderHistoryReport::typeLabel($order->type),
            OrderHistoryReport::customerDisplayName($order),
            $order->waiter?->name ?? '—',
            $order->deliveryRider?->name ?? '—',
            $order->table?->name ?? '—',
            $order->items_count,
            $order->total_amount,
            ucfirst(str_replace('_', ' ', $order->status)),
        ])->all();

        return $this->tableExcelDownload(
            'Order History',
            ['Order #', 'Date', 'Type', 'Customer', 'Waiter', 'Rider', 'Table', 'Items', 'Total', 'Status'],
            $rows,
            $ctx,
            sprintf('order-history-%s_%s.xlsx', $ctx['from'], $ctx['to'])
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<scalar|null>>  $rows
     * @param  array<string, mixed>  $ctx
     */
    protected function tablePdfDownload(string $title, array $headers, array $rows, array $ctx, string $filename): Response
    {
        $user = Auth::user();
        $branchLabel = $ctx['selectedBranch']?->name ?? null;

        return Pdf::loadView('reports.table-pdf', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'from' => $ctx['from'] ?? null,
            'to' => $ctx['to'] ?? null,
            'branchLabel' => $branchLabel,
            'businessName' => $user->company?->name ?? config('app.name'),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait')->download($filename);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<scalar|null>>  $rows
     * @param  array<string, mixed>  $ctx
     */
    protected function tableExcelDownload(string $title, array $headers, array $rows, array $ctx, string $filename): StreamedResponse
    {
        $user = Auth::user();
        $branchLabel = $ctx['selectedBranch']?->name ?? null;

        return (new ReportTableExcelExport(
            businessName: $user->company?->name ?? config('app.name'),
            title: $title,
            branchLabel: $branchLabel,
            from: $ctx['from'] ?? null,
            to: $ctx['to'] ?? null,
            generatedAt: now(),
            headers: $headers,
            rows: $rows,
        ))->download($filename);
    }

    /**
     * @return array{rows: list<list<string>>, ctx: array{from: string, to: string, selectedBranch: mixed}}|null
     */
    protected function salesByItemExportData(Request $request): ?array
    {
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
            MenuItem::query()->pluck('id')
        );

        $resolvedMenuItemIds = $menuItemIds;
        if ($resolvedMenuItemIds === [] && $categoryIds !== []) {
            $resolvedMenuItemIds = MenuItem::withTrashed()->whereIn('category_id', $categoryIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if ($resolvedMenuItemIds === []) {
            return null;
        }

        $orderQuery = Order::query()->where('status', 'completed');
        $this->applyCreatedAtDateRange($orderQuery, $from, $to, $branchId);
        $this->applyBranchScope($orderQuery, $user, $branchId);

        $lineQuery = OrderItem::query()
            ->whereIn('order_id', (clone $orderQuery)->select('orders.id'))
            ->whereIn('menu_item_id', $resolvedMenuItemIds);
        $quantitySql = 'CASE WHEN quantity > COALESCE(quantity_refunded, 0) THEN quantity - COALESCE(quantity_refunded, 0) ELSE 0 END';
        $quantitySubquery = OrderItem::query()
            ->selectRaw("COALESCE(SUM({$quantitySql}), 0)")
            ->whereColumn('order_items.order_id', 'orders.id')
            ->whereIn('menu_item_id', $resolvedMenuItemIds);
        $salesSubquery = OrderItem::query()
            ->selectRaw('COALESCE(SUM(total_price), 0)')
            ->whereColumn('order_items.order_id', 'orders.id')
            ->whereIn('menu_item_id', $resolvedMenuItemIds);

        $orders = (clone $orderQuery)
            ->whereIn('orders.id', (clone $lineQuery)->select('order_id')->distinct())
            ->addSelect(['matched_quantity' => $quantitySubquery, 'matched_sales' => $salesSubquery])
            ->with(['customer'])
            ->orderByDesc('orders.created_at')
            ->get();

        return [
            'rows' => $orders->map(fn ($order) => [
                $order->order_number,
                OrderHistoryReport::formatOrderDate($order),
                OrderHistoryReport::typeLabel($order->type),
                OrderHistoryReport::customerDisplayName($order),
                number_format((float) $order->matched_quantity, 2),
                number_format((float) $order->matched_sales, 2),
                number_format((float) $order->total_amount, 2),
            ])->all(),
            'ctx' => [
                'from' => $from,
                'to' => $to,
                'selectedBranch' => $availableBranches->firstWhere('id', (int) $branchId),
            ],
        ];
    }

    /**
     * @return array{rows: list<list<string|float>>, ctx: array{from: string, to: string, selectedBranch: mixed}}
     */
    protected function transactionsExportData(Request $request): array
    {
        $user = Auth::user();
        $branchId = $request->get('branch_id', $user->branch_id ?: current_branch_id());
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));
        $moneySourceIds = collect($request->input('money_source_ids', []))
            ->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values()->all();
        $type = in_array($request->get('type'), ['in', 'out'], true) ? $request->get('type') : null;
        $report = TransactionsByMoneySourceReport::build($user, $branchId ? (int) $branchId : null, $from, $to, $moneySourceIds, $type, 10000);

        return [
            'rows' => collect($report['rows']->items())->map(fn ($row) => [
                $row['date'],
                $row['money_source'],
                $row['type'] === 'in' ? 'In' : 'Out',
                $row['amount'],
                $row['reference_label'],
                $row['account'],
                $row['branch'],
                $row['created_by'],
                $row['notes'],
            ])->all(),
            'ctx' => [
                'from' => $from,
                'to' => $to,
                'selectedBranch' => $this->getAvailableBranches($user)->firstWhere('id', (int) $branchId),
            ],
        ];
    }

    /**
     * @return array{rows: list<list<string>>, ctx: array{from: string, to: string, selectedBranch: mixed}}|null
     */
    protected function ingredientLedgerExportData(Request $request): ?array
    {
        $user = Auth::user();
        $branchId = $request->get('branch_id', $user->branch_id);
        $from = $request->get('from', local_now($branchId)->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', local_today($branchId));
        $ingredientId = $request->filled('ingredient_id') ? (int) $request->get('ingredient_id') : null;
        if (! $ingredientId) {
            return null;
        }

        $ledger = IngredientLedgerReport::build($user, $ingredientId, $branchId ? (int) $branchId : null, $from, $to);
        if (! $ledger) {
            return null;
        }

        return [
            'rows' => collect($ledger['rows'])->map(fn ($row) => [
                $row['occurred_at'] ? format_datetime($row['occurred_at'], $branchId) : '—',
                $row['kind_label'],
                $row['reference_label'] ?: '—',
                $row['detail'] ?: '—',
                ($row['signed_qty'] >= 0 ? '+' : '').number_format($row['signed_qty'], 2),
                number_format($row['balance_qty'], 2),
                number_format($row['line_cost'], 2),
                $row['created_by'] ?: '—',
            ])->all(),
            'ctx' => [
                'from' => $from,
                'to' => $to,
                'selectedBranch' => $this->getAvailableBranches($user)->firstWhere('id', (int) $branchId),
            ],
        ];
    }

    protected function applyCreatedAtDateRange($query, string $from, string $to, $branchId): void
    {
        tz()->applyBusinessDateRange($query, $from, $to, $branchId ? (int) $branchId : null);
    }

    protected function getAvailableBranches($user)
    {
        if ($user->isSuperAdmin()) {
            return Branch::where('status', 'active')->orderBy('name')->get();
        }
        if ($user->isCompanyAdmin() && $user->company_id) {
            return Branch::where('company_id', $user->company_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        }
        $branches = $user->branches()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        if ($branches->isEmpty() && $user->branch_id) {
            $branch = Branch::where('id', $user->branch_id)->where('status', 'active')->first();
            if ($branch) {
                return collect([$branch]);
            }
        }
        return $branches;
    }

    protected function applyBranchScope($query, $user, $branchId)
    {
        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
            return;
        }
        if ($user->isCompanyAdmin() && $user->company_id) {
            $query->where('company_id', $user->company_id);
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
            return;
        }
        $query->where('company_id', $user->company_id);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $branchIds = $user->branches()->where('status', 'active')->pluck('branches.id')->toArray();
            if (!empty($branchIds)) {
                $query->whereIn('branch_id', $branchIds);
            } elseif ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }
        }
    }
}
