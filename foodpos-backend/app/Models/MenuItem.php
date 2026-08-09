<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'company_id',
        'category_id',
        'cuisine_id',
        'type',
        'default_recipe_id',
        'name',
        'slug',
        'description',
        'image',
        'price',
        'cost',
        'sku',
        'is_available',
        'track_inventory',
        'min_stock_level',
        'purchase_unit_id',
        'consumption_unit_id',
        'conversion_rate',
        'purchase_price',
        'preparation_time',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'is_available' => 'boolean',
        'track_inventory' => 'boolean',
        'min_stock_level' => 'decimal:2',
        'conversion_rate' => 'decimal:4',
        'purchase_price' => 'decimal:2',
    ];

    /**
     * Total piece stock at a branch (all batches combined).
     */
    public function totalStockAtBranch(int $branchId): float
    {
        return (float) MenuItemStock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('menu_item_id', $this->id)
            ->sum('quantity');
    }

    /**
     * Whether total stock at branch is at or below the minimum level.
     */
    public function isLowStockAtBranch(int $branchId): bool
    {
        if ($this->type !== 'single' || ! $this->track_inventory || ! $this->min_stock_level) {
            return false;
        }

        return $this->totalStockAtBranch($branchId) <= (float) $this->min_stock_level;
    }

    /**
     * Get the company that owns this menu item.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the category.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the cuisine.
     */
    public function cuisine()
    {
        return $this->belongsTo(Cuisine::class);
    }

    public function purchaseUnit()
    {
        return $this->belongsTo(IngredientUnit::class, 'purchase_unit_id');
    }

    public function consumptionUnit()
    {
        return $this->belongsTo(IngredientUnit::class, 'consumption_unit_id');
    }

    public function sellUnitLabel(): string
    {
        return $this->consumptionUnit?->displayLabel() ?? '—';
    }

    public static function calculateCostPerSellUnit(float $purchasePrice, float $conversionRate): float
    {
        if ($conversionRate <= 0) {
            return 0;
        }

        return round($purchasePrice / $conversionRate, 4);
    }

    /**
     * Apply default piece units when creating single tracked items.
     *
     * @return array<string, mixed>
     */
    public static function defaultUnitAttributes(int $companyId): array
    {
        $piece = IngredientUnit::findOrCreateDefaultPiece($companyId);

        return [
            'purchase_unit_id' => $piece->id,
            'consumption_unit_id' => $piece->id,
            'conversion_rate' => 1,
            'purchase_price' => 0,
        ];
    }

    /**
     * Catalog recipe used when the item has no variants (or as fallback).
     */
    public function defaultRecipe()
    {
        return $this->belongsTo(Recipe::class, 'default_recipe_id');
    }

    /**
     * Per-option recipe links (variant_id + option_name).
     */
    public function variantRecipes()
    {
        return $this->hasMany(MenuItemVariantRecipe::class);
    }

    /**
     * Legacy BOM lines (menu_item_recipe_lines). Prefer catalog via resolveRecipes().
     *
     * @deprecated Use defaultRecipe / variantRecipes
     */
    public function legacyRecipeLines()
    {
        return $this->hasMany(MenuItemRecipeLine::class);
    }

    /**
     * Compatibility alias — returns RecipeItem collection via resolveRecipes() when loaded path uses catalog.
     * Controllers that still sync legacy lines should use legacyRecipeLines().
     *
     * @deprecated
     */
    public function recipes()
    {
        return $this->legacyRecipeLines();
    }

    /**
     * Get all product addons for this menu item.
     */
    public function productAddons()
    {
        return $this->belongsToMany(ProductAddon::class, 'menu_item_product_addon')
            ->withTimestamps();
    }

    /**
     * Get all variants for this menu item.
     */
    public function variants()
    {
        return $this->belongsToMany(Variant::class, 'menu_item_variant')
            ->withPivot('price', 'option_prices', 'is_default')
            ->withTimestamps();
    }

    /**
     * Get all order items for this menu item.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get deals that include this menu item.
     */
    public function deals()
    {
        return $this->belongsToMany(Deal::class, 'deal_menu_item')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public static function recipeScopeKey(?int $variantId, ?string $optionName): string
    {
        if (! $variantId || $optionName === null || $optionName === '') {
            return '';
        }

        return $variantId.':'.$optionName;
    }

    /**
     * @param  array<string, mixed>|null  $variants
     * @return array{0: ?int, 1: ?string}
     */
    public static function variantContextFromOrderSelection(?array $variants): array
    {
        if (! is_array($variants) || $variants === []) {
            return [null, null];
        }

        $variantId = isset($variants['variant_id']) ? (int) $variants['variant_id'] : null;
        $optionName = isset($variants['option_name']) ? (string) $variants['option_name'] : null;

        if (! $variantId || ! $optionName) {
            return [null, null];
        }

        return [$variantId, $optionName];
    }

    /**
     * Resolve the catalog Recipe for a variant option, falling back to default_recipe_id.
     */
    public function resolveCatalogRecipe(?int $variantId = null, ?string $optionName = null): ?Recipe
    {
        $this->loadMissing(['variantRecipes.recipe.items.ingredient', 'defaultRecipe.items.ingredient']);

        if ($variantId && $optionName !== null && $optionName !== '') {
            $link = $this->variantRecipes
                ->first(fn (MenuItemVariantRecipe $row) => (int) $row->variant_id === (int) $variantId
                    && (string) $row->option_name === (string) $optionName);

            if ($link?->recipe) {
                return $link->recipe;
            }
        }

        return $this->defaultRecipe;
    }

    /**
     * Ingredient lines for a variant option (catalog), with legacy BOM fallback during transition.
     *
     * @return Collection<int, RecipeItem|MenuItemRecipeLine>
     */
    public function resolveRecipes(?int $variantId = null, ?string $optionName = null): Collection
    {
        $catalog = $this->resolveCatalogRecipe($variantId, $optionName);
        if ($catalog) {
            return $catalog->itemsWithIngredients()->values();
        }

        // Legacy fallback if catalog not linked yet
        if (! $this->relationLoaded('legacyRecipeLines') && ! $this->relationLoaded('recipes')) {
            $this->load('legacyRecipeLines.ingredient');
        }

        $lines = $this->relationLoaded('legacyRecipeLines')
            ? $this->legacyRecipeLines
            : ($this->relationLoaded('recipes') ? $this->recipes : $this->legacyRecipeLines()->with('ingredient')->get());

        $scope = static::recipeScopeKey($variantId, $optionName);
        if ($scope !== '') {
            $scoped = $lines->where('recipe_scope', $scope);
            if ($scoped->isNotEmpty()) {
                return $scoped->values();
            }
        }

        return $lines->where('recipe_scope', '')->values();
    }

    /**
     * Calculate cost from default (base) recipes.
     */
    public function calculateCost(): float
    {
        return $this->resolveRecipes(null, null)->sum(fn ($line) => $line->lineCost());
    }

    public function calculateCostForScope(string $recipeScope = ''): float
    {
        if ($recipeScope === '') {
            return $this->calculateCost();
        }

        $parts = explode(':', $recipeScope, 2);
        $variantId = isset($parts[0]) && is_numeric($parts[0]) ? (int) $parts[0] : null;
        $optionName = $parts[1] ?? null;

        return $this->resolveRecipes($variantId, $optionName)->sum(fn ($line) => $line->lineCost());
    }

    /**
     * @return Collection<string, Collection<int, RecipeItem|MenuItemRecipeLine>>
     */
    public function recipesGroupedByScope(): Collection
    {
        $this->loadMissing(['variantRecipes.recipe.items.ingredient', 'defaultRecipe.items.ingredient', 'variants']);

        $groups = collect();

        if ($this->defaultRecipe) {
            $groups->put('', $this->defaultRecipe->itemsWithIngredients()->values());
        }

        foreach ($this->variantRecipes as $link) {
            if (! $link->recipe) {
                continue;
            }
            $scope = static::recipeScopeKey((int) $link->variant_id, (string) $link->option_name);
            $groups->put($scope, $link->recipe->itemsWithIngredients()->values());
        }

        if ($groups->isNotEmpty()) {
            return $groups;
        }

        // Legacy
        if (! $this->relationLoaded('legacyRecipeLines')) {
            $this->load('legacyRecipeLines.ingredient');
        }

        return $this->legacyRecipeLines->groupBy(fn (MenuItemRecipeLine $line) => (string) ($line->recipe_scope ?? ''));
    }

    /**
     * Sync default + per-option catalog recipe links from form payload.
     *
     * @param  list<array{variant_id?: mixed, option_name?: mixed, recipe_id?: mixed}>  $variantRecipeRows
     */
    public function syncCatalogRecipes(?int $defaultRecipeId, array $variantRecipeRows = []): void
    {
        $this->default_recipe_id = $defaultRecipeId;
        $this->save();

        $keep = [];
        foreach ($variantRecipeRows as $row) {
            $variantId = (int) ($row['variant_id'] ?? 0);
            $optionName = trim((string) ($row['option_name'] ?? ''));
            $recipeId = (int) ($row['recipe_id'] ?? 0);
            if ($variantId <= 0 || $optionName === '' || $recipeId <= 0) {
                continue;
            }

            $link = $this->variantRecipes()
                ->where('variant_id', $variantId)
                ->where('option_name', $optionName)
                ->first();

            if ($link) {
                $link->update(['recipe_id' => $recipeId]);
                $keep[] = $link->id;
            } else {
                $created = $this->variantRecipes()->create([
                    'variant_id' => $variantId,
                    'option_name' => $optionName,
                    'recipe_id' => $recipeId,
                ]);
                $keep[] = $created->id;
            }
        }

        if ($keep === []) {
            $this->variantRecipes()->delete();
        } else {
            $this->variantRecipes()->whereNotIn('id', $keep)->delete();
        }
    }

    /**
     * Public URL for the menu image (storage uploads or static files under public/images/demo).
     */
    public function resolvedImageUrl(): ?string
    {
        $path = $this->image;
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'images/demo/')) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Whether the stored path points at a static file under public/ (not storage/app/public).
     */
    public static function imagePathIsPublicDemoAsset(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return str_starts_with(ltrim($path, '/'), 'images/demo/');
    }

    public static function imagePathIsPlatformMedia(?string $path): bool
    {
        return PlatformMedia::isPlatformMediaPath($path);
    }

    /**
     * Shared assets must not be deleted when a menu item is updated or removed.
     */
    public static function imagePathIsSharedAsset(?string $path): bool
    {
        return self::imagePathIsPublicDemoAsset($path) || self::imagePathIsPlatformMedia($path);
    }

    /**
     * Generate the next auto-increment style SKU (MI01, MI02, …) for a company.
     */
    public static function generateNextSku(?int $companyId): string
    {
        $skus = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku');

        $max = 0;
        foreach ($skus as $sku) {
            if (preg_match('/^MI(\d+)$/i', trim((string) $sku), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        return 'MI'.($next < 100
            ? str_pad((string) $next, 2, '0', STR_PAD_LEFT)
            : (string) $next);
    }

    /**
     * Resolve the SKU to store: use user input or auto-generate when blank.
     */
    public static function resolveSku(?int $companyId, ?string $requestedSku): string
    {
        $sku = trim((string) $requestedSku);

        return $sku !== '' ? $sku : static::generateNextSku($companyId);
    }

    /**
     * Ensure a persisted SKU exists (used before export when legacy rows have null sku).
     */
    public function ensureSku(): string
    {
        if (trim((string) ($this->sku ?? '')) !== '') {
            return (string) $this->sku;
        }

        $sku = static::resolveSku((int) $this->company_id, null);
        $this->forceFill(['sku' => $sku])->saveQuietly();

        return $sku;
    }

    /**
     * Assign SKUs to menu items that have none (same order as export: sort_order, name).
     */
    public static function backfillMissingSkus(): int
    {
        $updated = 0;

        static::query()
            ->where(function ($query) {
                $query->whereNull('sku')->orWhere('sku', '');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->each(function (MenuItem $menuItem) use (&$updated) {
                $menuItem->ensureSku();
                $updated++;
            });

        return $updated;
    }
}
