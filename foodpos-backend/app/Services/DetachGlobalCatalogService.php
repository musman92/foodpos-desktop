<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\MenuItem;
use App\Models\PurchaseOrderItem;
use App\Models\MenuItemRecipeLine;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DetachGlobalCatalogService
{
    /** @var array<int, int> */
    private array $ingredientCategoryMap = [];

    /** @var array<int, int> */
    private array $ingredientMap = [];

    /** @var array<int, int> */
    private array $menuCategoryMap = [];

    /** @var list<int>|null */
    private ?array $globalIngredientCategoryIds = null;

    /** @var list<int>|null */
    private ?array $globalIngredientIds = null;

    /** @var list<int>|null */
    private ?array $globalMenuCategoryIds = null;

    /**
     * @return array{
     *     companies: int,
     *     ingredient_categories: array{reused: int, cloned: int, repointed: int},
     *     ingredients: array{reused: int, cloned: int, repointed: int, recipes_dropped: int, stock_merged: int},
     *     menu_categories: array{reused: int, cloned: int, repointed: int},
     *     purged: array{ingredient_categories: int, ingredients: int, menu_categories: int}
     * }
     */
    public function detach(?int $companyId, bool $dryRun = false, bool $purgeGlobals = false): array
    {
        $this->resetMaps();

        $stats = [
            'companies' => 0,
            'ingredient_categories' => ['reused' => 0, 'cloned' => 0, 'repointed' => 0],
            'ingredients' => ['reused' => 0, 'cloned' => 0, 'repointed' => 0, 'recipes_dropped' => 0, 'stock_merged' => 0],
            'menu_categories' => ['reused' => 0, 'cloned' => 0, 'repointed' => 0],
            'purged' => ['ingredient_categories' => 0, 'ingredients' => 0, 'menu_categories' => 0],
        ];

        $companies = $companyId
            ? Company::query()->where('id', $companyId)->get()
            : Company::query()->orderBy('id')->get();

        foreach ($companies as $company) {
            $this->detachForCompany($company, $dryRun, $stats);
            $stats['companies']++;
        }

        if ($purgeGlobals && ! $dryRun) {
            $this->purgeUnreferencedGlobals($stats['purged']);
        }

        return $stats;
    }

    /**
     * @param  array{
     *     companies: int,
     *     ingredient_categories: array{reused: int, cloned: int, repointed: int},
     *     ingredients: array{reused: int, cloned: int, repointed: int, recipes_dropped: int, stock_merged: int},
     *     menu_categories: array{reused: int, cloned: int, repointed: int},
     *     purged: array{ingredient_categories: int, ingredients: int, menu_categories: int}
     * }  $stats
     */
    private function detachForCompany(Company $company, bool $dryRun, array &$stats): void
    {
        $companyId = (int) $company->id;

        $run = function () use ($companyId, $dryRun, &$stats) {
            $globalIngredientsUsed = $this->globalIngredientIdsUsedByCompany($companyId);

            $globalIngredientCategoriesUsed = $this->globalIngredientCategoryIdsUsedByCompany(
                $companyId,
                $globalIngredientsUsed
            );

            foreach ($globalIngredientCategoriesUsed as $globalCategoryId) {
                $tenantCategoryId = $this->resolveIngredientCategory($globalCategoryId, $companyId, $dryRun, $stats);
                $repointed = $this->repointIngredientCategoryReferences($companyId, $globalCategoryId, $tenantCategoryId, $dryRun);
                $stats['ingredient_categories']['repointed'] += $repointed;
            }

            foreach ($globalIngredientsUsed as $globalIngredientId) {
                $tenantIngredientId = $this->resolveIngredient($globalIngredientId, $companyId, $dryRun, $stats);
                $this->repointIngredientReferences($companyId, $globalIngredientId, $tenantIngredientId, $dryRun, $stats);
            }

            $globalMenuCategoriesUsed = $this->globalMenuCategoryIdsUsedByCompany($companyId);

            foreach ($this->orderedGlobalMenuCategoryIds($globalMenuCategoriesUsed) as $globalCategoryId) {
                $tenantCategoryId = $this->resolveMenuCategory($globalCategoryId, $companyId, $dryRun, $stats);
                $repointed = $this->repointMenuCategoryReferences($companyId, $globalCategoryId, $tenantCategoryId, $dryRun);
                $stats['menu_categories']['repointed'] += $repointed;
            }
        };

        if ($dryRun) {
            $run();

            return;
        }

        DB::transaction($run);
    }

    private function resetMaps(): void
    {
        $this->ingredientCategoryMap = [];
        $this->ingredientMap = [];
        $this->menuCategoryMap = [];
        $this->globalIngredientCategoryIds = null;
        $this->globalIngredientIds = null;
        $this->globalMenuCategoryIds = null;
    }

    private function nameKey(string $name): string
    {
        return Str::lower(trim($name));
    }

    /** @return list<int> */
    private function globalIngredientCategoryIds(): array
    {
        if ($this->globalIngredientCategoryIds === null) {
            $this->globalIngredientCategoryIds = IngredientCategory::withoutTenantScope()
                ->global()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $this->globalIngredientCategoryIds;
    }

    /** @return list<int> */
    private function globalIngredientIds(): array
    {
        if ($this->globalIngredientIds === null) {
            $this->globalIngredientIds = Ingredient::withoutTenantScope()
                ->global()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $this->globalIngredientIds;
    }

    /** @return list<int> */
    private function globalMenuCategoryIds(): array
    {
        if ($this->globalMenuCategoryIds === null) {
            $this->globalMenuCategoryIds = Category::withoutTenantScope()
                ->global()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $this->globalMenuCategoryIds;
    }

    /** @return list<int> */
    private function companyBranchIds(int $companyId): array
    {
        return Branch::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return Collection<int, int> */
    private function globalIngredientIdsUsedByCompany(int $companyId): Collection
    {
        $globalIds = $this->globalIngredientIds();
        if ($globalIds === []) {
            return collect();
        }

        $branchIds = $this->companyBranchIds($companyId);
        $menuItemIds = MenuItem::withoutTenantScope()
            ->where('company_id', $companyId)
            ->pluck('id');

        $ids = collect();

        if ($menuItemIds->isNotEmpty()) {
            $ids = $ids->merge(
                MenuItemRecipeLine::query()
                    ->whereIn('menu_item_id', $menuItemIds)
                    ->whereIn('ingredient_id', $globalIds)
                    ->pluck('ingredient_id')
            );
        }

        $ids = $ids->merge(
            RecipeItem::query()
                ->whereIn('ingredient_id', $globalIds)
                ->whereHas('recipe', fn ($q) => $q->where('company_id', $companyId))
                ->pluck('ingredient_id')
        );

        if ($branchIds !== []) {
            $ids = $ids->merge(
                BranchStock::withoutGlobalScopes()
                    ->whereIn('branch_id', $branchIds)
                    ->whereIn('ingredient_id', $globalIds)
                    ->pluck('ingredient_id')
            );

            $ids = $ids->merge(
                StockMovement::withoutGlobalScopes()
                    ->whereIn('branch_id', $branchIds)
                    ->whereIn('ingredient_id', $globalIds)
                    ->pluck('ingredient_id')
            );
        }

        $ids = $ids->merge(
            DB::table('purchase_items')
                ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
                ->where('purchases.company_id', $companyId)
                ->where('purchase_items.item_type', 'ingredient')
                ->whereIn('purchase_items.item_id', $globalIds)
                ->pluck('purchase_items.item_id')
        );

        $ids = $ids->merge(
            DB::table('purchase_order_items')
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
                ->where('purchase_orders.company_id', $companyId)
                ->whereIn('purchase_order_items.ingredient_id', $globalIds)
                ->pluck('purchase_order_items.ingredient_id')
        );

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * @param  Collection<int, int>  $globalIngredientsUsed
     * @return Collection<int, int>
     */
    private function globalIngredientCategoryIdsUsedByCompany(int $companyId, Collection $globalIngredientsUsed): Collection
    {
        $globalCategoryIds = $this->globalIngredientCategoryIds();
        if ($globalCategoryIds === []) {
            return collect();
        }

        $ids = Ingredient::withoutTenantScope()
            ->where('company_id', $companyId)
            ->whereIn('category_id', $globalCategoryIds)
            ->pluck('category_id');

        if ($globalIngredientsUsed->isNotEmpty()) {
            $ids = $ids->merge(
                Ingredient::withoutTenantScope()
                    ->global()
                    ->whereIn('id', $globalIngredientsUsed)
                    ->whereNotNull('category_id')
                    ->pluck('category_id')
            );
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }

    /** @return Collection<int, int> */
    private function globalMenuCategoryIdsUsedByCompany(int $companyId): Collection
    {
        $globalIds = $this->globalMenuCategoryIds();
        if ($globalIds === []) {
            return collect();
        }

        return MenuItem::withoutTenantScope()
            ->where('company_id', $companyId)
            ->whereIn('category_id', $globalIds)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Parents before children so parent_id can be mapped when cloning.
     *
     * @param  Collection<int, int>  $usedIds
     * @return list<int>
     */
    private function orderedGlobalMenuCategoryIds(Collection $usedIds): array
    {
        if ($usedIds->isEmpty()) {
            return [];
        }

        $categories = Category::withoutTenantScope()
            ->global()
            ->whereIn('id', $usedIds)
            ->get()
            ->keyBy('id');

        $ordered = [];
        $visited = [];

        $visit = function (int $id) use (&$visit, &$ordered, &$visited, $categories, $usedIds) {
            if (isset($visited[$id])) {
                return;
            }
            $visited[$id] = true;

            $category = $categories->get($id);
            if (! $category) {
                return;
            }

            if ($category->parent_id && $usedIds->contains((int) $category->parent_id)) {
                $visit((int) $category->parent_id);
            }

            $ordered[] = $id;
        };

        foreach ($usedIds as $id) {
            $visit((int) $id);
        }

        return $ordered;
    }

    /**
     * @param  array{
     *     ingredient_categories: array{reused: int, cloned: int, repointed: int},
     *     ingredients: array{reused: int, cloned: int, repointed: int, recipes_dropped: int, stock_merged: int},
     *     menu_categories: array{reused: int, cloned: int, repointed: int}
     * }  $stats
     */
    private function resolveIngredientCategory(int $globalId, int $companyId, bool $dryRun, array &$stats): int
    {
        if (isset($this->ingredientCategoryMap[$globalId])) {
            return $this->ingredientCategoryMap[$globalId];
        }

        $global = IngredientCategory::withoutTenantScope()->global()->findOrFail($globalId);
        $existing = $this->findTenantIngredientCategoryByName($companyId, $global->name);

        if ($existing) {
            $this->ingredientCategoryMap[$globalId] = (int) $existing->id;
            $stats['ingredient_categories']['reused']++;

            return (int) $existing->id;
        }

        if ($dryRun) {
            $placeholder = -1 * $globalId;
            $this->ingredientCategoryMap[$globalId] = $placeholder;
            $stats['ingredient_categories']['cloned']++;

            return $placeholder;
        }

        $clone = IngredientCategory::withoutTenantScope()->create([
            'company_id' => $companyId,
            'name' => $global->name,
            'description' => $global->description,
            'sort_order' => $global->sort_order,
            'is_active' => $global->is_active,
        ]);

        $this->ingredientCategoryMap[$globalId] = (int) $clone->id;
        $stats['ingredient_categories']['cloned']++;

        return (int) $clone->id;
    }

    /**
     * @param  array{
     *     ingredient_categories: array{reused: int, cloned: int, repointed: int},
     *     ingredients: array{reused: int, cloned: int, repointed: int, recipes_dropped: int, stock_merged: int},
     *     menu_categories: array{reused: int, cloned: int, repointed: int}
     * }  $stats
     */
    private function resolveIngredient(int $globalId, int $companyId, bool $dryRun, array &$stats): int
    {
        if (isset($this->ingredientMap[$globalId])) {
            return $this->ingredientMap[$globalId];
        }

        $global = Ingredient::withoutTenantScope()->global()->findOrFail($globalId);
        $existing = $this->findTenantIngredientByName($companyId, $global->name);

        if ($existing) {
            $this->ingredientMap[$globalId] = (int) $existing->id;
            $stats['ingredients']['reused']++;

            return (int) $existing->id;
        }

        $categoryId = null;
        if ($global->category_id) {
            $categoryId = $this->resolveIngredientCategory((int) $global->category_id, $companyId, $dryRun, $stats);
            if ($categoryId < 0) {
                $categoryId = null;
            }
        }

        if ($dryRun) {
            $placeholder = -1 * $globalId;
            $this->ingredientMap[$globalId] = $placeholder;
            $stats['ingredients']['cloned']++;

            return $placeholder;
        }

        $clone = Ingredient::withoutTenantScope()->create([
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'name' => $global->name,
            'sku' => $global->sku,
            'base_unit_id' => $global->base_unit_id,
            'purchase_unit_id' => $global->purchase_unit_id,
            'consumption_unit_id' => $global->consumption_unit_id,
            'conversion_rate' => $global->conversion_rate,
            'purchase_price' => $global->purchase_price,
            'cost_per_unit' => $global->cost_per_unit,
            'min_stock_level' => $global->min_stock_level,
            'max_stock_level' => $global->max_stock_level,
            'track_stock' => $global->track_stock,
            'is_active' => $global->is_active,
            'description' => $global->description,
        ]);

        $this->ingredientMap[$globalId] = (int) $clone->id;
        $stats['ingredients']['cloned']++;

        return (int) $clone->id;
    }

    /**
     * @param  array{
     *     ingredient_categories: array{reused: int, cloned: int, repointed: int},
     *     ingredients: array{reused: int, cloned: int, repointed: int, recipes_dropped: int, stock_merged: int},
     *     menu_categories: array{reused: int, cloned: int, repointed: int}
     * }  $stats
     */
    private function resolveMenuCategory(int $globalId, int $companyId, bool $dryRun, array &$stats): int
    {
        if (isset($this->menuCategoryMap[$globalId])) {
            return $this->menuCategoryMap[$globalId];
        }

        $global = Category::withoutTenantScope()->global()->findOrFail($globalId);

        $parentId = null;
        if ($global->parent_id) {
            $parentId = $this->resolveMenuCategory((int) $global->parent_id, $companyId, $dryRun, $stats);
            if ($parentId < 0) {
                $parentId = null;
            }
        }

        $existing = $this->findTenantMenuCategory($companyId, $global);
        if ($existing) {
            $this->menuCategoryMap[$globalId] = (int) $existing->id;
            $stats['menu_categories']['reused']++;

            return (int) $existing->id;
        }

        if ($dryRun) {
            $placeholder = -1 * $globalId;
            $this->menuCategoryMap[$globalId] = $placeholder;
            $stats['menu_categories']['cloned']++;

            return $placeholder;
        }

        $clone = Category::withoutTenantScope()->create([
            'company_id' => $companyId,
            'parent_id' => $parentId,
            'name' => $global->name,
            'slug' => $this->uniqueMenuCategorySlug($companyId, (string) $global->slug),
            'description' => $global->description,
            'image' => $global->image,
            'sort_order' => $global->sort_order,
            'is_active' => $global->is_active,
        ]);

        $this->menuCategoryMap[$globalId] = (int) $clone->id;
        $stats['menu_categories']['cloned']++;

        return (int) $clone->id;
    }

    private function findTenantIngredientCategoryByName(int $companyId, string $name): ?IngredientCategory
    {
        $key = $this->nameKey($name);

        return IngredientCategory::withoutTenantScope()
            ->where('company_id', $companyId)
            ->get()
            ->first(fn (IngredientCategory $category) => $this->nameKey($category->name) === $key);
    }

    private function findTenantIngredientByName(int $companyId, string $name): ?Ingredient
    {
        $key = $this->nameKey($name);

        return Ingredient::withoutTenantScope()
            ->where('company_id', $companyId)
            ->get()
            ->first(fn (Ingredient $ingredient) => $this->nameKey($ingredient->name) === $key);
    }

    private function findTenantMenuCategory(int $companyId, Category $global): ?Category
    {
        $key = $this->nameKey($global->name);

        $byName = Category::withoutTenantScope()
            ->where('company_id', $companyId)
            ->get()
            ->first(fn (Category $category) => $this->nameKey($category->name) === $key);

        if ($byName) {
            return $byName;
        }

        if ($global->slug) {
            return Category::withoutTenantScope()
                ->where('company_id', $companyId)
                ->where('slug', $global->slug)
                ->first();
        }

        return null;
    }

    private function uniqueMenuCategorySlug(int $companyId, string $slug): string
    {
        $base = Str::slug($slug !== '' ? $slug : 'category');
        $candidate = $base;
        $suffix = 1;

        while (Category::withoutTenantScope()
            ->where('company_id', $companyId)
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function repointIngredientCategoryReferences(int $companyId, int $fromId, int $toId, bool $dryRun): int
    {
        if ($fromId === $toId) {
            return 0;
        }

        $query = Ingredient::withoutTenantScope()
            ->where('company_id', $companyId)
            ->where('category_id', $fromId);

        $count = (int) $query->count();

        if ($count > 0 && ! $dryRun && $toId > 0) {
            $query->update(['category_id' => $toId]);
        }

        return $count;
    }

    private function repointMenuCategoryReferences(int $companyId, int $fromId, int $toId, bool $dryRun): int
    {
        if ($fromId === $toId) {
            return 0;
        }

        $query = MenuItem::withoutTenantScope()
            ->where('company_id', $companyId)
            ->where('category_id', $fromId);

        $count = (int) $query->count();

        if ($count > 0 && ! $dryRun && $toId > 0) {
            $query->update(['category_id' => $toId]);
        }

        return $count;
    }

    /**
     * @param  array{
     *     ingredients: array{reused: int, cloned: int, repointed: int, recipes_dropped: int, stock_merged: int}
     * }  $stats
     */
    private function repointIngredientReferences(int $companyId, int $fromId, int $toId, bool $dryRun, array &$stats): void
    {
        if ($fromId === $toId) {
            return;
        }

        $canUpdate = ! $dryRun && $toId > 0;

        $branchIds = $this->companyBranchIds($companyId);
        $menuItemIds = MenuItem::withoutTenantScope()
            ->where('company_id', $companyId)
            ->pluck('id');

        if ($menuItemIds->isNotEmpty()) {
            $legacyLines = MenuItemRecipeLine::query()
                ->whereIn('menu_item_id', $menuItemIds)
                ->where('ingredient_id', $fromId)
                ->get();

            foreach ($legacyLines as $line) {
                if ($toId > 0) {
                    $duplicate = MenuItemRecipeLine::query()
                        ->where('menu_item_id', $line->menu_item_id)
                        ->where('ingredient_id', $toId)
                        ->where('recipe_scope', $line->recipe_scope)
                        ->where('id', '!=', $line->id)
                        ->exists();

                    if ($duplicate) {
                        if ($canUpdate) {
                            $line->delete();
                        }
                        $stats['ingredients']['recipes_dropped']++;

                        continue;
                    }
                }

                if ($canUpdate) {
                    $line->update(['ingredient_id' => $toId]);
                }
                $stats['ingredients']['repointed']++;
            }
        }

        $catalogItems = RecipeItem::query()
            ->where('ingredient_id', $fromId)
            ->whereHas('recipe', fn ($q) => $q->where('company_id', $companyId))
            ->get();

        foreach ($catalogItems as $item) {
            if ($toId > 0) {
                $duplicate = RecipeItem::query()
                    ->where('recipe_id', $item->recipe_id)
                    ->where('ingredient_id', $toId)
                    ->where('id', '!=', $item->id)
                    ->exists();

                if ($duplicate) {
                    if ($canUpdate) {
                        $item->delete();
                    }
                    $stats['ingredients']['recipes_dropped']++;

                    continue;
                }
            }

            if ($canUpdate) {
                $item->update(['ingredient_id' => $toId]);
            }
            $stats['ingredients']['repointed']++;
        }

        if ($branchIds !== []) {
            $stockRows = BranchStock::withoutGlobalScopes()
                ->whereIn('branch_id', $branchIds)
                ->where('ingredient_id', $fromId)
                ->get();

            foreach ($stockRows as $row) {
                if ($toId > 0) {
                    $existing = BranchStock::withoutGlobalScopes()
                        ->where('branch_id', $row->branch_id)
                        ->where('ingredient_id', $toId)
                        ->where('average_cost', $row->average_cost)
                        ->where('id', '!=', $row->id)
                        ->first();

                    if ($existing) {
                        if ($canUpdate) {
                            $existing->update([
                                'quantity' => (float) $existing->quantity + (float) $row->quantity,
                                'reserved_quantity' => (float) $existing->reserved_quantity + (float) $row->reserved_quantity,
                            ]);
                            $row->delete();
                        }
                        $stats['ingredients']['stock_merged']++;

                        continue;
                    }
                }

                if ($canUpdate) {
                    $row->update(['ingredient_id' => $toId]);
                }
                $stats['ingredients']['repointed']++;
            }

            $movementQuery = StockMovement::withoutGlobalScopes()
                ->whereIn('branch_id', $branchIds)
                ->where('ingredient_id', $fromId);

            $movementCount = (int) $movementQuery->count();
            if ($movementCount > 0) {
                if ($canUpdate) {
                    $movementQuery->update(['ingredient_id' => $toId]);
                }
                $stats['ingredients']['repointed'] += $movementCount;
            }
        }

        $purchaseItemIds = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.company_id', $companyId)
            ->where('purchase_items.item_type', 'ingredient')
            ->where('purchase_items.item_id', $fromId)
            ->pluck('purchase_items.id');

        $purchaseItemCount = $purchaseItemIds->count();
        if ($purchaseItemCount > 0) {
            if ($canUpdate) {
                DB::table('purchase_items')
                    ->whereIn('id', $purchaseItemIds)
                    ->update(['item_id' => $toId]);
            }
            $stats['ingredients']['repointed'] += $purchaseItemCount;
        }

        $poItemQuery = PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->withoutGlobalScopes()->where('company_id', $companyId))
            ->where('ingredient_id', $fromId);

        $poItemCount = (int) $poItemQuery->count();
        if ($poItemCount > 0) {
            if ($canUpdate) {
                $poItemQuery->update(['ingredient_id' => $toId]);
            }
            $stats['ingredients']['repointed'] += $poItemCount;
        }
    }

    /**
     * @param  array{ingredient_categories: int, ingredients: int, menu_categories: int}  $purged
     */
    private function purgeUnreferencedGlobals(array &$purged): void
    {
        foreach (Ingredient::withoutTenantScope()->global()->get() as $ingredient) {
            if ($this->globalIngredientHasReferences((int) $ingredient->id)) {
                continue;
            }

            $ingredient->delete();
            $purged['ingredients']++;
        }

        foreach (IngredientCategory::withoutTenantScope()->global()->get() as $category) {
            if ($this->globalIngredientCategoryHasReferences((int) $category->id)) {
                continue;
            }

            $category->delete();
            $purged['ingredient_categories']++;
        }

        $globalMenuCategories = Category::withoutTenantScope()->global()->get();
        $remaining = $globalMenuCategories->count();
        $guard = 0;

        while ($remaining > 0 && $guard < 50) {
            $guard++;
            $deletedThisPass = 0;

            foreach (Category::withoutTenantScope()->global()->get() as $category) {
                if ($this->globalMenuCategoryHasReferences((int) $category->id)) {
                    continue;
                }

                $category->delete();
                $purged['menu_categories']++;
                $deletedThisPass++;
            }

            $remaining = Category::withoutTenantScope()->global()->count();
            if ($deletedThisPass === 0) {
                break;
            }
        }
    }

    private function globalIngredientHasReferences(int $id): bool
    {
        return MenuItemRecipeLine::query()->where('ingredient_id', $id)->exists()
            || RecipeItem::query()->where('ingredient_id', $id)->exists()
            || BranchStock::withoutGlobalScopes()->where('ingredient_id', $id)->exists()
            || StockMovement::withoutGlobalScopes()->where('ingredient_id', $id)->exists()
            || DB::table('purchase_items')->where('item_type', 'ingredient')->where('item_id', $id)->exists()
            || PurchaseOrderItem::query()->where('ingredient_id', $id)->exists();
    }

    private function globalIngredientCategoryHasReferences(int $id): bool
    {
        return Ingredient::withoutTenantScope()->where('category_id', $id)->exists();
    }

    private function globalMenuCategoryHasReferences(int $id): bool
    {
        return MenuItem::withoutTenantScope()->where('category_id', $id)->exists()
            || Category::withoutTenantScope()->where('parent_id', $id)->exists();
    }
}
