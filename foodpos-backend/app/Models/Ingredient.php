<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'company_id',
        'created_by',
        'category_id',
        'name',
        'sku',
        'base_unit_id',
        'purchase_unit_id',
        'consumption_unit_id',
        'conversion_rate',
        'purchase_price',
        'cost_per_unit',
        'min_stock_level',
        'max_stock_level',
        'track_stock',
        'is_active',
        'description',
    ];

    protected $casts = [
        'conversion_rate' => 'decimal:4',
        'purchase_price' => 'decimal:2',
        'cost_per_unit' => 'decimal:4',
        'min_stock_level' => 'decimal:2',
        'max_stock_level' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function isGlobal(): bool
    {
        return $this->company_id === null;
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    public function scopeTenantOwned(Builder $query): Builder
    {
        return $query->whereNotNull('company_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(IngredientCategory::class, 'category_id');
    }

    public function purchaseUnit()
    {
        return $this->belongsTo(IngredientUnit::class, 'purchase_unit_id');
    }

    public function consumptionUnit()
    {
        return $this->belongsTo(IngredientUnit::class, 'consumption_unit_id');
    }

    /** @deprecated Use consumptionUnit() */
    public function unit()
    {
        return $this->consumptionUnit();
    }

    public function getUnitNameAttribute(): ?string
    {
        if ($this->consumption_unit_id) {
            return $this->consumptionUnit?->displayLabel()
                ?? \App\Support\UnitLabel::forIngredientUnitId($this->consumption_unit_id, $this->company_id);
        }

        if ($this->base_unit_id) {
            return \App\Support\UnitLabel::forIngredientUnitId($this->base_unit_id, $this->company_id);
        }

        return null;
    }

    public function getUnitAbbreviationAttribute(): ?string
    {
        if ($this->consumption_unit_id) {
            return $this->consumptionUnit?->code ?: (string) $this->consumption_unit_id;
        }

        return $this->base_unit_id;
    }

    public static function calculateCostPerUnit(float $purchasePrice, float $conversionRate): float
    {
        if ($conversionRate <= 0) {
            return 0;
        }

        return round($purchasePrice / $conversionRate, 4);
    }

    /**
     * Convert stored consumption quantity to purchase-unit quantity.
     */
    public function toPurchaseQuantity(float $consumptionQuantity): float
    {
        $rate = (float) ($this->conversion_rate ?: 1);

        if ($rate <= 0) {
            return $consumptionQuantity;
        }

        return $consumptionQuantity / $rate;
    }

    /**
     * Convert a purchase-unit quantity into consumption (stock) units.
     */
    public function toConsumptionQuantity(float $purchaseQuantity): float
    {
        $rate = (float) ($this->conversion_rate ?: 1);

        if ($rate <= 0) {
            return $purchaseQuantity;
        }

        return $purchaseQuantity * $rate;
    }

    /**
     * Whether purchase and consumption units differ enough to offer a unit toggle.
     */
    public function hasDualUnits(): bool
    {
        $rate = (float) ($this->conversion_rate ?: 1);
        if ($rate <= 0 || abs($rate - 1.0) < 0.0001) {
            return false;
        }

        if (! $this->purchase_unit_id || ! $this->consumption_unit_id) {
            return false;
        }

        return (int) $this->purchase_unit_id !== (int) $this->consumption_unit_id;
    }

    /**
     * Cost for one purchase unit from cost per consumption unit.
     */
    public function costPerPurchaseUnit(?float $costPerConsumptionUnit = null): float
    {
        $rate = (float) ($this->conversion_rate ?: 1);
        $cost = $costPerConsumptionUnit ?? (float) $this->cost_per_unit;

        return $cost * $rate;
    }

    /**
     * Catalog recipe lines that use this ingredient.
     */
    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }

    /**
     * Legacy menu-item BOM lines.
     */
    public function menuItemRecipeLines()
    {
        return $this->hasMany(MenuItemRecipeLine::class);
    }

    /**
     * @deprecated Prefer recipeItems() / menuItemRecipeLines()
     */
    public function recipes()
    {
        return $this->menuItemRecipeLines();
    }

    public function branchStock()
    {
        return $this->hasMany(BranchStock::class);
    }

    /**
     * Label for dropdowns and lists: "114 — Cooking Oil".
     */
    public function displayLabel(): string
    {
        if ($this->sku) {
            return "{$this->sku} — {$this->name}";
        }

        return $this->name;
    }

    public function stockForBranch(int $branchId)
    {
        return $this->branchStock()->where('branch_id', $branchId)->first();
    }
}
