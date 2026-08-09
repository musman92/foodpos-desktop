<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Http\Requests\UpdateInventoryAdjustmentRequest;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Support\IngredientPicker;
use App\Support\ListingPerPage;
use App\Support\TenantIngredientAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InventoryAdjustmentController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $perPage = ListingPerPage::fromRequest($request);

        $query = $this->adjustmentsQueryForUser($user);

        if ($request->filled('branch_id') && $user->isSuperAdmin()) {
            $query->where('branch_id', (int) $request->branch_id);
        } elseif ($branchId = current_branch_id()) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('from')) {
            $branchId = $user->isSuperAdmin() && $request->filled('branch_id')
                ? (int) $request->branch_id
                : current_branch_id();
            $query->where('created_at', '>=', tz()->localDateStartUtc($request->from, $branchId));
        }
        if ($request->filled('to')) {
            $branchId = $user->isSuperAdmin() && $request->filled('branch_id')
                ? (int) $request->branch_id
                : current_branch_id();
            $query->where('created_at', '<=', tz()->localDateEndUtc($request->to, $branchId));
        }

        if ($request->filled('ingredient')) {
            $term = trim((string) $request->ingredient);
            $query->where(function ($q) use ($term) {
                $q->whereHas('ingredient', function ($iq) use ($term) {
                    $iq->where('name', 'like', '%'.$term.'%');
                })->orWhereHas('menuItem', function ($mq) use ($term) {
                    $mq->where('name', 'like', '%'.$term.'%');
                });
            });
        }

        $table = (new StockMovement)->getTable();
        $adjustments = $query
            ->reorder()
            ->orderByDesc("{$table}.created_at")
            ->orderByDesc("{$table}.id")
            ->paginate($perPage)
            ->withQueryString();
        $branches = $this->branchesForUser($user);

        return view('inventory.adjustment-index', compact('adjustments', 'branches', 'perPage'));
    }

    public function show(Request $request, StockMovement $stockMovement): View
    {
        $user = Auth::user();
        $this->authorizeViewAdjustment($user, $stockMovement);

        $stockMovement->load(['branch', 'ingredient', 'menuItem', 'creator']);

        return view('inventory.adjustment-show', compact('stockMovement'));
    }

    public function create(Request $request): View
    {
        $user = Auth::user();

        $branches = $this->branchesForUser($user);
        $ingredients = IngredientPicker::options(IngredientPicker::CONTEXT_INVENTORY_ADJUSTMENT, $user);
        $menuItems = $this->menuItemsForUser($user);

        if ($branches->isEmpty()) {
            abort(403, 'No branch is available for inventory adjustment.');
        }

        $selectedBranchId = $request->get('branch_id');
        if (! $selectedBranchId) {
            $selectedBranchId = current_branch_id() ?? $user->branch_id;
        }
        if (! $selectedBranchId) {
            $selectedBranchId = $branches->first()->id;
        }

        return view('inventory.adjustment', [
            'branches' => $branches,
            'ingredients' => $ingredients,
            'menuItems' => $menuItems,
            'selectedBranchId' => $selectedBranchId,
            'editingMovement' => null,
        ]);
    }

    /**
     * Current on-hand stock for the adjustment form (JSON).
     */
    public function stock(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'adjustable' => ['required', 'string', 'in:ingredient,menu_item'],
            'ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'menu_item_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'exclude_movement_id' => ['nullable', 'integer', 'exists:stock_movements,id'],
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);
        if ($user->company_id && (int) $branch->company_id !== (int) $user->company_id) {
            abort(403);
        }

        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            $allowed = (int) $user->branch_id === (int) $branch->id
                || $user->branches()->where('branches.id', $branch->id)->exists();
            if (! $allowed) {
                abort(403);
            }
        }

        if ($validated['adjustable'] === 'ingredient') {
            $ingredientId = (int) ($validated['ingredient_id'] ?? 0);
            if ($ingredientId <= 0) {
                return response()->json(['message' => 'Select an ingredient.'], 422);
            }

            $ingredient = Ingredient::query()
                ->withoutGlobalScopes()
                ->with([
                    'consumptionUnit' => fn ($q) => $q->withoutGlobalScopes(),
                    'purchaseUnit' => fn ($q) => $q->withoutGlobalScopes(),
                ])
                ->findOrFail($ingredientId);

            if ($user->company_id && ! TenantIngredientAccess::isUsableByCompany($ingredient, (int) $user->company_id)) {
                abort(403);
            }

            $qty = (float) BranchStock::query()
                ->withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->where('ingredient_id', $ingredient->id)
                ->sum('quantity');

            $qty = $this->stockQtyExcludingMovement(
                $qty,
                isset($validated['exclude_movement_id']) ? (int) $validated['exclude_movement_id'] : null,
                (int) $branch->id,
                ingredientId: $ingredient->id,
                menuItemId: null
            );

            $dual = $ingredient->hasDualUnits();
            $consumptionName = (string) ($ingredient->consumptionUnit?->name ?: $ingredient->unit_name ?: 'unit');
            $purchaseName = (string) ($ingredient->purchaseUnit?->name ?: $consumptionName);

            return response()->json([
                'quantity_consumption' => round($qty, 4),
                'quantity_purchase' => round($ingredient->toPurchaseQuantity($qty), 4),
                'consumption_unit_name' => $consumptionName,
                'purchase_unit_name' => $purchaseName,
                'conversion_rate' => (float) ($ingredient->conversion_rate ?: 1),
                'has_dual_units' => $dual,
            ]);
        }

        $menuItemId = (int) ($validated['menu_item_id'] ?? 0);
        if ($menuItemId <= 0) {
            return response()->json(['message' => 'Select a menu item.'], 422);
        }

        $menuItem = MenuItem::findOrFail($menuItemId);
        if ($user->company_id && (int) $menuItem->company_id !== (int) $user->company_id) {
            abort(403);
        }

        $qty = $menuItem->totalStockAtBranch((int) $branch->id);
        $qty = $this->stockQtyExcludingMovement(
            $qty,
            isset($validated['exclude_movement_id']) ? (int) $validated['exclude_movement_id'] : null,
            (int) $branch->id,
            ingredientId: null,
            menuItemId: $menuItem->id
        );

        return response()->json([
            'quantity_consumption' => round($qty, 4),
            'quantity_purchase' => round($qty, 4),
            'consumption_unit_name' => 'pcs',
            'purchase_unit_name' => 'pcs',
            'conversion_rate' => 1.0,
            'has_dual_units' => false,
        ]);
    }

    public function store(StoreInventoryAdjustmentRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $branch = Branch::findOrFail($request->branch_id);
        if ($user->company_id && (int) $branch->company_id !== (int) $user->company_id) {
            abort(403);
        }

        try {
            $delta = $this->resolveConsumptionDelta($request);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        try {
            if ($request->adjustable === 'ingredient') {
                $ingredient = Ingredient::findOrFail($request->ingredient_id);
                if ($user->company_id && ! TenantIngredientAccess::isUsableByCompany($ingredient, (int) $user->company_id)) {
                    abort(403);
                }
                $this->inventoryService->adjustIngredientStockManually(
                    (int) $request->branch_id,
                    (int) $request->ingredient_id,
                    $delta,
                    $user->id,
                    $request->notes
                );
            } else {
                $menuItem = MenuItem::findOrFail($request->menu_item_id);
                if ($user->company_id && (int) $menuItem->company_id !== (int) $user->company_id) {
                    abort(403);
                }
                $this->inventoryService->adjustMenuItemStockManually(
                    (int) $request->branch_id,
                    (int) $request->menu_item_id,
                    $delta,
                    $user->id,
                    $request->notes
                );
            }
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('inventory.adjustment.index', ['branch_id' => $request->branch_id])
            ->with('success', 'Inventory adjustment saved.');
    }

    public function edit(StockMovement $stockMovement): View
    {
        $user = Auth::user();
        $this->authorizeViewAdjustment($user, $stockMovement);

        $branches = $this->branchesForUser($user);
        $ingredients = IngredientPicker::options(IngredientPicker::CONTEXT_INVENTORY_ADJUSTMENT, $user);
        $menuItems = $this->menuItemsForUser($user);
        $selectedBranchId = $stockMovement->branch_id;
        $editingMovement = $stockMovement->load(['ingredient', 'menuItem', 'branch']);

        return view('inventory.adjustment', compact(
            'branches',
            'ingredients',
            'menuItems',
            'selectedBranchId',
            'editingMovement'
        ));
    }

    public function update(UpdateInventoryAdjustmentRequest $request, StockMovement $stockMovement): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeViewAdjustment($user, $stockMovement);

        try {
            $delta = $this->resolveConsumptionDelta($request, $stockMovement);
            $this->inventoryService->replaceManualAdjustment(
                $stockMovement,
                $delta,
                (int) $user->id,
                (string) $request->notes
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('inventory.adjustment.index', ['branch_id' => $stockMovement->branch_id])
            ->with('success', 'Inventory adjustment updated.');
    }

    public function destroy(StockMovement $stockMovement): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeViewAdjustment($user, $stockMovement);
        $branchId = $stockMovement->branch_id;

        try {
            $this->inventoryService->deleteManualAdjustment($stockMovement);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('inventory.adjustment.index', ['branch_id' => $branchId])
            ->with('success', 'Inventory adjustment deleted.');
    }

    /**
     * Convert form quantity (mode + unit) into consumption-unit delta for stock services.
     * When editing, $exclude is the movement being replaced so "exact" uses stock before that movement.
     */
    protected function resolveConsumptionDelta(Request $request, ?StockMovement $exclude = null): float
    {
        $mode = (string) $request->input('mode', 'change');
        $unit = (string) $request->input('unit', 'consumption');
        $inputQty = (float) $request->input('quantity');
        $branchId = (int) $request->branch_id;

        if ($request->adjustable === 'ingredient') {
            $ingredient = Ingredient::query()
                ->withoutGlobalScopes()
                ->findOrFail((int) $request->ingredient_id);

            $inConsumption = $unit === 'purchase'
                ? $ingredient->toConsumptionQuantity($inputQty)
                : $inputQty;

            $current = (float) BranchStock::query()
                ->withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('ingredient_id', $ingredient->id)
                ->sum('quantity');

            $current = $this->stockQtyExcludingMovement(
                $current,
                $exclude?->id,
                $branchId,
                ingredientId: $ingredient->id,
                menuItemId: null
            );
        } else {
            $menuItem = MenuItem::findOrFail((int) $request->menu_item_id);
            $inConsumption = $inputQty;
            $current = $menuItem->totalStockAtBranch($branchId);
            $current = $this->stockQtyExcludingMovement(
                $current,
                $exclude?->id,
                $branchId,
                ingredientId: null,
                menuItemId: $menuItem->id
            );
        }

        if ($mode === 'exact') {
            if ($inConsumption < -0.0001) {
                throw new \InvalidArgumentException('Exact quantity cannot be negative.');
            }
            $delta = round($inConsumption - $current, 4);
        } else {
            $delta = round($inConsumption, 4);
        }

        if (abs($delta) < 0.01) {
            throw new \InvalidArgumentException(
                $mode === 'exact'
                    ? 'Exact quantity matches current stock — nothing to adjust.'
                    : 'Quantity change must be non-zero.'
            );
        }

        return $delta;
    }

    protected function stockQtyExcludingMovement(
        float $qty,
        ?int $excludeMovementId,
        int $branchId,
        ?int $ingredientId,
        ?int $menuItemId
    ): float {
        if (! $excludeMovementId) {
            return $qty;
        }

        $movement = StockMovement::query()
            ->withoutGlobalScopes()
            ->whereKey($excludeMovementId)
            ->where('type', 'adjustment')
            ->where('branch_id', $branchId)
            ->first();

        if (! $movement) {
            return $qty;
        }

        if ($ingredientId && (int) $movement->ingredient_id !== (int) $ingredientId) {
            return $qty;
        }
        if ($menuItemId && (int) $movement->menu_item_id !== (int) $menuItemId) {
            return $qty;
        }

        return round($qty - $this->inventoryService->signedAdjustmentDelta($movement), 4);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\StockMovement>  $query
     */
    protected function adjustmentsQueryForUser($user)
    {
        $query = StockMovement::withoutGlobalScope('branch')
            ->where('type', 'adjustment')
            ->with(['branch', 'ingredient', 'menuItem', 'creator']);

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $query->whereHas('branch', function ($q) use ($user) {
            if ($user->company_id) {
                $q->where('company_id', (int) $user->company_id);
            }
        });

        if ($branchId = current_branch_id()) {
            $query->where('branch_id', $branchId);

            return $query;
        }

        $ids = $user->branches()->pluck('branches.id')->all();
        if ($ids !== []) {
            $query->whereIn('branch_id', array_map('intval', $ids));
        } elseif ($user->branch_id) {
            $query->where('branch_id', (int) $user->branch_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    protected function authorizeViewAdjustment($user, StockMovement $stockMovement): void
    {
        if ($stockMovement->type !== 'adjustment') {
            abort(404);
        }

        $stockMovement->loadMissing('branch');

        if ($user->isSuperAdmin()) {
            return;
        }

        $branch = $stockMovement->branch;
        if (! $branch) {
            abort(404);
        }

        if ($user->company_id && (int) $branch->company_id !== (int) $user->company_id) {
            abort(403);
        }

        if ($user->company_id) {
            if ($stockMovement->ingredient_id) {
                $stockMovement->loadMissing('ingredient');
                $ingredient = $stockMovement->ingredient;
                if ($ingredient && ! TenantIngredientAccess::isUsableByCompany($ingredient, (int) $user->company_id)) {
                    abort(403);
                }
            }
            if ($stockMovement->menu_item_id) {
                $stockMovement->loadMissing('menuItem');
                $menuItem = $stockMovement->menuItem;
                if ($menuItem && (int) $menuItem->company_id !== (int) $user->company_id) {
                    abort(403);
                }
            }
        }

        if ($user->isCompanyAdmin()) {
            $currentBranchId = current_branch_id();
            if ($currentBranchId && (int) $branch->id === $currentBranchId) {
                return;
            }
            abort(403);
        }

        $allowed = (int) $user->branch_id === (int) $branch->id
            || $user->branches()->where('branches.id', $branch->id)->exists();

        if (! $allowed) {
            abort(403);
        }
    }

    private function branchesForUser($user)
    {
        if ($user->isSuperAdmin()) {
            return Branch::where('status', 'active')->orderBy('name')->get();
        }

        $currentBranchId = current_branch_id();
        if ($currentBranchId) {
            return Branch::where('id', $currentBranchId)->where('status', 'active')->get();
        }

        return $user->branches()->where('status', 'active')->orderBy('name')->get();
    }

    private function menuItemsForUser($user)
    {
        $q = MenuItem::query()
            ->where('type', 'single')
            ->where('track_inventory', true)
            ->orderBy('name');
        if ($user->company_id && ! $user->isSuperAdmin()) {
            $q->where('company_id', $user->company_id);
        }

        return $q->get();
    }
}
