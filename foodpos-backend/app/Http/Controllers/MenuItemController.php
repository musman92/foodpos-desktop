<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportMenuItemsRequest;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\IngredientUnit;
use App\Models\MenuItem;
use App\Models\ProductAddon;
use App\Models\Recipe;
use App\Models\Variant;
use App\Services\MenuItemImageService;
use App\Services\MenuItemImportService;
use App\Support\ListingPerPage;
use App\Support\MenuItemExport;
use App\Support\MenuItemImportSampleExport;
use App\Support\MenuItemVariantCosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MenuItemController extends Controller
{
    public function __construct(
        private MenuItemImageService $menuItemImages,
        private MenuItemImportService $importer,
    ) {}
    /**
     * Display a listing of menu items.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = ListingPerPage::fromRequest($request);
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $availability = $this->normalizeTriStateFilter($request->input('available'));
        $trackInventory = $this->normalizeTriStateFilter($request->input('track_inventory'));

        $query = MenuItem::with(['company', 'category', 'cuisine', 'productAddons'])
            ->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%');
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($availability !== null) {
            $query->where('is_available', $availability);
        }

        if ($trackInventory !== null) {
            $query->where('track_inventory', $trackInventory);
        }

        $menuItems = $query->paginate($perPage)->withQueryString();

        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('menu-items.index', compact(
            'menuItems',
            'categories',
            'search',
            'categoryId',
            'availability',
            'trackInventory',
            'perPage'
        ));
    }

    /**
     * Show the form for creating a new menu item.
     */
    public function create()
    {
        $user = Auth::user();

        $categories = Category::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $cuisines = Cuisine::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $productAddons = ProductAddon::where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();

        $catalogRecipes = Recipe::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        // Fetch all variants from variants table (for "Select variant" dropdown in form)
        $variants = Variant::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($variant) {
                $options = $variant->options ?? [];

                return [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'code' => $variant->code,
                    'options' => is_array($options) ? $options : [],
                ];
            });

        $ingredientUnits = IngredientUnit::orderBy('name')->get();

        return view('menu-items.create', compact('categories', 'cuisines', 'productAddons', 'variants', 'ingredientUnits', 'catalogRecipes'));
    }

    /**
     * Store a newly created menu item.
     */
    public function store(StoreMenuItemRequest $request)
    {
        $user = Auth::user();

        // Generate slug from name
        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $counter = 1;

        // Ensure unique slug within company
        while (MenuItem::where('company_id', $user->company_id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        $imagePath = $this->menuItemImages->resolveFromRequest($request);

        $type = $request->type ?? 'single';

        // Create menu item
        $menuItem = MenuItem::create(array_merge([
            'company_id' => $user->company_id,
            'category_id' => $request->category_id,
            'cuisine_id' => $request->cuisine_id ?: null,
            'type' => $type,
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'image' => $imagePath,
            'price' => $request->price,
            'cost' => $type === 'single' ? ($request->input('cost', 0) ?? 0) : 0,
            'min_stock_level' => $type === 'single' ? ($request->input('min_stock_level', 0) ?? 0) : 0,
            'sku' => MenuItem::resolveSku($user->company_id, $request->sku),
            'preparation_time' => $request->filled('preparation_time') ? (int) $request->preparation_time : null,
            'is_available' => $request->has('is_available') ? true : false,
            'track_inventory' => $request->has('track_inventory') ? true : false,
        ], $this->singleTypeUnitAttributes($request, (int) $user->company_id, $type)));

        // Attach product addons
        if ($request->has('product_addons')) {
            $menuItem->productAddons()->sync($request->product_addons);
        }

        // Attach variants with option prices (same payload as edit form: option_prices[][name|price])
        if ($request->has('variants') && is_array($request->variants)) {
            $variantsData = [];
            foreach ($request->variants as $variantData) {
                $variantId = $variantData['variant_id'] ?? null;
                if (empty($variantId)) {
                    continue;
                }
                $optionPrices = [];
                $optionPricesInput = $variantData['option_prices'] ?? [];
                if (is_array($optionPricesInput)) {
                    foreach ($optionPricesInput as $optPrice) {
                        if (isset($optPrice['name']) && $optPrice['name'] !== '' && isset($optPrice['price'])) {
                            $optionPrices[$optPrice['name']] = (float) $optPrice['price'];
                        }
                    }
                }
                $optionPricesJson = ! empty($optionPrices) ? json_encode($optionPrices) : null;
                $variantsData[$variantId] = [
                    'price' => 0,
                    'option_prices' => $optionPricesJson,
                    'is_default' => isset($variantData['is_default']) && $variantData['is_default'] == '1',
                ];
            }
            if (! empty($variantsData)) {
                $menuItem->variants()->sync($variantsData);
            }
        }

        if ($request->type === 'recipe') {
            [$defaultRecipeId, $variantRecipes] = $this->catalogRecipePayload($request);
            $menuItem->syncCatalogRecipes($defaultRecipeId, $variantRecipes);
            $this->refreshAndPersistCalculatedCost($menuItem);
        }

        return redirect()
            ->route('menu-items.index')
            ->with('success', "Menu item '{$menuItem->name}' created successfully.");
    }

    /**
     * Duplicate a menu item and all of its configuration (recipes, addons, variants).
     */
    public function duplicate(MenuItem $menuItem): RedirectResponse
    {
        $copy = DB::transaction(function () use ($menuItem) {
            $menuItem->load(['productAddons', 'variants', 'variantRecipes', 'defaultRecipe']);

            $name = $this->uniqueDuplicateName($menuItem->name, (int) $menuItem->company_id);
            $slug = $this->uniqueSlug($name, (int) $menuItem->company_id);

            $copy = MenuItem::create([
                'company_id' => $menuItem->company_id,
                'category_id' => $menuItem->category_id,
                'cuisine_id' => $menuItem->cuisine_id,
                'type' => $menuItem->type,
                'default_recipe_id' => $menuItem->default_recipe_id,
                'name' => $name,
                'slug' => $slug,
                'description' => $menuItem->description,
                'image' => $this->menuItemImages->duplicatePath($menuItem->image),
                'price' => $menuItem->price,
                'cost' => $menuItem->cost,
                'min_stock_level' => $menuItem->min_stock_level,
                'purchase_unit_id' => $menuItem->purchase_unit_id,
                'consumption_unit_id' => $menuItem->consumption_unit_id,
                'conversion_rate' => $menuItem->conversion_rate,
                'purchase_price' => $menuItem->purchase_price,
                'sku' => MenuItem::resolveSku((int) $menuItem->company_id, null),
                'is_available' => $menuItem->is_available,
                'track_inventory' => $menuItem->track_inventory,
                'preparation_time' => $menuItem->preparation_time,
                'sort_order' => $menuItem->sort_order,
            ]);

            $copy->productAddons()->sync($menuItem->productAddons->pluck('id')->all());

            $variantsData = [];
            foreach ($menuItem->variants as $variant) {
                $variantsData[$variant->id] = [
                    'price' => $variant->pivot->price,
                    'option_prices' => $variant->pivot->option_prices,
                    'is_default' => (bool) $variant->pivot->is_default,
                ];
            }
            if ($variantsData !== []) {
                $copy->variants()->sync($variantsData);
            }

            foreach ($menuItem->variantRecipes as $link) {
                $copy->variantRecipes()->create([
                    'variant_id' => $link->variant_id,
                    'option_name' => $link->option_name,
                    'recipe_id' => $link->recipe_id,
                ]);
            }

            return $copy;
        });

        return redirect()
            ->route('menu-items.edit', $copy)
            ->with('success', "Menu item duplicated as '{$copy->name}'. You can adjust the copy and save.");
    }

    /**
     * Display the specified menu item.
     */
    public function show(MenuItem $menuItem)
    {
        $menuItem->load('company', 'category', 'cuisine', 'productAddons', 'defaultRecipe.items.ingredient', 'variantRecipes.recipe');
        $menuItem->load(['variants' => function ($query) use ($menuItem) {
            $query->withoutGlobalScope('tenant')
                ->withTrashed()
                ->where('variants.company_id', $menuItem->company_id);
        }]);

        $variantCosting = $menuItem->type === 'recipe'
            ? MenuItemVariantCosting::breakdown($menuItem)
            : [];

        return view('menu-items.show', compact('menuItem', 'variantCosting'));
    }

    /**
     * Show the form for editing the specified menu item.
     */
    public function edit(MenuItem $menuItem)
    {
        $user = Auth::user();

        $categories = Category::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $cuisines = Cuisine::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $productAddons = ProductAddon::where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();

        $menuItem->load(['productAddons', 'defaultRecipe', 'variantRecipes']);
        $menuItem->load(['variants' => function ($query) use ($menuItem) {
            $query->withoutGlobalScope('tenant')
                ->withTrashed()
                ->where('variants.company_id', $menuItem->company_id);
        }]);

        $linkedRecipeIds = collect([$menuItem->default_recipe_id])
            ->merge($menuItem->variantRecipes->pluck('recipe_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $catalogRecipes = Recipe::where('company_id', $user->company_id)
            ->where(function ($query) use ($linkedRecipeIds) {
                $query->where('is_active', true);
                if ($linkedRecipeIds !== []) {
                    $query->orWhereIn('id', $linkedRecipeIds);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $linkedVariantIds = $menuItem->variants->pluck('id')->map(fn ($id) => (int) $id)->all();

        $variants = Variant::where('company_id', $user->company_id)
            ->where(function ($query) use ($linkedVariantIds) {
                $query->where('is_active', true);
                if ($linkedVariantIds !== []) {
                    $query->orWhereIn('id', $linkedVariantIds);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($variant) {
                $options = $variant->options ?? [];

                return [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'code' => $variant->code,
                    'options' => is_array($options) ? $options : [],
                ];
            });

        $ingredientUnits = IngredientUnit::orderBy('name')->get();

        return view('menu-items.edit', compact('menuItem', 'categories', 'cuisines', 'productAddons', 'variants', 'ingredientUnits', 'catalogRecipes'));
    }

    /**
     * Update the specified menu item.
     */
    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem)
    {
        $user = Auth::user();

        $imagePath = $this->menuItemImages->resolveFromRequest($request, $menuItem->image);

        // Update menu item
        $updateData = [
            'category_id' => $request->category_id,
            'cuisine_id' => $request->cuisine_id ?: null,
            'type' => $request->type ?? 'single',
            'name' => $request->name,
            'description' => $request->description,
            'image' => $imagePath,
            'price' => $request->price,
            'sku' => MenuItem::resolveSku($user->company_id, $request->sku),
            'preparation_time' => $request->filled('preparation_time') ? (int) $request->preparation_time : null,
            'is_available' => $request->has('is_available') ? true : false,
            'track_inventory' => $request->has('track_inventory') ? true : false,
        ];

        if (($request->type ?? 'single') === 'single') {
            $updateData['cost'] = $request->input('cost', 0) ?? 0;
            $updateData['min_stock_level'] = $request->input('min_stock_level', 0) ?? 0;
            $updateData = array_merge(
                $updateData,
                $this->singleTypeUnitAttributes($request, (int) $user->company_id, 'single')
            );
        } else {
            $updateData['purchase_unit_id'] = null;
            $updateData['consumption_unit_id'] = null;
            $updateData['conversion_rate'] = 1;
            $updateData['purchase_price'] = 0;
        }

        $menuItem->update($updateData);

        // Sync product addons
        if ($request->has('product_addons')) {
            $menuItem->productAddons()->sync($request->product_addons);
        } else {
            $menuItem->productAddons()->sync([]);
        }

        // Attach variants with option prices (form sends variants[0][variant_id], variants[0][option_prices][0][name], etc.)
        if ($request->has('variants') && is_array($request->variants)) {
            $variantsData = [];
            foreach ($request->variants as $variantData) {
                $variantId = $variantData['variant_id'] ?? null;
                if (empty($variantId)) {
                    continue;
                }
                // Build option_prices from form (allow missing/empty option_prices)
                $optionPrices = [];
                $optionPricesInput = $variantData['option_prices'] ?? [];
                if (is_array($optionPricesInput)) {
                    foreach ($optionPricesInput as $optPrice) {
                        if (isset($optPrice['name']) && $optPrice['name'] !== '' && isset($optPrice['price'])) {
                            $optionPrices[$optPrice['name']] = (float) $optPrice['price'];
                        }
                    }
                }
                // option_prices is a JSON column: store as string (MySQL doesn't cast pivot attributes)
                $optionPricesJson = ! empty($optionPrices) ? json_encode($optionPrices) : null;
                $variantsData[$variantId] = [
                    'price' => 0,
                    'option_prices' => $optionPricesJson,
                    'is_default' => isset($variantData['is_default']) && $variantData['is_default'] == '1',
                ];
            }
            $menuItem->variants()->sync($variantsData);
        } else {
            // If no variants provided, remove all variants
            $menuItem->variants()->detach();
        }

        if (($request->type ?? 'single') === 'recipe') {
            [$defaultRecipeId, $variantRecipes] = $this->catalogRecipePayload($request);
            $menuItem->syncCatalogRecipes($defaultRecipeId, $variantRecipes);
            $this->refreshAndPersistCalculatedCost($menuItem);
        } else {
            $menuItem->syncCatalogRecipes(null, []);
            $menuItem->cost = $request->input('cost', 0) ?? 0;
            $menuItem->min_stock_level = $request->input('min_stock_level', 0) ?? 0;
            $menuItem->save();
        }

        return redirect()
            ->route('menu-items.index')
            ->with('success', "Menu item '{$menuItem->name}' updated successfully.");
    }

    /**
     * Remove the specified menu item.
     */
    public function destroy(MenuItem $menuItem)
    {
        $name = $menuItem->name;

        $this->menuItemImages->deleteIfOwned($menuItem->image);

        $menuItem->delete();

        return redirect()
            ->route('menu-items.index')
            ->with('success', "Menu item '{$name}' deleted successfully.");
    }

    public function import(): View
    {
        return view('menu-items.import', [
            'expectedHeaders' => MenuItemImportService::expectedHeaders(),
            'importResult' => session('importResult'),
        ]);
    }

    public function importSample(): StreamedResponse
    {
        return (new MenuItemImportSampleExport)->download();
    }

    public function export(): StreamedResponse
    {
        return (new MenuItemExport)->download();
    }

    public function importStore(ImportMenuItemsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $result = $this->importer->import($request->file('file'), (int) $user->company_id);

        $message = sprintf(
            'Import finished: %d created, %d updated, %d skipped.',
            $result['created'],
            $result['updated'],
            $result['skipped']
        );

        return redirect()
            ->route('menu-items.import')
            ->with('importResult', $result)
            ->with($result['created'] + $result['updated'] > 0 ? 'success' : 'error', $message);
    }

    private function uniqueSlug(string $name, int $companyId): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (MenuItem::where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function uniqueDuplicateName(string $name, int $companyId): string
    {
        $base = trim((string) preg_replace('/\s+\(Copy(?:\s+\d+)?\)$/i', '', $name));
        $candidate = $base.' (Copy)';
        $counter = 2;

        while (MenuItem::where('company_id', $companyId)->where('name', $candidate)->exists()) {
            $candidate = $base.' (Copy '.$counter.')';
            $counter++;
        }

        return $candidate;
    }

    private function refreshAndPersistCalculatedCost(MenuItem $menuItem): void
    {
        $menuItem->unsetRelation('defaultRecipe');
        $menuItem->unsetRelation('variantRecipes');
        $menuItem->load(['defaultRecipe.items.ingredient', 'variantRecipes.recipe.items.ingredient']);
        $menuItem->cost = $menuItem->calculateCost();
        $menuItem->save();
    }

    /**
     * When the item has variants, recipes must be per option — clear default_recipe_id.
     *
     * @return array{0: ?int, 1: list<array{variant_id?: mixed, option_name?: mixed, recipe_id?: mixed}>}
     */
    private function catalogRecipePayload(Request $request): array
    {
        $hasVariants = collect($request->input('variants', []))
            ->contains(fn ($variant) => ! empty($variant['variant_id']));

        $variantRecipes = is_array($request->input('variant_recipes'))
            ? $request->input('variant_recipes')
            : [];

        if ($hasVariants) {
            return [null, $variantRecipes];
        }

        return [
            $request->filled('default_recipe_id') ? (int) $request->default_recipe_id : null,
            [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function singleTypeUnitAttributes(Request $request, int $companyId, string $type): array
    {
        if ($type !== 'single') {
            return [
                'purchase_unit_id' => null,
                'consumption_unit_id' => null,
                'conversion_rate' => 1,
                'purchase_price' => 0,
            ];
        }

        $defaults = MenuItem::defaultUnitAttributes($companyId);

        return [
            'purchase_unit_id' => (int) $request->input('purchase_unit_id', $defaults['purchase_unit_id']),
            'consumption_unit_id' => (int) $request->input('consumption_unit_id', $defaults['consumption_unit_id']),
            'conversion_rate' => max((float) $request->input('conversion_rate', $defaults['conversion_rate']), 0.0001),
            'purchase_price' => (float) $request->input('purchase_price', $defaults['purchase_price']),
        ];
    }

    /**
     * Normalize yes/no listing filters to bool or null (all).
     */
    private function normalizeTriStateFilter(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }

}
