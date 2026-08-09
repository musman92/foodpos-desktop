<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\IngredientCategory;
use App\Models\MenuItemStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    /**
     * Display inventory listing for a single branch at a time.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $branches = $this->accessibleBranches($user);

        $selectedBranchId = $request->filled('branch_id') && $user->isSuperAdmin()
            ? (int) $request->input('branch_id')
            : current_branch_id();

        if (! $selectedBranchId && $branches->isNotEmpty()) {
            $selectedBranchId = (int) ($user->branch_id ?: $branches->first()->id);
            if (! $branches->contains('id', $selectedBranchId)) {
                $selectedBranchId = (int) $branches->first()->id;
            }
        }

        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $search = trim((string) $request->input('search', ''));

        $ingredientCategories = IngredientCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $ingredientStock = collect();
        $menuItemStock = collect();

        if ($selectedBranchId) {
            $ingredientStockQuery = BranchStock::query()
                ->with([
                    'ingredient.category',
                    'ingredient.purchaseUnit',
                ])
                ->where('branch_id', $selectedBranchId);

            if ($user->company_id && ! $user->isSuperAdmin()) {
                $ingredientStockQuery->whereHas('branch', fn ($q) => $q->where('company_id', $user->company_id));
            }

            if ($categoryId) {
                $ingredientStockQuery->whereHas('ingredient', fn ($q) => $q->where('category_id', $categoryId));
            }

            $searchTerms = $this->searchTerms($search);
            if ($searchTerms !== []) {
                $ingredientStockQuery->whereHas('ingredient', function ($q) use ($searchTerms) {
                    $q->where(function ($inner) use ($searchTerms) {
                        foreach ($searchTerms as $term) {
                            $like = '%'.addcslashes($term, '%_\\').'%';
                            $inner->orWhere('name', 'like', $like)
                                ->orWhere('sku', 'like', $like);
                        }
                    });
                });
            }

            $ingredientStock = $ingredientStockQuery->orderBy('ingredient_id')->get();

            $menuItemStockQuery = MenuItemStock::query()
                ->with(['menuItem.category', 'menuItem.consumptionUnit'])
                ->where('branch_id', $selectedBranchId);

            if ($user->company_id && ! $user->isSuperAdmin()) {
                $menuItemStockQuery->whereHas('menuItem', fn ($q) => $q->where('company_id', $user->company_id));
            }

            if ($searchTerms !== []) {
                $menuItemStockQuery->whereHas('menuItem', function ($q) use ($searchTerms) {
                    $q->where(function ($inner) use ($searchTerms) {
                        foreach ($searchTerms as $term) {
                            $like = '%'.addcslashes($term, '%_\\').'%';
                            $inner->orWhere('name', 'like', $like)
                                ->orWhere('sku', 'like', $like);
                        }
                    });
                });
            }

            $menuItemStock = $menuItemStockQuery->orderBy('menu_item_id')->get();
        }

        return view('inventory.index', compact(
            'ingredientStock',
            'menuItemStock',
            'branches',
            'selectedBranchId',
            'ingredientCategories',
            'categoryId',
            'search',
        ));
    }

    /**
     * Split a comma-separated search into unique non-empty terms.
     *
     * @return list<string>
     */
    private function searchTerms(string $search): array
    {
        if ($search === '') {
            return [];
        }

        $terms = preg_split('/\s*,\s*/', $search) ?: [];
        $terms = array_values(array_filter(array_map('trim', $terms), fn (string $term) => $term !== ''));

        return array_values(array_unique($terms));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Branch>
     */
    private function accessibleBranches($user)
    {
        if ($user->isSuperAdmin()) {
            return Branch::where('status', 'active')->orderBy('name')->get();
        }

        if ($user->isCompanyAdmin() && $user->company_id) {
            $branchId = current_branch_id();
            if ($branchId) {
                return Branch::where('id', $branchId)->where('status', 'active')->get();
            }

            return collect();
        }

        $branches = $user->branches()->where('status', 'active')->orderBy('name')->get();
        if ($branches->isNotEmpty()) {
            return $branches;
        }

        if ($user->branch_id) {
            return Branch::where('id', $user->branch_id)->where('status', 'active')->get();
        }

        return collect();
    }
}
