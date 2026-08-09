<?php

namespace App\Http\Controllers;

use App\Events\OrderCreated;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Floor;
use App\Models\KitchenKot;
use App\Models\MenuItem;
use App\Models\MoneySource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\Tax;
use App\Models\User;
use App\Models\Company;
use App\Support\CurrentShift;
use App\Support\OrderWorkflow;
use App\Services\ActivityLogger;
use App\Services\CompanyAddonService;
use App\Services\CompanyReceiptBrandingService;
use App\Services\InventoryService;
use App\Services\KitchenKotService;
use App\Services\OrderDeleteService;
use App\Services\OrderTrackingService;
use App\Services\PosAddonService;
use App\Services\PosCreditService;
use App\Services\PosPrintReadinessService;
use App\Services\PosSplitPaymentService;
use App\Services\PrintJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PosController extends Controller
{
    public function __construct(
        protected PosCreditService $posCreditService,
        protected PosSplitPaymentService $posSplitPaymentService,
        protected KitchenKotService $kitchenKotService,
        protected PrintJobService $printJobService,
        protected PosPrintReadinessService $printReadiness,
        protected CompanyAddonService $companyAddonService,
        protected OrderTrackingService $orderTrackingService,
        protected OrderDeleteService $orderDeleteService,
    ) {}

    /**
     * Display the POS screen.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get branches available to the user
        if ($user->isSuperAdmin()) {
            // Super admin can see all branches
            $branches = Branch::where('status', 'active')
                ->with('company:id,timezone')
                ->orderBy('name')
                ->get();
        } elseif ($user->isCompanyAdmin() && $user->company_id) {
            $branchId = current_branch_id();
            if ($branchId) {
                $branch = Branch::where('id', $branchId)
                    ->where('status', 'active')
                    ->with('company:id,timezone')
                    ->first();
                $branches = $branch ? collect([$branch]) : collect();
            } else {
                $branches = collect();
            }
        } else {
            // Regular users can only see their allocated branches
            $branches = $user->branches()
                ->where('status', 'active')
                ->with('company:id,timezone')
                ->orderBy('name')
                ->get();

            // Fallback to single branch_id if no many-to-many relationship
            if ($branches->isEmpty() && $user->branch_id) {
                $branch = Branch::where('id', $user->branch_id)
                    ->where('status', 'active')
                    ->with('company:id,timezone')
                    ->first();
                if ($branch) {
                    $branches = collect([$branch]);
                }
            }
        }

        // Selected branch follows topbar (company admin) or request (super admin)
        $selectedBranchId = $user->isSuperAdmin()
            ? $request->get('branch_id', $user->branch_id)
            : (current_branch_id() ?? $user->branch_id);

        // Get all active menu items (variants from menu_item_variant table via relationship, not menu_items.variants column)
        $menuItems = MenuItem::query()
            ->select(['id', 'name', 'price', 'image', 'category_id', 'sku', 'is_available'])
            ->where('is_available', true)
            ->with(['category', 'productAddons', 'variants'])
            ->orderBy('name')
            ->get();

        $categoryIdsWithItems = $menuItems->pluck('category_id')->filter()->unique()->values();

        // Only categories that have at least one available menu item (directly or via subcategories)
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => function ($query) use ($categoryIdsWithItems) {
                $query->where('is_active', true)
                    ->whereIn('id', $categoryIdsWithItems)
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }])
            ->where(function ($query) use ($categoryIdsWithItems) {
                $query->whereIn('id', $categoryIdsWithItems)
                    ->orWhereHas('children', function ($childQuery) use ($categoryIdsWithItems) {
                        $childQuery->where('is_active', true)
                            ->whereIn('id', $categoryIdsWithItems);
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $shownCategoryIds = collect();
        $categoryFilterMap = [];

        foreach ($categories as $parent) {
            $idsForParent = [];
            if ($categoryIdsWithItems->contains($parent->id)) {
                $idsForParent[] = $parent->id;
            }
            $idsForParent = array_values(array_unique(array_merge(
                $idsForParent,
                $parent->children->pluck('id')->all()
            )));

            if ($idsForParent !== []) {
                $categoryFilterMap[$parent->id] = $idsForParent;
                $shownCategoryIds->push($parent->id);
            }

            foreach ($parent->children as $child) {
                $categoryFilterMap[$child->id] = [$child->id];
                $shownCategoryIds->push($child->id);
            }
        }

        // Subcategories with items whose parent is missing or inactive
        $orphanCategories = Category::where('is_active', true)
            ->whereIn('id', $categoryIdsWithItems)
            ->whereNotNull('parent_id')
            ->whereNotIn('id', $shownCategoryIds->unique())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($orphanCategories as $orphan) {
            $categoryFilterMap[$orphan->id] = [$orphan->id];
        }

        Log::info('POS: menu items loaded', [
            'count' => $menuItems->count(),
            'ids' => $menuItems->pluck('id')->toArray(),
            'names' => $menuItems->pluck('name')->toArray(),
        ]);

        // Tables available for seating, or with an unpaid open tab (resume)
        $openTableIds = collect();
        if ($selectedBranchId) {
            $openTableIds = Order::withoutGlobalScopes(['tenant', 'branch'])
                ->where('branch_id', $selectedBranchId)
                ->whereIn('status', $this->posActiveTabStatuses())
                ->where('payment_status', 'unpaid')
                ->whereNotNull('table_id')
                ->pluck('table_id')
                ->unique()
                ->filter();
        }

        $tables = collect();
        if ($selectedBranchId) {
            $tables = Table::withoutGlobalScope('branch')
                ->with('floor')
                ->where('branch_id', $selectedBranchId)
                ->where(function ($q) use ($openTableIds) {
                    $q->where('status', 'available')
                        ->orWhereIn('id', $openTableIds);
                })
                ->where('status', '!=', 'out_of_service')
                ->orderBy('name')
                ->get();
        }

        $floorsJson = collect();
        if ($selectedBranchId) {
            $floors = Floor::withoutGlobalScope('branch')
                ->where('branch_id', $selectedBranchId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $floorsJson = $floors->map(function (Floor $floor) use ($tables) {
                $floorTables = $tables->where('floor_id', $floor->id)->values();

                return [
                    'id' => $floor->id,
                    'name' => $floor->name,
                    'tables' => $floorTables->map(fn (Table $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'capacity' => $t->capacity,
                        'status' => $t->status,
                    ])->all(),
                ];
            })->values();

            $ungrouped = $tables->filter(fn (Table $t) => $t->floor_id === null)->values();
            if ($ungrouped->isNotEmpty()) {
                $floorsJson->push([
                    'id' => null,
                    'name' => 'Other',
                    'tables' => $ungrouped->map(fn (Table $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'capacity' => $t->capacity,
                        'status' => $t->status,
                    ])->all(),
                ]);
            }
        }

        // Get customers (no longer pass full list; POS searches by mobile via AJAX)
        $customers = Customer::where('is_active', true)->orderBy('name')->get();

        $customersJson = collect([]);

        // Get active taxes
        $taxes = Tax::where('is_active', true)->get();

        // Calculate total tax percentage
        $totalTaxPercentage = $taxes->sum('percentage');

        // Get money sources for the selected branch
        $moneySources = collect();
        if ($selectedBranchId) {
            $branch = Branch::find($selectedBranchId);
            if ($branch) {
                // Get money sources assigned to this branch
                $moneySources = MoneySource::forPayments()->where('company_id', $branch->company_id)
                    ->where('active', true)
                    ->whereHas('branches', function ($query) use ($selectedBranchId) {
                        $query->where('branches.id', $selectedBranchId);
                    })
                    ->orderBy('type')
                    ->orderBy('name')
                    ->get();

                // If no money sources for this branch, get all active company money sources
                if ($moneySources->isEmpty()) {
                    $moneySources = MoneySource::forPayments()->where('company_id', $branch->company_id)
                        ->where('active', true)
                        ->orderBy('type')
                        ->orderBy('name')
                        ->get();
                }
            }
        } elseif ($user->company_id) {
            // Fallback to company money sources if no branch selected
            $moneySources = MoneySource::forPayments()->where('company_id', $user->company_id)
                ->where('active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get();
        }

        // Prepare money sources for JavaScript
        $moneySourcesJson = $moneySources->map(function ($source) {
            return [
                'id' => $source->id,
                'name' => $source->name,
                'type' => $source->type,
            ];
        })->values();

        // Prepare menu items for JavaScript: variants from menu_item_variant table (relationship), with option_prices from pivot
        $menuItemsJson = $menuItems->map(function ($item) {
            $variantsRelation = $item->getRelation('variants');
            $hasVariants = $variantsRelation && $variantsRelation->count() > 0;

            $variantsData = null;
            if ($hasVariants) {
                $variantsData = $variantsRelation->map(function ($variant) use ($item) {
                    $rawPrices = $variant->pivot->option_prices ?? null;
                    $optionPrices = is_array($rawPrices) ? $rawPrices : (is_string($rawPrices) ? (json_decode($rawPrices, true) ?? []) : []);
                    if (! is_array($optionPrices)) {
                        $optionPrices = [];
                    }
                    $options = [];
                    // Build from variant->options when present
                    if ($variant->options && is_array($variant->options)) {
                        foreach ($variant->options as $opt) {
                            $optName = is_array($opt) ? ($opt['name'] ?? '') : (is_object($opt) ? ($opt->name ?? '') : '');
                            if ($optName === '') {
                                continue;
                            }
                            $optCode = is_array($opt) ? ($opt['code'] ?? null) : (is_object($opt) ? ($opt->code ?? null) : null);
                            $price = isset($optionPrices[$optName]) ? (float) $optionPrices[$optName] : (float) ($variant->pivot->price ?? $item->price ?? 0);
                            $options[] = [
                                'name' => $optName,
                                'code' => $optCode,
                                'price' => $price,
                            ];
                        }
                    }
                    // Fallback: build options from option_prices keys so POS always gets options when prices exist
                    if (empty($options) && ! empty($optionPrices)) {
                        foreach ($optionPrices as $optName => $price) {
                            if ($optName !== '' && $optName !== null) {
                                $options[] = [
                                    'name' => (string) $optName,
                                    'code' => null,
                                    'price' => (float) $price,
                                ];
                            }
                        }
                    }

                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'options' => $options,
                    ];
                })->filter(function ($v) {
                    return ! empty($v['options']);
                })->values()->toArray();
            }

            $hasVariantsWithOptions = $variantsData && count($variantsData) > 0;

            if ($hasVariants) {
                Log::debug('POS: menu item variants built', [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'has_variants_with_options' => $hasVariantsWithOptions,
                    'variants_count' => $variantsData ? count($variantsData) : 0,
                    'variants_sample' => $variantsData ? array_map(function ($v) {
                        return ['id' => $v['id'], 'name' => $v['name'], 'options_count' => count($v['options'] ?? [])];
                    }, array_slice($variantsData, 0, 2)) : null,
                ]);
            }

            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (float) $item->price,
                'image' => $item->resolvedImageUrl(),
                'category_id' => $item->category_id,
                'sku' => $item->sku ?? '',
                'variants' => $variantsData ?? [],
                'has_variants' => $hasVariantsWithOptions,
                'addons' => $item->productAddons->map(fn ($addon) => $addon->posPayload())->values()->all(),
                'has_addons' => $item->productAddons->count() > 0,
            ];
        })->values();

        Log::info('POS: menuItemsJson prepared', [
            'count' => $menuItemsJson->count(),
            'first_item_keys' => $menuItemsJson->isNotEmpty() ? array_keys($menuItemsJson->first()) : [],
        ]);

        // Active deals (for POS "Deals" section) - loaded via AJAX when user clicks Deals
        // No longer passed to view to reduce initial payload

        // Prepare tables for JavaScript
        $tablesJson = $tables->map(function ($table) {
            return [
                'id' => $table->id,
                'name' => $table->name,
                'slug' => $table->slug,
                'capacity' => $table->capacity,
                'floor_name' => $table->floor?->name,
            ];
        })->values();

        $branchStaffJson = collect();
        if ($selectedBranchId) {
            $branchCompanyId = Branch::find($selectedBranchId)?->company_id ?? $user->company_id;
            if ($branchCompanyId) {
                $branchStaffJson = User::query()
                    ->where('company_id', $branchCompanyId)
                    ->where('status', 'active')
                    ->where(function ($query) use ($selectedBranchId) {
                        $query->where('branch_id', $selectedBranchId)
                            ->orWhereHas('branches', function ($branchQuery) use ($selectedBranchId) {
                                $branchQuery->where('branches.id', $selectedBranchId);
                            });
                    })
                    ->orderBy('name')
                    ->get(['id', 'name', 'type'])
                    ->map(fn (User $staff) => [
                        'id' => $staff->id,
                        'name' => $staff->name,
                        'type' => $staff->type,
                    ])
                    ->values();
            }
        }

        $addonKitchenTracking = false;
        if ($selectedBranchId) {
            $branchCompanyId = Branch::find($selectedBranchId)?->company_id;
            if ($branchCompanyId) {
                $addonKitchenTracking = $this->companyAddonService->kitchenTrackingEnabled(
                    Company::find($branchCompanyId)
                );
            }
        } elseif ($user->company_id) {
            $addonKitchenTracking = $this->companyAddonService->kitchenTrackingEnabled(
                Company::find($user->company_id)
            );
        }

        $companyTimezone = tz()->resolveForCompany();
        $branchTimezonesJson = tz()->branchTimezonesMap($branches);
        $defaultBranchId = $selectedBranchId ?? ($branches->first()?->id);
        $posTimezone = $defaultBranchId
            ? ($branchTimezonesJson[$defaultBranchId] ?? $companyTimezone)
            : $companyTimezone;
        $posToday = tz()->today($defaultBranchId);
        $canPosFoc = $user->hasAppPermission('pos.foc');

        return view('pos.index', compact(
            'categories',
            'orphanCategories',
            'categoryFilterMap',
            'menuItems',
            'menuItemsJson',
            'customers',
            'customersJson',
            'tables',
            'tablesJson',
            'floorsJson',
            'customers',
            'taxes',
            'totalTaxPercentage',
            'branches',
            'selectedBranchId',
            'moneySources',
            'moneySourcesJson',
            'branchStaffJson',
            'addonKitchenTracking',
            'companyTimezone',
            'branchTimezonesJson',
            'posToday',
            'canPosFoc'
        ));
    }

    /**
     * Return active deals as JSON for POS (loaded via AJAX when user clicks Deals section).
     */
    public function deals()
    {
        $deals = Deal::query()
            ->select(['id', 'title', 'price', 'image'])
            ->active()
            ->orderBy('title')
            ->get();
        $dealsJson = $deals->map(function ($deal) {
            return [
                'id' => $deal->id,
                'title' => $deal->title,
                'price' => (float) $deal->price,
                'image' => $deal->image ? asset('storage/'.$deal->image) : null,
            ];
        })->values();

        return response()->json($dealsJson);
    }

    /**
     * Floor/table grid for POS table view (status + open tab summary per table).
     */
    public function tableView(Request $request): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();

        try {
            $request->validate([
                'branch_id' => ['required', 'integer', 'exists:branches,id'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $branchId = (int) $request->input('branch_id');
        if (! in_array($branchId, $this->posAccessibleBranchIds($user), true)) {
            return response()->json(['success' => false, 'message' => 'Branch not accessible.'], 403);
        }

        $tables = Table::withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get();

        $openOrdersByTable = Order::withoutGlobalScopes(['tenant', 'branch'])
            ->where('branch_id', $branchId)
            ->whereIn('status', $this->posActiveTabStatuses())
            ->where('payment_status', 'unpaid')
            ->where('type', 'dine_in')
            ->whereNotNull('table_id')
            ->with(['waiter:id,name'])
            ->withCount([
                'items',
                'kitchenKots as kitchen_kots_count' => fn ($q) => $q->withoutGlobalScopes(['tenant', 'branch']),
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->unique('table_id')
            ->keyBy('table_id');

        $summary = [
            'total' => $tables->count(),
            'available' => 0,
            'open_tab' => 0,
            'occupied' => 0,
            'reserved' => 0,
            'dirty' => 0,
            'out_of_service' => 0,
        ];

        $mapTable = function (Table $table) use ($openOrdersByTable, &$summary): array {
            $openOrder = $openOrdersByTable->get($table->id);
            if ($openOrder) {
                $summary['open_tab']++;
            } elseif ($table->status === 'available') {
                $summary['available']++;
            } elseif (isset($summary[$table->status])) {
                $summary[$table->status]++;
            }

            $openOrderPayload = null;
            if ($openOrder) {
                $openOrderPayload = [
                    'id' => $openOrder->id,
                    'order_number' => $openOrder->order_number,
                    'total_amount' => (float) $openOrder->total_amount,
                    'items_count' => (int) ($openOrder->items_count ?? 0),
                    'payment_status' => $openOrder->payment_status,
                    'updated_at' => $openOrder->updated_at?->toIso8601String(),
                    'customer_name' => $openOrder->customer_name,
                    'kitchen_sent' => $this->orderHasKitchenSlip($openOrder),
                    'waiter' => $openOrder->waiter ? [
                        'id' => $openOrder->waiter->id,
                        'name' => $openOrder->waiter->name,
                    ] : null,
                ];
            }

            return [
                'id' => $table->id,
                'name' => $table->name,
                'capacity' => (int) $table->capacity,
                'status' => $table->status,
                'section' => $table->section,
                'open_order' => $openOrderPayload,
            ];
        };

        $floors = Floor::withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $floorsPayload = $floors->map(function (Floor $floor) use ($tables, $mapTable) {
            return [
                'id' => $floor->id,
                'name' => $floor->name,
                'tables' => $tables->where('floor_id', $floor->id)->values()->map($mapTable)->values()->all(),
            ];
        })->values();

        $ungrouped = $tables->filter(fn (Table $t) => $t->floor_id === null)->values();
        if ($ungrouped->isNotEmpty()) {
            $floorsPayload->push([
                'id' => null,
                'name' => 'Other',
                'tables' => $ungrouped->map($mapTable)->values()->all(),
            ]);
        }

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'floors' => $floorsPayload,
        ]);
    }

    /**
     * Find an unpaid open POS order to resume (by order id, or dine-in table only).
     */
    public function openOrder(Request $request): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();

        try {
            $request->validate([
                'branch_id' => ['required', 'integer', 'exists:branches,id'],
                'order_id' => ['nullable', 'integer', 'exists:orders,id'],
                'table_id' => ['nullable', 'integer', 'exists:tables,id'],
                'type' => ['nullable', 'in:dine_in,takeaway,delivery'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $branchId = (int) $request->input('branch_id');
        if (! in_array($branchId, $this->posAccessibleBranchIds($user), true)) {
            return response()->json(['success' => false, 'message' => 'Branch not accessible.'], 403);
        }

        $tableId = $request->filled('table_id') ? (int) $request->input('table_id') : null;

        $base = Order::withoutGlobalScopes(['tenant', 'branch'])
            ->where('branch_id', $branchId)
            ->whereIn('status', $this->posActiveTabStatuses())
            ->where('payment_status', 'unpaid')
            ->with(['items.menuItem', 'items.deal', 'kitchenKots']);

        if ($request->filled('order_id')) {
            $order = (clone $base)->where('id', (int) $request->input('order_id'))->first();
        } elseif ($tableId) {
            $order = (clone $base)->where('table_id', $tableId)->latest()->first();
        } else {
            $order = null;
        }

        return response()->json([
            'success' => true,
            'order' => $order ? $this->posOrderJson($order) : null,
        ]);
    }

    /**
     * List unpaid open POS orders for the branch (dine-in, takeaway, delivery) for quick resume.
     */
    public function listOpenOrders(Request $request): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();

        try {
            $request->validate([
                'branch_id' => ['required', 'integer', 'exists:branches,id'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $branchId = (int) $request->input('branch_id');
        if (! in_array($branchId, $this->posAccessibleBranchIds($user), true)) {
            return response()->json(['success' => false, 'message' => 'Branch not accessible.'], 403);
        }

        $orders = Order::withoutGlobalScopes(['tenant', 'branch'])
            ->where('branch_id', $branchId)
            ->whereIn('status', $this->posActiveTabStatuses())
            ->where('payment_status', 'unpaid')
            ->whereIn('type', ['dine_in', 'takeaway', 'delivery'])
            ->with(['table:id,name', 'cashier:id,name', 'waiter:id,name', 'deliveryRider:id,name'])
            ->withCount([
                'items',
                'kitchenKots as kitchen_kots_count' => fn ($q) => $q->withoutGlobalScopes(['tenant', 'branch']),
            ])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Order $o) => $this->formatPosOrderListRow($o))
            ->values();

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }

    /**
     * List active orders for a POS channel (dine-in saved tabs, takeaway, or delivery queue).
     */
    public function listChannelOrders(Request $request): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();

        try {
            $request->validate([
                'branch_id' => ['required', 'integer', 'exists:branches,id'],
                'channel' => ['required', 'string', 'in:dine_in,takeaway,delivery'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $branchId = (int) $request->input('branch_id');
        if (! in_array($branchId, $this->posAccessibleBranchIds($user), true)) {
            return response()->json(['success' => false, 'message' => 'Branch not accessible.'], 403);
        }

        $channel = (string) $request->input('channel');

        $query = Order::withoutGlobalScopes(['tenant', 'branch'])
            ->where('branch_id', $branchId)
            ->where('type', $channel);

        if ($channel === 'delivery') {
            $query->whereIn('status', OrderWorkflow::activePosTabStatuses('delivery'))
                ->where('payment_status', '!=', 'paid');
        } else {
            $query->whereIn('status', $this->posActiveTabStatuses())
                ->where('payment_status', 'unpaid');
        }

        $orders = $query
            ->with(['table:id,name', 'cashier:id,name', 'waiter:id,name', 'deliveryRider:id,name'])
            ->withCount([
                'items',
                'kitchenKots as kitchen_kots_count' => fn ($q) => $q->withoutGlobalScopes(['tenant', 'branch']),
            ])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Order $o) => $this->formatPosOrderListRow($o))
            ->values();

        return response()->json([
            'success' => true,
            'channel' => $channel,
            'orders' => $orders,
        ]);
    }

    /**
     * Kitchen ticket queue for the branch (today's KOTs in generation order).
     */
    public function kitchenQueue(Request $request): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();

        try {
            $request->validate([
                'branch_id' => ['required', 'integer', 'exists:branches,id'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $branchId = (int) $request->input('branch_id');
        if (! in_array($branchId, $this->posAccessibleBranchIds($user), true)) {
            return response()->json(['success' => false, 'message' => 'Branch not accessible.'], 403);
        }

        $company = Company::find($this->companyIdForBranch($branchId));
        if (! $this->companyAddonService->kitchenTrackingEnabled($company)) {
            return response()->json(['success' => false, 'message' => 'Kitchen tracking is not enabled.'], 403);
        }

        $businessDate = tz()->businessDate($branchId);
        $kots = $this->kitchenKotService->queueForBranch($branchId, $businessDate);

        $rows = $kots->values()->map(
            fn (KitchenKot $kot, int $index) => $this->formatKitchenQueueRow($kot, $index + 1, $branchId)
        )->all();

        return response()->json([
            'success' => true,
            'business_date' => $businessDate,
            'total' => count($rows),
            'kots' => $rows,
        ]);
    }

    /**
     * List completed orders for the branch on a given day (defaults to today).
     */
    public function todayOrders(Request $request): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();

        try {
            $request->validate([
                'branch_id' => ['required', 'integer', 'exists:branches,id'],
                'date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $branchId = (int) $request->input('branch_id');
        if (! in_array($branchId, $this->posAccessibleBranchIds($user), true)) {
            return response()->json(['success' => false, 'message' => 'Branch not accessible.'], 403);
        }

        $timezone = tz()->resolveForBranch($branchId);
        $todayInBranch = tz()->businessDate($branchId);
        if ($request->filled('date') && (string) $request->input('date') > $todayInBranch) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['date' => ['The date must be today or earlier.']],
            ], 422);
        }

        $date = $request->filled('date')
            ? (string) $request->input('date')
            : tz()->businessDate($branchId);

        $ordersQuery = Order::withoutGlobalScopes(['tenant', 'branch'])
            ->select([
                'id',
                'branch_id',
                'order_number',
                'type',
                'status',
                'payment_status',
                'total_amount',
                'created_at',
                'updated_at',
                'completed_at',
                'customer_name',
                'customer_phone',
                'customer_address',
                'table_id',
                'cashier_id',
                'waiter_id',
                'delivery_rider_id',
            ])
            ->where('branch_id', $branchId)
            ->where('status', 'completed');

        tz()->applyLocalDateColumn($ordersQuery, 'COALESCE(completed_at, created_at)', $date, $branchId);

        $orders = $ordersQuery
            ->with([
                'table:id,name',
                'cashier:id,name',
                'waiter:id,name',
                'deliveryRider:id,name',
            ])
            ->withCount('items')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (Order $o) => $this->formatPosOrderListRow($o, true))
            ->values();

        return response()->json([
            'success' => true,
            'date' => $date,
            'timezone' => $timezone,
            'orders' => $orders,
        ]);
    }

    /**
     * Sync line items and totals on an open (unpaid) POS order.
     */
    public function updateOpenOrder(Request $request, Order $order): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();
        $this->assertPosOrderAccess($user, $order);

        if ($resp = $this->assertPosOpenTab($order)) {
            return $resp;
        }

        try {
            $validated = $request->validate($this->posUpdateOpenOrderRules());
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $enrichedItems = $this->enrichPosCartItems($validated['items'], (int) $order->company_id);
        if ($enrichedItems instanceof JsonResponse) {
            return $enrichedItems;
        }
        $validated['items'] = $enrichedItems;

        if ($resp = $this->validatePosInventoryForItems($validated['items'], (int) $order->branch_id)) {
            return $resp;
        }

        DB::beginTransaction();
        try {
            $order->items()->delete();
            $this->createPosLineItems($order, $validated['items']);
            $discount = $this->normalizePosDiscount((float) $validated['subtotal'], $validated);
            $tabPayment = $this->resolvePosTabPaymentAttributes($validated, (int) $order->company_id);
            if ($tabPayment instanceof JsonResponse) {
                DB::rollBack();

                return $tabPayment;
            }

            $order->update(array_merge([
                'type' => $validated['type'] ?? $order->type,
                'table_id' => array_key_exists('table_id', $validated) ? $validated['table_id'] : $order->table_id,
                'waiter_id' => $validated['waiter_id'] ?? null,
                'delivery_rider_id' => $validated['delivery_rider_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_address' => $validated['customer_address'] ?? null,
                'subtotal' => $validated['subtotal'],
                'tax_amount' => $validated['tax_amount'],
                'discount_amount' => $discount['discount_amount'],
                'discount_type' => $discount['discount_type'],
                'discount_value' => $discount['discount_value'],
                'service_charge' => $validated['service_charge'] ?? 0,
                'delivery_fee' => $validated['delivery_fee'] ?? 0,
                'total_amount' => $validated['total_amount'],
                'notes' => $validated['notes'] ?? null,
            ], $tabPayment));
            $order->load(['items.menuItem', 'items.deal']);

            $directKitchenKots = $this->createDirectKitchenKotsForSavedOrder($order, $user);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update order: '.$e->getMessage(),
            ], 500);
        }

        $kitchenPrint = $this->queueDirectKitchenKots((int) $order->branch_id, $directKitchenKots);
        $order->refresh();

        return response()->json([
            'success' => true,
            'message' => $kitchenPrint['desktop_jobs'] > 0
                ? 'Order updated. KOT sent to kitchen.'
                : 'Order updated.',
            'order' => $this->posOrderJsonWithKitchenSync($order),
            'kots' => $kitchenPrint['kots'],
            'browser_kot_ids' => $kitchenPrint['browser_kot_ids'],
            'kitchen_desktop_jobs' => $kitchenPrint['desktop_jobs'],
        ]);
    }

    /**
     * Save cart changes and issue kitchen tickets (KOT) with void/add diff.
     */
    public function sendToKitchen(Request $request, Order $order): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();
        $this->assertPosOrderAccess($user, $order);

        if ($resp = $this->assertPosOpenTab($order)) {
            return $resp;
        }

        if ($readiness = $this->printReadiness->readinessErrorResponse((int) $order->branch_id, ['kitchen'])) {
            return $readiness;
        }

        try {
            $validated = $request->validate($this->posUpdateOpenOrderRules());
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $enrichedItems = $this->enrichPosCartItems($validated['items'], (int) $order->company_id);
        if ($enrichedItems instanceof JsonResponse) {
            return $enrichedItems;
        }
        $validated['items'] = $enrichedItems;

        if ($resp = $this->validatePosInventoryForItems($validated['items'], (int) $order->branch_id)) {
            return $resp;
        }

        DB::beginTransaction();
        try {
            $order = Order::withoutGlobalScopes(['tenant', 'branch'])
                ->where('id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order->items()->delete();
            $this->createPosLineItems($order, $validated['items']);
            $discount = $this->normalizePosDiscount((float) $validated['subtotal'], $validated);
            $order->update([
                'type' => $validated['type'] ?? $order->type,
                'table_id' => array_key_exists('table_id', $validated) ? $validated['table_id'] : $order->table_id,
                'waiter_id' => $validated['waiter_id'] ?? null,
                'delivery_rider_id' => $validated['delivery_rider_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_address' => $validated['customer_address'] ?? null,
                'subtotal' => $validated['subtotal'],
                'tax_amount' => $validated['tax_amount'],
                'discount_amount' => $discount['discount_amount'],
                'discount_type' => $discount['discount_type'],
                'discount_value' => $discount['discount_value'],
                'service_charge' => $validated['service_charge'] ?? 0,
                'delivery_fee' => $validated['delivery_fee'] ?? 0,
                'total_amount' => $validated['total_amount'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $order = $order->fresh();
            $kots = $this->kitchenKotService->sendToKitchen($order, $validated['items'], $user);

            if ($kots !== [] && $this->companyAddonService->kitchenTrackingEnabled(Company::find($order->company_id))) {
                $this->orderTrackingService->markPlacedFromKitchen($order->fresh(), $user);
            }

            $order->refresh();
            $order->load(['items.menuItem', 'items.deal', 'table', 'waiter', 'kitchenKots']);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to send to kitchen: '.$e->getMessage(),
            ], 500);
        }

        if ($kots === []) {
            return response()->json([
                'success' => true,
                'message' => 'No kitchen changes to print.',
                'kots' => [],
                'browser_kot_ids' => [],
                'desktop_jobs' => 0,
                'order' => $this->posOrderJsonWithKitchenSync($order),
            ]);
        }

        $printResult = $this->printJobService->queueKitchenKots((int) $order->branch_id, $kots);

        return response()->json([
            'success' => true,
            'message' => 'Sent to kitchen.',
            'kots' => collect($kots)->map(fn (KitchenKot $k) => [
                'id' => $k->id,
                'kot_number' => $k->kot_number,
                'token_number' => $k->token_number,
                'type' => $k->type,
            ])->values(),
            'browser_kot_ids' => $printResult['browser_kot_ids'],
            'desktop_jobs' => $printResult['desktop_jobs'],
            'order' => $this->posOrderJsonWithKitchenSync($order),
        ]);
    }

    /**
     * Advance workflow status on an open POS order (kitchen tracking add-on).
     */
    public function updateOrderStatus(Request $request, Order $order): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();
        $this->assertPosOrderAccess($user, $order);

        if ($resp = $this->assertPosOpenTab($order)) {
            return $resp;
        }

        $company = Company::find($order->company_id);
        if (! $this->companyAddonService->kitchenTrackingEnabled($company)) {
            return response()->json([
                'success' => false,
                'message' => 'Kitchen & order tracking is not enabled for this company.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'status' => [
                    'required',
                    'string',
                    Rule::in(OrderWorkflow::allowedNextStatuses((string) $order->status, (string) $order->type)),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $order = $this->orderTrackingService->changeStatus(
                $order,
                $validated['status'],
                $user,
                'pos'
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order status updated.',
            'order' => $this->posOrderJson($order),
        ]);
    }

    /**
     * Print readiness for POS actions (kitchen / receipt direct print).
     */
    public function printReadiness(Request $request): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'needs' => ['nullable', 'string'],
        ]);

        $branchId = (int) $validated['branch_id'];
        if (! in_array($branchId, $this->posAccessibleBranchIds($user), true)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized branch.'], 403);
        }

        $needs = $this->parsePrintNeeds($validated['needs'] ?? 'kitchen,receipt');

        return response()->json([
            'success' => true,
            ...$this->printReadiness->check($branchId, $needs),
        ]);
    }

    /**
     * Queue or prepare customer receipt print for an open order (unpaid bill).
     */
    public function printReceipt(Request $request, Order $order): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();
        $this->assertPosOrderAccess($user, $order);

        if ($readiness = $this->printReadiness->readinessErrorResponse((int) $order->branch_id, ['receipt'])) {
            return $readiness;
        }

        $printResult = $this->printJobService->queueReceipt($order);

        return response()->json([
            'success' => true,
            'message' => $printResult['desktop_jobs'] > 0 && ! $printResult['browser_print']
                ? 'Receipt sent for direct print.'
                : 'Receipt ready to print.',
            'browser_print' => $printResult['browser_print'],
            'desktop_jobs' => $printResult['desktop_jobs'],
            'order_id' => $order->id,
        ]);
    }

    /**
     * Print view for a kitchen order ticket (KOT).
     */
    public function kitchenKot(Request $request, int $kitchenKot)
    {
        $user = Auth::user();

        $kitchenKot = KitchenKot::withoutGlobalScopes(['tenant', 'branch'])
            ->findOrFail($kitchenKot);

        $order = $kitchenKot->order()
            ->withoutGlobalScopes(['tenant', 'branch'])
            ->withTrashed()
            ->with(['table', 'waiter', 'branch', 'company'])
            ->firstOrFail();

        $this->assertPosOrderAccess($user, $order);

        $company = $order->company;

        return view('pos.kitchen-ticket', [
            'kot' => $kitchenKot,
            'order' => $order,
            'company' => $company,
            'showReprint' => $request->boolean('reprint'),
            'showOrderCancel' => $request->boolean('cancel'),
        ]);
    }

    /**
     * Reprint kitchen ticket(s) for an open order (marked REPRINT on slip).
     */
    public function reprintKitchenKot(Request $request, Order $order): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();
        $this->assertPosOrderAccess($user, $order);

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This order is already paid.',
            ], 422);
        }

        $kots = $this->kitchenKotService->kotsForReprint($order);

        if ($kots->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nothing has been sent to kitchen for this order yet.',
            ], 422);
        }

        $printResult = $this->printJobService->queueKitchenKots(
            (int) $order->branch_id,
            $kots->all(),
            true
        );

        return response()->json([
            'success' => true,
            'message' => $kots->count() === 1
                ? 'KOT reprint sent.'
                : 'KOT reprints sent ('.$kots->count().' slips).',
            'kots' => $kots->map(fn (KitchenKot $k) => [
                'id' => $k->id,
                'kot_number' => $k->kot_number,
                'token_number' => $k->token_number,
                'type' => $k->type,
            ])->values(),
            'browser_kot_ids' => $printResult['browser_kot_ids'],
            'desktop_jobs' => $printResult['desktop_jobs'],
            'reprint' => true,
        ]);
    }

    /**
     * Cancel a POS queue order, or a completed Today-history order (reverses stock/payments).
     */
    public function cancelOrder(Request $request, Order $order): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();
        $this->assertPosOrderAccess($user, $order);

        if ($order->payment_status === 'refunded') {
            return response()->json([
                'success' => false,
                'message' => 'Refunded orders cannot be cancelled from the POS.',
            ], 422);
        }

        $isActiveQueue = in_array($order->status, $this->posActiveTabStatuses(), true)
            && $order->payment_status !== 'paid';
        $isCompletedHistory = $order->status === 'completed';

        if (! $isActiveQueue && ! $isCompletedHistory) {
            return response()->json([
                'success' => false,
                'message' => 'Only active unpaid queue orders or completed history orders can be cancelled from the POS.',
            ], 422);
        }

        try {
            $orderNumber = $order->order_number;
            $branchId = (int) $order->branch_id;
            $cancelKot = $this->kitchenKotService->createOrderCancelVoid($order->fresh(), $user);
            $preserveKotIds = $cancelKot ? [(int) $cancelKot->id] : [];
            $printResult = ['browser_kot_ids' => [], 'desktop_jobs' => 0];

            if ($cancelKot) {
                $printResult = $this->printJobService->queueKitchenKots(
                    $branchId,
                    [$cancelKot],
                    false,
                    false,
                    true
                );
            }

            $this->orderDeleteService->deleteOrder($order, (int) $user->id, $preserveKotIds);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Order could not be cancelled.',
            ], 500);
        }

        $message = 'Order '.$orderNumber.' cancelled.';
        if ($cancelKot) {
            $message .= ((int) ($printResult['desktop_jobs'] ?? 0) > 0 && ($printResult['browser_kot_ids'] ?? []) === [])
                ? ' Cancel slip sent to kitchen.'
                : ' Cancel slip ready for kitchen.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'order_id' => $order->id,
            'browser_kot_ids' => $printResult['browser_kot_ids'] ?? [],
            'desktop_jobs' => $printResult['desktop_jobs'] ?? 0,
            'cancel_kot_id' => $cancelKot?->id,
        ]);
    }

    /**
     * Take payment and close an open POS order (inventory + sale transaction).
     */
    public function checkoutOrder(Request $request, Order $order): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();
        $this->assertPosOrderAccess($user, $order);

        if ($resp = $this->assertPosOpenTab($order)) {
            return $resp;
        }

        try {
            $validated = $request->validate([
                'money_source_id' => ['nullable', 'exists:money_sources,id'],
                'payment_method' => ['nullable', 'in:credit,split,foc'],
                'payment_splits' => ['nullable', 'array', 'min:1'],
                'payment_splits.*.money_source_id' => ['required', 'integer', 'exists:money_sources,id'],
                'payment_splits.*.amount' => ['required', 'numeric', 'min:0.01'],
                'payment_splits.*.given_amount' => ['nullable', 'numeric', 'min:0'],
                'payment_splits.*.change_amount' => ['nullable', 'numeric', 'min:0'],
                'paid_amount' => ['nullable', 'numeric', 'min:0'],
                'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
                'customer_name' => ['nullable', 'string', 'max:255'],
                'customer_phone' => ['nullable', 'string', 'max:255'],
                'customer_email' => ['nullable', 'string', 'email', 'max:255'],
                'customer_address' => ['nullable', 'string'],
                'payment_status' => ['required', 'in:partial,paid'],
                'auto_bill' => ['nullable', 'boolean'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        if (($focDenied = $this->assertPosFocAllowed($user, $validated)) instanceof JsonResponse) {
            return $focDenied;
        }

        if ($order->type === 'delivery' && empty($order->customer_address) && empty($validated['customer_address'])) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery address is required before checkout.',
            ], 422);
        }

        $autoBill = array_key_exists('auto_bill', $validated)
            ? $request->boolean('auto_bill')
            : true;

        $printNeeds = ['receipt'];
        $printKitchenOnCheckout = $autoBill
            && $this->printJobService->hasDirectPrinters((int) $order->branch_id, 'kitchen');
        if ($printKitchenOnCheckout) {
            $printNeeds[] = 'kitchen';
        }

        if ($autoBill) {
            if ($readiness = $this->printReadiness->readinessErrorResponse((int) $order->branch_id, $printNeeds)) {
                return $readiness;
            }
        }

        $company = Company::find($order->company_id);
        $creditAllowed = $this->posCreditService->creditSalesAllowed($company);
        $totalAmount = (float) $order->total_amount;
        $paidAmount = (float) ($validated['paid_amount'] ?? 0);
        $customerId = isset($validated['customer_id']) ? (int) $validated['customer_id'] : null;
        $isSplitPayment = ($validated['payment_method'] ?? null) === 'split';
        $isFocPayment = ($validated['payment_method'] ?? null) === 'foc';
        $splitLines = [];

        if ($isSplitPayment) {
            $paymentResolved = $this->posSplitPaymentService->resolve(
                $validated['payment_splits'] ?? [],
                $totalAmount,
                (int) $order->company_id,
                (int) $order->branch_id
            );

            if ($paymentResolved instanceof JsonResponse) {
                return $paymentResolved;
            }

            $paymentMethod = $paymentResolved['payment_method'];
            $paidAmount = $paymentResolved['paid_amount'];
            $paymentStatus = $paymentResolved['payment_status'];
            $splitLines = $paymentResolved['lines'];
            $moneySource = null;
        } elseif ($isFocPayment) {
            $paymentResolved = $this->resolvePosFocPayment($validated, $totalAmount);
            if ($paymentResolved instanceof JsonResponse) {
                return $paymentResolved;
            }

            ['money_source' => $moneySource, 'payment_method' => $paymentMethod, 'paid_amount' => $paidAmount, 'payment_status' => $paymentStatus] = $paymentResolved;
        } else {
            $explicitCredit = $this->posCreditService->isExplicitCreditPayment($validated);

            if ($message = $this->posCreditService->validateCreditSale($creditAllowed, $totalAmount, $paidAmount, $customerId, $explicitCredit, (int) $order->company_id)) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            $paymentResolved = $this->resolvePosPayment(
                $validated,
                $creditAllowed,
                $totalAmount,
                $paidAmount,
                (int) $order->company_id
            );

            if ($paymentResolved instanceof JsonResponse) {
                return $paymentResolved;
            }

            ['money_source' => $moneySource, 'payment_method' => $paymentMethod, 'paid_amount' => $paidAmount, 'payment_status' => $paymentStatus] = $paymentResolved;
        }

        $order->load(['items.menuItem.recipes.ingredient', 'items.deal']);
        if ($resp = $this->validatePosInventoryForItems(
            $order->items->map(fn (OrderItem $line) => [
                'menu_item_id' => $line->menu_item_id,
                'deal_id' => $line->deal_id,
                'quantity' => $line->quantity,
            ])->all(),
            (int) $order->branch_id
        )) {
            return $resp;
        }

        $checkoutKots = [];

        DB::beginTransaction();
        try {
            $address = $validated['customer_address'] ?? $order->customer_address;
            $name = trim((string) ($validated['customer_name'] ?? ''));
            $phone = trim((string) ($validated['customer_phone'] ?? ''));
            $email = trim((string) ($validated['customer_email'] ?? ''));
            $customer = $this->posCreditService->resolveCustomerForCompany($customerId, (int) $order->company_id);

            $updateData = [
                'customer_name' => $name !== '' ? $name : null,
                'customer_phone' => $phone !== '' ? $phone : null,
                'customer_email' => $email !== '' ? $email : null,
                'customer_address' => $address,
                'payment_method' => $paymentMethod,
                'money_source_id' => $moneySource?->id,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'paid_at_sale' => $paidAmount,
                'status' => 'completed',
                'completed_at' => now(),
            ];
            $updateData = $this->posCreditService->mergeCustomerSnapshot($updateData, $customer);
            $updateData = array_merge($updateData, $this->checkoutShiftStampAttributes($order));
            $order->update($updateData);

            if ($isSplitPayment) {
                $this->posSplitPaymentService->persist($order, $splitLines);
            }

            if ($customer && $paymentMethod !== 'foc') {
                $this->posCreditService->applyOrderToCustomerBalance($customer, $totalAmount, $paidAmount);
            }

            try {
                $order->load(['items.menuItem.recipes.ingredient', 'items.deal', 'payments.moneySource']);
                event(new OrderCreated($order));
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('POS checkout: order event failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            if ($order->type === 'dine_in' && $order->table_id) {
                Table::withoutGlobalScope('branch')->where('id', $order->table_id)->update(['status' => 'available']);
            }

            if ($printKitchenOnCheckout) {
                $order->loadMissing('items');
                $checkoutKots = $this->kitchenKotService->sendToKitchen(
                    $order,
                    $this->kitchenKotService->cartItemsFromOrder($order),
                    $user
                );

                if ($checkoutKots !== [] && $this->companyAddonService->kitchenTrackingEnabled(Company::find($order->company_id))) {
                    $this->orderTrackingService->markPlacedFromKitchen($order->fresh(), $user);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Checkout failed: '.$e->getMessage(),
            ], 500);
        }

        $order->refresh()->load(['items.menuItem', 'items.deal', 'payments.moneySource']);

        if (! $autoBill) {
            return response()->json([
                'success' => true,
                'message' => 'Order completed.',
                'order' => $order,
                'browser_print' => false,
                'desktop_jobs' => 0,
                'kitchen_desktop_jobs' => 0,
                'kots' => [],
                'auto_bill' => false,
            ]);
        }

        $printResult = $this->printJobService->queueReceipt($order);
        $kitchenPrintResult = ['browser_kot_ids' => [], 'desktop_jobs' => 0];
        if ($checkoutKots !== []) {
            $kitchenPrintResult = $this->printJobService->queueKitchenKots(
                (int) $order->branch_id,
                $checkoutKots,
                asReprint: false,
                directOnly: true,
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Order completed.',
            'order' => $order,
            'browser_print' => $printResult['browser_print'],
            'desktop_jobs' => $printResult['desktop_jobs'],
            'kitchen_desktop_jobs' => $kitchenPrintResult['desktop_jobs'],
            'kots' => collect($checkoutKots)->map(fn (KitchenKot $k) => [
                'id' => $k->id,
                'kot_number' => $k->kot_number,
                'token_number' => $k->token_number,
                'type' => $k->type,
            ])->values(),
            'auto_bill' => true,
        ]);
    }

    /**
     * Store a new order.
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $user = Auth::user();
        $accessibleBranchIds = $this->posAccessibleBranchIds($user);
        $mode = $request->input('mode', 'pay');

        $tableInBranchRule = function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            if ($value === null || $value === '') {
                return;
            }
            $branchId = (int) $request->input('branch_id');
            $ok = Table::withoutGlobalScope('branch')
                ->where('id', (int) $value)
                ->where('branch_id', $branchId)
                ->exists();
            if (! $ok) {
                $fail('The selected table is not valid for this branch.');
            }
        };

        try {
            if ($mode === 'tab') {
                $validated = $request->validate(array_merge([
                    'mode' => 'required|in:tab',
                    'type' => 'required|in:dine_in,takeaway,delivery',
                    'branch_id' => ['required', 'exists:branches,id', function ($attribute, $value, $fail) use ($accessibleBranchIds) {
                        if (! in_array((int) $value, $accessibleBranchIds, true)) {
                            $fail('The selected branch is not accessible.');
                        }
                    }],
                    'table_id' => ['nullable', 'exists:tables,id', $tableInBranchRule],
                    'waiter_id' => 'nullable|integer|exists:users,id',
                    'delivery_rider_id' => 'nullable|integer|exists:users,id',
                    'customer_name' => 'nullable|string|max:255',
                    'customer_phone' => 'nullable|string|max:255',
                    'customer_email' => ['nullable', 'string', 'email', 'max:255'],
                    'customer_address' => 'nullable|string',
                    'items' => 'required|array|min:1',
                    'items.*.menu_item_id' => 'nullable|required_without:items.*.deal_id|exists:menu_items,id',
                    'items.*.deal_id' => 'nullable|required_without:items.*.menu_item_id|exists:deals,id',
                    'items.*.item_name' => 'nullable|string|max:255',
                    'items.*.name' => 'nullable|string|max:255',
                    'items.*.quantity' => 'required|numeric|min:0.01',
                    'items.*.unit_price' => 'required|numeric|min:0',
                    'items.*.variants' => 'nullable|array',
                    'items.*.addons' => 'nullable|array',
                    'items.*.addons.*.id' => 'nullable|integer',
                    'items.*.addons.*.quantity' => 'nullable|numeric|min:0.01',
                    'items.*.special_instructions' => 'nullable|string',
                    'subtotal' => 'required|numeric|min:0',
                    'tax_amount' => 'required|numeric|min:0',
                    ...$this->posDiscountValidationRules(),
                    'service_charge' => 'nullable|numeric|min:0',
                    'delivery_fee' => 'nullable|numeric|min:0',
                    'total_amount' => 'required|numeric|min:0',
                    'notes' => 'nullable|string',
                    ...$this->posTabPaymentRules(),
                ], $request->type === 'dine_in' ? [
                    'table_id' => ['required', 'exists:tables,id', $tableInBranchRule],
                ] : []));
            } else {
                $validated = $request->validate(array_merge([
                    'mode' => 'nullable|in:pay',
                    'type' => 'required|in:dine_in,takeaway,delivery',
                    'branch_id' => ['required', 'exists:branches,id', function ($attribute, $value, $fail) use ($accessibleBranchIds) {
                        if (! in_array((int) $value, $accessibleBranchIds, true)) {
                            $fail('The selected branch is not accessible.');
                        }
                    }],
                    'table_id' => ['nullable', 'exists:tables,id', $tableInBranchRule],
                    'waiter_id' => 'nullable|integer|exists:users,id',
                    'delivery_rider_id' => 'nullable|integer|exists:users,id',
                    'customer_id' => 'nullable|integer|exists:customers,id',
                    'customer_name' => 'nullable|string|max:255',
                    'customer_phone' => 'nullable|string|max:255',
                    'customer_email' => ['nullable', 'string', 'email', 'max:255'],
                    'customer_address' => 'nullable|string',
                    'items' => 'required|array|min:1',
                    'items.*.menu_item_id' => 'nullable|required_without:items.*.deal_id|exists:menu_items,id',
                    'items.*.deal_id' => 'nullable|required_without:items.*.menu_item_id|exists:deals,id',
                    'items.*.item_name' => 'nullable|string|max:255',
                    'items.*.name' => 'nullable|string|max:255',
                    'items.*.quantity' => 'required|numeric|min:0.01',
                    'items.*.unit_price' => 'required|numeric|min:0',
                    'items.*.variants' => 'nullable|array',
                    'items.*.addons' => 'nullable|array',
                    'items.*.addons.*.id' => 'nullable|integer',
                    'items.*.addons.*.quantity' => 'nullable|numeric|min:0.01',
                    'items.*.special_instructions' => 'nullable|string',
                    'subtotal' => 'required|numeric|min:0',
                    'tax_amount' => 'required|numeric|min:0',
                    ...$this->posDiscountValidationRules(),
                    'service_charge' => 'nullable|numeric|min:0',
                    'delivery_fee' => 'nullable|numeric|min:0',
                    'total_amount' => 'required|numeric|min:0',
                    'paid_amount' => 'nullable|numeric|min:0',
                    'money_source_id' => 'nullable|exists:money_sources,id',
                    'payment_method' => 'nullable|in:credit,foc',
                    'payment_status' => 'required|in:unpaid,partial,paid',
                    'notes' => 'nullable|string',
                ], $request->type === 'dine_in' ? [
                    'table_id' => ['required', 'exists:tables,id', $tableInBranchRule],
                ] : []));
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        if (($focDenied = $this->assertPosFocAllowed($user, $validated ?? [])) instanceof JsonResponse) {
            return $focDenied;
        }

        if ($mode === 'tab' && $validated['type'] === 'dine_in' && ! empty($validated['table_id'])) {
            $openOnTable = Order::withoutGlobalScopes(['tenant', 'branch'])
                ->where('table_id', (int) $validated['table_id'])
                ->whereIn('status', $this->posActiveTabStatuses())
                ->where('payment_status', 'unpaid')
                ->first();
            if ($openOnTable) {
                return response()->json([
                    'success' => false,
                    'message' => 'This table already has an open order. Resume it or finish checkout first.',
                    'existing_order_id' => $openOnTable->id,
                ], 422);
            }
        }

        if ($mode === 'pay' && $validated['type'] === 'dine_in' && ! empty($validated['table_id'])) {
            $openOnTable = Order::withoutGlobalScopes(['tenant', 'branch'])
                ->where('table_id', (int) $validated['table_id'])
                ->whereIn('status', $this->posActiveTabStatuses())
                ->where('payment_status', 'unpaid')
                ->exists();
            if ($openOnTable) {
                return response()->json([
                    'success' => false,
                    'message' => 'This table has an unpaid tab. Use Save/Checkout on that order before taking a new payment here.',
                ], 422);
            }
        }

        $companyId = $this->companyIdForBranch((int) $validated['branch_id']);
        $enrichedItems = $this->enrichPosCartItems($validated['items'], $companyId);
        if ($enrichedItems instanceof JsonResponse) {
            return $enrichedItems;
        }
        $validated['items'] = $enrichedItems;

        if ($resp = $this->validatePosInventoryForItems($validated['items'], (int) $validated['branch_id'])) {
            return $resp;
        }

        DB::beginTransaction();
        try {
            if ($mode === 'tab') {
                $discount = $this->normalizePosDiscount((float) $validated['subtotal'], $validated);
                $tabPayment = $this->resolvePosTabPaymentAttributes($validated, $companyId);
                if ($tabPayment instanceof JsonResponse) {
                    DB::rollBack();

                    return $tabPayment;
                }

                $order = $this->createPosOrder(array_merge([
                    'company_id' => $companyId,
                    'branch_id' => $validated['branch_id'],
                    'table_id' => $validated['table_id'] ?? null,
                    'cashier_id' => $user->id,
                    'waiter_id' => $validated['waiter_id'] ?? null,
                    'delivery_rider_id' => $validated['delivery_rider_id'] ?? null,
                    'type' => $validated['type'],
                    'status' => 'open',
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'customer_email' => $validated['customer_email'] ?? null,
                    'customer_address' => $validated['customer_address'] ?? null,
                    'subtotal' => $validated['subtotal'],
                    'tax_amount' => $validated['tax_amount'],
                    'discount_amount' => $discount['discount_amount'],
                    'discount_type' => $discount['discount_type'],
                    'discount_value' => $discount['discount_value'],
                    'service_charge' => $validated['service_charge'] ?? 0,
                    'delivery_fee' => $validated['delivery_fee'] ?? 0,
                    'total_amount' => $validated['total_amount'],
                    'notes' => $validated['notes'] ?? null,
                ], $tabPayment), (int) $validated['branch_id']);

                $this->createPosLineItems($order, $validated['items']);

                if ($validated['type'] === 'dine_in' && ! empty($validated['table_id'])) {
                    Table::withoutGlobalScope('branch')->where('id', (int) $validated['table_id'])->update(['status' => 'occupied']);
                }

                $order->load(['items.menuItem', 'items.deal']);
                $directKitchenKots = $this->createDirectKitchenKotsForSavedOrder($order, $user);

                DB::commit();

                $kitchenPrint = $this->queueDirectKitchenKots((int) $order->branch_id, $directKitchenKots);
                $order->refresh()->load(['items.menuItem', 'items.deal']);

                return response()->json([
                    'success' => true,
                    'message' => $kitchenPrint['desktop_jobs'] > 0
                        ? 'Order saved. KOT sent to kitchen.'
                        : 'Order saved. You can add more items or checkout when ready.',
                    'order' => $this->posOrderJsonWithKitchenSync($order),
                    'kots' => $kitchenPrint['kots'],
                    'browser_kot_ids' => $kitchenPrint['browser_kot_ids'],
                    'kitchen_desktop_jobs' => $kitchenPrint['desktop_jobs'],
                ]);
            }

            $company = Company::find($companyId);
            $creditAllowed = $this->posCreditService->creditSalesAllowed($company);
            $totalAmount = (float) $validated['total_amount'];
            $paidAmount = (float) ($validated['paid_amount'] ?? 0);
            $customerId = isset($validated['customer_id']) ? (int) $validated['customer_id'] : null;
            $isFocPayment = ($validated['payment_method'] ?? null) === 'foc';

            if ($isFocPayment) {
                $paymentResolved = $this->resolvePosFocPayment($validated, $totalAmount);
            } else {
                $explicitCredit = $this->posCreditService->isExplicitCreditPayment($validated);

                if ($message = $this->posCreditService->validateCreditSale($creditAllowed, $totalAmount, $paidAmount, $customerId, $explicitCredit, $companyId)) {
                    DB::rollBack();

                    return response()->json(['success' => false, 'message' => $message], 422);
                }

                $paymentResolved = $this->resolvePosPayment(
                    $validated,
                    $creditAllowed,
                    $totalAmount,
                    $paidAmount,
                    $companyId
                );
            }

            if ($paymentResolved instanceof JsonResponse) {
                DB::rollBack();

                return $paymentResolved;
            }

            ['money_source' => $moneySource, 'payment_method' => $paymentMethod, 'paid_amount' => $paidAmount, 'payment_status' => $paymentStatus] = $paymentResolved;

            $paidNow = $paymentStatus === 'paid' || $paymentStatus === 'partial';
            $customer = $this->posCreditService->resolveCustomerForCompany($customerId, $companyId);
            $discount = $this->normalizePosDiscount((float) $validated['subtotal'], $validated);

            $orderData = [
                'company_id' => $companyId,
                'branch_id' => $validated['branch_id'],
                'table_id' => $validated['table_id'] ?? null,
                'cashier_id' => $user->id,
                'waiter_id' => $validated['waiter_id'] ?? null,
                'delivery_rider_id' => $validated['delivery_rider_id'] ?? null,
                'type' => $validated['type'],
                'status' => 'completed',
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'money_source_id' => $moneySource?->id,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_address' => $validated['customer_address'] ?? null,
                'subtotal' => $validated['subtotal'],
                'tax_amount' => $validated['tax_amount'],
                'discount_amount' => $discount['discount_amount'],
                'discount_type' => $discount['discount_type'],
                'discount_value' => $discount['discount_value'],
                'service_charge' => $validated['service_charge'] ?? 0,
                'delivery_fee' => $validated['delivery_fee'] ?? 0,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'paid_at_sale' => $paidAmount,
                'notes' => $validated['notes'] ?? null,
                'completed_at' => now(),
            ];
            $orderData = $this->posCreditService->mergeCustomerSnapshot($orderData, $customer);
            $order = $this->createPosOrder($orderData, (int) $validated['branch_id']);

            if ($customer && $paymentMethod !== 'foc') {
                $this->posCreditService->applyOrderToCustomerBalance($customer, $totalAmount, $paidAmount);
            }

            $this->createPosLineItems($order, $validated['items']);

            if ($validated['type'] === 'dine_in' && ! empty($validated['table_id'])) {
                Table::withoutGlobalScope('branch')->where('id', (int) $validated['table_id'])->update(['status' => 'occupied']);
            }

            $order->load([
                'items.menuItem.recipes.ingredient',
            ]);

            try {
                event(new OrderCreated($order));
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to process order event', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully.',
                'order' => $order->load('items'),
                'redirect' => route('pos.index'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display invoice for an order.
     */
    public function invoice(int $order)
    {
        $order = Order::withoutGlobalScopes(['tenant', 'branch'])
            ->with([
                'items.menuItem',
                'items.deal.menuItems' => fn ($q) => $q->withoutGlobalScopes(),
                'cashier',
                'table',
                'payments.moneySource',
                'branch',
                'company',
            ])
            ->findOrFail($order);

        $this->assertPosOrderAccess(Auth::user(), $order);

        CompanyReceiptBrandingService::applyToOrder($order);

        // If request wants JSON (for AJAX), return JSON
        if (request()->wantsJson() || request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'order' => $order,
            ]);
        }

        return view('pos.invoice', compact('order'));
    }

    /**
     * Full order details for POS queue / details modal.
     */
    public function orderDetails(Request $request, Order $order): JsonResponse
    {
        $this->ensurePosAcceptsJson($request);
        $this->assertPosOrderAccess(Auth::user(), $order);

        $order->load([
            'items.menuItem',
            'items.deal',
            'table',
            'cashier',
            'waiter',
            'deliveryRider',
            'moneySource',
            'payments.moneySource',
            'kitchenKots',
            'branch',
        ]);

        return response()->json([
            'success' => true,
            'order' => $this->posOrderDetailJson($order),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPosOrderListRow(Order $o, bool $includeHistoryTimestamps = false): array
    {
        $row = [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'type' => $o->type,
            'status' => $o->status,
            'payment_status' => $o->payment_status,
            'total_amount' => (float) $o->total_amount,
            'updated_at' => $o->updated_at?->toIso8601String(),
            'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'customer_address' => $o->customer_address,
            'items_count' => (int) ($o->items_count ?? 0),
            'table' => $o->table ? ['id' => $o->table->id, 'name' => $o->table->name] : null,
            'cashier' => $o->cashier ? ['id' => $o->cashier->id, 'name' => $o->cashier->name] : null,
            'waiter' => $o->waiter ? ['id' => $o->waiter->id, 'name' => $o->waiter->name] : null,
            'delivery_rider' => $o->deliveryRider ? ['id' => $o->deliveryRider->id, 'name' => $o->deliveryRider->name] : null,
            'kitchen_kots_count' => (int) ($o->kitchen_kots_count ?? 0),
            'kitchen_sent' => $this->orderHasKitchenSlip($o),
        ];

        if ($includeHistoryTimestamps) {
            $row['created_at'] = $o->created_at?->toIso8601String();
            $row['completed_at'] = $o->completed_at?->toIso8601String();
            $row['created_at_display'] = tz()->formatHistoryTimestamp($o->created_at, $o->branch_id);
            $row['completed_at_display'] = tz()->formatHistoryTimestamp($o->completed_at, $o->branch_id);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatKitchenQueueRow(KitchenKot $kot, int $queuePosition, int $branchId): array
    {
        $order = $kot->order;
        $lines = is_array($kot->lines) ? $kot->lines : [];

        return [
            'id' => $kot->id,
            'queue_position' => $queuePosition,
            'kot_number' => $kot->kot_number,
            'token_number' => $kot->token_number,
            'type' => $kot->type,
            'type_label' => $kot->typeLabel(),
            'lines' => $lines,
            'line_count' => count($lines),
            'lines_summary' => collect($lines)
                ->map(fn ($line) => ((float) ($line['quantity'] ?? 0)).'×'.((string) ($line['item_name'] ?? 'Item')))
                ->implode(', '),
            'printed_at' => $kot->printed_at?->toIso8601String(),
            'printed_at_display' => tz()->formatHistoryTimestamp($kot->printed_at ?? $kot->created_at, $branchId),
            'printed_by_name' => $kot->printedBy?->name,
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'type' => $order->type,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'customer_name' => $order->customer_name,
                'table' => $order->table ? ['id' => $order->table->id, 'name' => $order->table->name] : null,
                'waiter' => $order->waiter ? ['id' => $order->waiter->id, 'name' => $order->waiter->name] : null,
            ] : null,
        ];
    }

    private function orderHasKitchenSlip(Order $order): bool
    {
        $snapshot = $order->kitchen_cart_snapshot ?? null;
        if (is_array($snapshot) && $snapshot !== []) {
            return true;
        }

        return (int) ($order->kitchen_kots_count ?? 0) > 0;
    }

    private function ensurePosAcceptsJson(Request $request): void
    {
        if (! $request->expectsJson() && $request->wantsJson()) {
            $request->headers->set('Accept', 'application/json');
        }
    }

    /**
     * @return list<int>
     */
    private function posAccessibleBranchIds(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Branch::where('status', 'active')->pluck('id')->all();
        }
        if ($user->isCompanyAdmin() && $user->company_id) {
            $branchId = current_branch_id();

            return $branchId ? [(int) $branchId] : [];
        }
        $ids = $user->branches()->where('status', 'active')->pluck('branches.id')->all();
        if (empty($ids) && $user->branch_id) {
            return [(int) $user->branch_id];
        }

        return array_map('intval', $ids);
    }

    private function companyIdForBranch(int $branchId): int
    {
        return (int) Branch::withoutGlobalScope('tenant')->where('id', $branchId)->value('company_id');
    }

    private function assertPosOrderAccess(User $user, Order $order): void
    {
        if (! in_array((int) $order->branch_id, $this->posAccessibleBranchIds($user), true)) {
            abort(403);
        }
        if (! $user->isSuperAdmin() && $user->company_id && (int) $order->company_id !== (int) $user->company_id) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function posUpdateOpenOrderRules(): array
    {
        return [
            'type' => 'nullable|in:dine_in,takeaway,delivery',
            'table_id' => 'nullable|integer|exists:tables,id',
            'waiter_id' => 'nullable|integer|exists:users,id',
            'delivery_rider_id' => 'nullable|integer|exists:users,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:255',
            'customer_email' => ['nullable', 'string', 'email', 'max:255'],
            'customer_address' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'nullable|required_without:items.*.deal_id|exists:menu_items,id',
            'items.*.deal_id' => 'nullable|required_without:items.*.menu_item_id|exists:deals,id',
            'items.*.item_name' => 'nullable|string|max:255',
            'items.*.name' => 'nullable|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.variants' => 'nullable|array',
            'items.*.addons' => 'nullable|array',
            'items.*.addons.*.id' => 'nullable|integer',
            'items.*.addons.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.special_instructions' => 'nullable|string',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            ...$this->posDiscountValidationRules(),
            'service_charge' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            ...$this->posTabPaymentRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function posTabPaymentRules(): array
    {
        return [
            'customer_id' => 'nullable|integer|exists:customers,id',
            'payment_method' => 'nullable|in:credit',
            'paid_amount' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|JsonResponse
     */
    private function resolvePosTabPaymentAttributes(array $validated, int $companyId): array|JsonResponse
    {
        if (($validated['payment_method'] ?? null) !== 'credit') {
            return [
                'payment_method' => null,
                'money_source_id' => null,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
            ];
        }

        $company = Company::find($companyId);
        $creditAllowed = $this->posCreditService->creditSalesAllowed($company);
        $totalAmount = (float) $validated['total_amount'];
        $paidAmount = (float) ($validated['paid_amount'] ?? 0);
        $customerId = isset($validated['customer_id']) ? (int) $validated['customer_id'] : null;

        if ($message = $this->posCreditService->validateCreditSale($creditAllowed, $totalAmount, $paidAmount, $customerId, true, $companyId)) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        $customer = $this->posCreditService->resolveCustomerForCompany($customerId, $companyId);
        if (! $customer) {
            return response()->json([
                'success' => false,
                'message' => 'Select a registered customer for credit orders.',
            ], 422);
        }

        return $this->posCreditService->mergeCustomerSnapshot([
            'payment_method' => 'credit',
            'money_source_id' => null,
            'paid_amount' => round(max(0, $paidAmount), 2),
            'payment_status' => 'unpaid',
        ], $customer);
    }

    /**
     * @return array<string, string>
     */
    private function posDiscountValidationRules(): array
    {
        return [
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{discount_type: ?string, discount_value: ?float, discount_amount: float}
     */
    private function normalizePosDiscount(float $subtotal, array $validated): array
    {
        $subtotal = round(max(0, $subtotal), 2);
        $type = $validated['discount_type'] ?? null;
        $value = isset($validated['discount_value']) ? (float) $validated['discount_value'] : null;
        $clientAmount = (float) ($validated['discount_amount'] ?? 0);

        $amount = 0.0;
        if ($type === 'percentage' && $value !== null && $value > 0 && $subtotal > 0) {
            $amount = round($subtotal * min($value, 100) / 100, 2);
        } elseif ($type === 'fixed' && $value !== null && $value > 0 && $subtotal > 0) {
            $amount = round(min($value, $subtotal), 2);
        } elseif ($clientAmount > 0 && $subtotal > 0) {
            $amount = round(min($clientAmount, $subtotal), 2);
            $type = 'fixed';
            $value = $amount;
        }

        if ($amount <= 0) {
            return [
                'discount_type' => null,
                'discount_value' => null,
                'discount_amount' => 0.0,
            ];
        }

        return [
            'discount_type' => $type,
            'discount_value' => $value,
            'discount_amount' => $amount,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>|JsonResponse
     */
    private function enrichPosCartItems(array $items, int $companyId): array|JsonResponse
    {
        return app(PosAddonService::class)->enrichAndValidatePosItems($items, $companyId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function validatePosInventoryForItems(array $items, int $branchId): ?JsonResponse
    {
        $inventoryService = app(InventoryService::class);
        foreach ($items as $item) {
            if (! empty($item['deal_id'])) {
                $deal = \App\Models\Deal::with([
                    'menuItems.defaultRecipe.items.ingredient',
                    'menuItems.variantRecipes.recipe.items.ingredient',
                    'menuItems.legacyRecipeLines.ingredient',
                ])->findOrFail($item['deal_id']);

                $availability = $inventoryService->checkDealAvailability(
                    $deal,
                    (float) $item['quantity'],
                    $branchId
                );

                if (! $availability['can_sell']) {
                    return response()->json([
                        'success' => false,
                        'message' => $availability['error_message'],
                        'error' => 'insufficient_stock',
                    ], 422);
                }

                continue;
            }
            if (empty($item['menu_item_id'])) {
                continue;
            }
            $menuItem = MenuItem::with('defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient', 'legacyRecipeLines.ingredient')->findOrFail($item['menu_item_id']);
            [$variantId, $variantOption] = MenuItem::variantContextFromOrderSelection(
                is_array($item['variants'] ?? null) ? $item['variants'] : null
            );
            $availability = $inventoryService->checkMenuItemAvailability(
                $menuItem,
                $item['quantity'],
                $branchId,
                $variantId,
                $variantOption
            );

            if (! $availability['can_sell']) {
                return response()->json([
                    'success' => false,
                    'message' => $availability['error_message'],
                    'error' => 'insufficient_stock',
                ], 422);
            }

            $addonAvailability = $inventoryService->checkAddonsAvailability(
                is_array($item['addons'] ?? null) ? $item['addons'] : null,
                (float) $item['quantity'],
                $branchId
            );

            if (! $addonAvailability['can_sell']) {
                return response()->json([
                    'success' => false,
                    'message' => $addonAvailability['error_message'],
                    'error' => 'insufficient_stock',
                ], 422);
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createPosLineItems(Order $order, array $items): void
    {
        foreach ($items as $item) {
            $isDeal = ! empty($item['deal_id']);
            $itemName = $item['item_name'] ?? $item['name'] ?? null;
            if (! $itemName && ! $isDeal) {
                $menuItem = MenuItem::find($item['menu_item_id']);
                $itemName = $menuItem ? $menuItem->name : 'Item';
            }
            $itemName = $itemName ?? 'Item';
            $quantity = $item['quantity'];
            $unitPrice = $item['unit_price'];
            $addons = $isDeal ? null : ($item['addons'] ?? null);

            OrderItem::create([
                'order_id' => $order->id,
                'deal_id' => $isDeal ? $item['deal_id'] : null,
                'menu_item_id' => $isDeal ? null : $item['menu_item_id'],
                'item_name' => $itemName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $quantity * $unitPrice,
                'variants' => $isDeal ? [] : ($item['variants'] ?? []),
                'addons' => is_array($addons) && $addons !== [] ? $addons : null,
                'special_instructions' => $item['special_instructions'] ?? null,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertPosFocAllowed(User $user, array $validated): ?JsonResponse
    {
        if (($validated['payment_method'] ?? null) !== 'foc') {
            return null;
        }

        if (! $user->hasAppPermission('pos.foc')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to settle orders as FOC.',
            ], 403);
        }

        return null;
    }

    /**
     * Exclusive FOC settle: no money source, no splits, no cash collected.
     *
     * @param  array<string, mixed>  $validated
     * @return array{money_source: null, payment_method: string, paid_amount: float, payment_status: string}|JsonResponse
     */
    private function resolvePosFocPayment(array $validated, float $totalAmount): array|JsonResponse
    {
        if (! empty($validated['money_source_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'FOC cannot be combined with a money source.',
            ], 422);
        }

        if (! empty($validated['payment_splits'])) {
            return response()->json([
                'success' => false,
                'message' => 'FOC cannot be combined with split payment.',
            ], 422);
        }

        if ($totalAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'FOC requires a positive order total.',
            ], 422);
        }

        return [
            'money_source' => null,
            'payment_method' => 'foc',
            'paid_amount' => 0.0,
            'payment_status' => 'paid',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{money_source: ?MoneySource, payment_method: string, paid_amount: float, payment_status: string}|JsonResponse
     */
    private function resolvePosPayment(
        array $validated,
        bool $creditAllowed,
        float $totalAmount,
        float $paidAmount,
        int $companyId
    ): array|JsonResponse {
        $paidAmount = round(max(0, $paidAmount), 2);
        $explicitCredit = $this->posCreditService->isExplicitCreditPayment($validated);

        if ($explicitCredit) {
            if (! $creditAllowed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credit sales are disabled. Payment must cover the full total.',
                ], 422);
            }

            $creditAmount = $this->posCreditService->creditAmount($totalAmount, $paidAmount);
            if ($creditAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credit payment selected but amount received covers the total.',
                ], 422);
            }

            $paymentStatus = $paidAmount >= $totalAmount ? 'paid' : 'partial';

            return [
                'money_source' => null,
                'payment_method' => 'credit',
                'paid_amount' => $paidAmount,
                'payment_status' => $paymentStatus,
            ];
        }

        $creditAmount = $this->posCreditService->creditAmount($totalAmount, $paidAmount);

        if ($creditAmount > 0) {
            $customerId = isset($validated['customer_id']) ? (int) $validated['customer_id'] : null;
            $customer = $this->posCreditService->resolveCustomerForCompany($customerId, $companyId);

            if ($customer && $creditAmount <= $this->posCreditService->customerCreditAvailable($customer) + 0.001) {
                $moneySource = null;
                $paymentMethod = 'cash';

                if ($paidAmount > 0) {
                    if (empty($validated['money_source_id'])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Select a payment source for the cash portion.',
                        ], 422);
                    }

                    $moneySource = MoneySource::forPayments()
                        ->where('company_id', $companyId)
                        ->where('id', $validated['money_source_id'])
                        ->first();

                    if (! $moneySource) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid payment source for this company.',
                        ], 422);
                    }

                    $paymentMethod = match ($moneySource->type) {
                        'CASH' => 'cash',
                        'BANK' => 'card',
                        'APP' => 'digital_wallet',
                        default => 'cash',
                    };
                }

                return [
                    'money_source' => $moneySource,
                    'payment_method' => $paymentMethod,
                    'paid_amount' => $paidAmount,
                    'payment_status' => 'paid',
                ];
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment must cover the full total, select Credit, or use customer advance.',
            ], 422);
        }

        if ($paidAmount < $totalAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Enter payment at least equal to the order total, or select Credit.',
            ], 422);
        }

        if (empty($validated['money_source_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Select a payment source.',
            ], 422);
        }

        $moneySource = MoneySource::forPayments()
            ->where('company_id', $companyId)
            ->where('id', $validated['money_source_id'])
            ->first();

        if (! $moneySource) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment source for this company.',
            ], 422);
        }

        $paymentMethod = match ($moneySource->type) {
            'CASH' => 'cash',
            'BANK' => 'card',
            'APP' => 'digital_wallet',
            default => 'cash',
        };

        if ($creditAmount <= 0 && $paymentMethod === 'cash' && $paidAmount < $totalAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Enter payment at least equal to the order total, or select Credit.',
            ], 422);
        }

        if ($creditAmount <= 0 && $paymentMethod !== 'cash' && $paidAmount < $totalAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Enter payment at least equal to the order total, or select Credit.',
            ], 422);
        }

        if ($paidAmount >= $totalAmount) {
            $paidAmount = $totalAmount;
            $paymentStatus = 'paid';
        } else {
            $paymentStatus = 'partial';
        }

        return [
            'money_source' => $moneySource,
            'payment_method' => $paymentMethod,
            'paid_amount' => $paidAmount,
            'payment_status' => $paymentStatus,
        ];
    }

    /**
     * Open tabs keep the opening shift until checkout. On pay, re-stamp to the cashier's
     * current shift so Z-report / business_date / cash drawer match when cash was taken.
     *
     * @return array{shift_id?: int, business_date?: string}
     */
    private function checkoutShiftStampAttributes(Order $order): array
    {
        $branchId = (int) $order->branch_id;
        $currentShiftId = CurrentShift::id($branchId);
        $currentBusinessDate = CurrentShift::businessDate($branchId);

        if (! $currentShiftId || ! $currentBusinessDate) {
            return [];
        }

        $oldShiftId = $order->shift_id ? (int) $order->shift_id : null;
        $oldBusinessDate = filled($order->business_date)
            ? substr((string) $order->business_date, 0, 10)
            : null;

        if ($oldShiftId === $currentShiftId && $oldBusinessDate === $currentBusinessDate) {
            return [
                'shift_id' => $currentShiftId,
                'business_date' => $currentBusinessDate,
            ];
        }

        ActivityLogger::log(
            'order.shift_restamped',
            (int) $order->company_id,
            "Order {$order->order_number} moved from shift ".($oldShiftId ?? 'none')." to {$currentShiftId}",
            ActivityLogger::changes(
                ['shift_id' => $oldShiftId, 'business_date' => $oldBusinessDate],
                ['shift_id' => $currentShiftId, 'business_date' => $currentBusinessDate]
            ),
            $order,
            $branchId,
            $currentShiftId
        );

        return [
            'shift_id' => $currentShiftId,
            'business_date' => $currentBusinessDate,
        ];
    }

    /**
     * Create an order with a guaranteed-unique order number (retries + suffix fallback).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createPosOrder(array $attributes, int $branchId): Order
    {
        $lastDuplicate = null;

        if (! array_key_exists('shift_id', $attributes)) {
            $attributes['shift_id'] = CurrentShift::id($branchId);
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $attributes['order_number'] = Order::allocateOrderNumber($branchId);

            try {
                return Order::create($attributes);
            } catch (QueryException $e) {
                if (! Order::isDuplicateKeyException($e)) {
                    throw $e;
                }
                $lastDuplicate = $e;
            }
        }

        throw $lastDuplicate ?? new \RuntimeException('Unable to allocate a unique order number.');
    }

    /**
     * @return list<string>
     */
    private function posActiveTabStatuses(): array
    {
        return OrderWorkflow::allActivePosTabStatuses();
    }

    private function assertPosOpenTab(Order $order): ?JsonResponse
    {
        if (! in_array($order->status, $this->posActiveTabStatuses(), true) || $order->payment_status !== 'unpaid') {
            return response()->json([
                'success' => false,
                'message' => 'Only open unpaid orders can be updated from the POS.',
            ], 422);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function posOrderDetailJson(Order $order): array
    {
        $payload = $this->posOrderJson($order);

        if ($order->branch) {
            $payload['branch'] = [
                'id' => $order->branch->id,
                'name' => $order->branch->name,
            ];
        }

        $payload['payments'] = $order->payments->map(fn ($payment) => [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'payment_method' => $payment->payment_method,
            'money_source' => $payment->moneySource ? [
                'id' => $payment->moneySource->id,
                'name' => $payment->moneySource->name,
            ] : null,
        ])->values()->all();

        $payload['kitchen_kots'] = $this->mapKitchenKotsForJson($order);
        $payload['kitchen_sync'] = app(KitchenKotService::class)->buildKitchenSyncReport($order);

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapKitchenKotsForJson(Order $order): array
    {
        $order->loadMissing('kitchenKots');

        return $order->kitchenKots->sortBy('kot_number')->values()->map(fn ($kot) => [
            'id' => $kot->id,
            'kot_number' => $kot->kot_number,
            'token_number' => $kot->token_number,
            'type' => $kot->type,
            'type_label' => $kot->typeLabel(),
            'is_reprint' => (bool) $kot->is_reprint,
            'lines' => $kot->lines ?? [],
            'printed_at' => $kot->printed_at?->toIso8601String(),
        ])->all();
    }

    /**
     * When kitchen printers are direct mode, create KOT slips as part of Save (unpaid tab).
     *
     * @return list<KitchenKot>
     */
    private function createDirectKitchenKotsForSavedOrder(Order $order, User $user): array
    {
        if (! $this->printJobService->hasDirectPrinters((int) $order->branch_id, 'kitchen')) {
            return [];
        }

        $order->loadMissing('items');
        $kots = $this->kitchenKotService->sendToKitchen(
            $order,
            $this->kitchenKotService->cartItemsFromOrder($order),
            $user
        );

        if ($kots !== [] && $this->companyAddonService->kitchenTrackingEnabled(Company::find($order->company_id))) {
            $this->orderTrackingService->markPlacedFromKitchen($order->fresh(), $user);
        }

        return $kots;
    }

    /**
     * @param  list<KitchenKot>  $kots
     * @return array{kots: list<array<string, mixed>>, browser_kot_ids: list<int>, desktop_jobs: int}
     */
    private function queueDirectKitchenKots(int $branchId, array $kots): array
    {
        if ($kots === []) {
            return [
                'kots' => [],
                'browser_kot_ids' => [],
                'desktop_jobs' => 0,
            ];
        }

        $printResult = $this->printJobService->queueKitchenKots(
            $branchId,
            $kots,
            asReprint: false,
            directOnly: true,
        );

        return [
            'kots' => collect($kots)->map(fn (KitchenKot $k) => [
                'id' => $k->id,
                'kot_number' => $k->kot_number,
                'token_number' => $k->token_number,
                'type' => $k->type,
            ])->values()->all(),
            'browser_kot_ids' => $printResult['browser_kot_ids'],
            'desktop_jobs' => $printResult['desktop_jobs'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function posOrderJsonWithKitchenSync(Order $order): array
    {
        $order->loadMissing(['items.menuItem', 'items.deal']);

        return array_merge(
            $this->posOrderJson($order),
            ['kitchen_sync' => app(KitchenKotService::class)->buildKitchenSyncReport($order)]
        );
    }

    private function posOrderJson(Order $order): array
    {
        $order->loadMissing(['items.menuItem', 'items.deal', 'table', 'waiter', 'statusLogs.changedByUser', 'kitchenKots']);

        $payload = $order->toArray();

        $company = Company::find($order->company_id);
        if ($this->companyAddonService->kitchenTrackingEnabled($company)) {
            if (in_array($order->status, ['placed', 'preparing'], true) && ! $order->expected_ready_at) {
                $order->expected_ready_at = $this->orderTrackingService->calculateExpectedReadyAt($order);
                $order->save();
            }

            $payload['tracking'] = [
                'expected_ready_at' => $order->expected_ready_at?->toIso8601String(),
                'estimated_prep_minutes' => $this->orderTrackingService->estimatePreparationMinutes($order),
                'allowed_next_statuses' => $this->orderTrackingService->allowedNextStatuses(
                    (string) $order->status,
                    (string) $order->type
                ),
                'status_label' => OrderWorkflow::label((string) $order->status, (string) $order->type),
                'allowed_next_labels' => collect(
                    $this->orderTrackingService->allowedNextStatuses((string) $order->status, (string) $order->type)
                )->mapWithKeys(fn (string $status) => [
                    $status => OrderWorkflow::label($status, (string) $order->type),
                ])->all(),
                'status_logs' => $order->statusLogs->map(fn ($log) => [
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'changed_at' => $log->changed_at?->toIso8601String(),
                    'source' => $log->source,
                    'notes' => $log->notes,
                    'changed_by_name' => $log->changedByUser?->name,
                ])->values()->all(),
            ];
        }

        $payload['kitchen_kots'] = $this->mapKitchenKotsForJson($order);

        return $payload;
    }

    /**
     * @return list<'kitchen'|'receipt'>
     */
    private function parsePrintNeeds(string $needs): array
    {
        $parts = array_filter(array_map('trim', explode(',', strtolower($needs))));
        $allowed = array_values(array_intersect($parts, ['kitchen', 'receipt']));

        return $allowed !== [] ? $allowed : ['kitchen', 'receipt'];
    }
}
