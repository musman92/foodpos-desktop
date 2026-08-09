<?php

namespace App\Models;

use App\Support\IngredientQuantity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Legacy per-menu-item BOM line (table: menu_item_recipe_lines).
 * Kept for migration/backfill compatibility; runtime costing uses catalog RecipeItem.
 */
class MenuItemRecipeLine extends Model
{
    use HasFactory;

    protected $table = 'menu_item_recipe_lines';

    protected $fillable = [
        'menu_item_id',
        'recipe_scope',
        'ingredient_id',
        'quantity',
        'unit_id',
        'waste_percentage',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'waste_percentage' => 'decimal:2',
    ];

    public function scopeLabel(): string
    {
        $scope = (string) ($this->recipe_scope ?? '');
        if ($scope === '') {
            return 'Default';
        }

        $parts = explode(':', $scope, 2);
        if (count($parts) !== 2) {
            return $scope;
        }

        $variant = Variant::find($parts[0]);

        return ($variant?->name ?? 'Variant').': '.$parts[1];
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function getUnitNameAttribute(): ?string
    {
        $this->ingredient?->loadMissing('consumptionUnit');

        return $this->ingredient?->consumptionUnit?->name
            ?? $this->ingredient?->unit_name
            ?? $this->unit_id;
    }

    public function getEffectiveQuantityAttribute(): float
    {
        $wasteFactor = 1 + ($this->waste_percentage / 100);

        return $this->quantity * $wasteFactor;
    }

    public function recipeUnitId(): ?string
    {
        if ($this->unit_id) {
            return (string) $this->unit_id;
        }

        $this->ingredient?->loadMissing('consumptionUnit');

        return IngredientQuantity::canonicalRecipeUnitId($this->ingredient);
    }

    public function quantityInBaseUnit(?float $quantity = null): ?float
    {
        $ingredient = $this->ingredient;
        if (! $ingredient) {
            return null;
        }

        $qty = $quantity ?? (float) $this->quantity;

        return IngredientQuantity::toConsumptionQuantity($ingredient, $qty, $this->recipeUnitId());
    }

    public function effectiveQuantityInBaseUnit(): ?float
    {
        return $this->quantityInBaseUnit((float) $this->effective_quantity);
    }

    public function lineCost(): float
    {
        if (! $this->ingredient) {
            return 0;
        }

        $qtyInBase = $this->effectiveQuantityInBaseUnit();
        if ($qtyInBase === null) {
            return (float) $this->ingredient->cost_per_unit * (float) $this->effective_quantity;
        }

        return (float) $this->ingredient->cost_per_unit * $qtyInBase;
    }

    public function stockEquivalentLabel(): ?string
    {
        $ingredient = $this->ingredient;
        if (! $ingredient?->base_unit_id) {
            return null;
        }

        if (IngredientQuantity::matchRecipeUnit($ingredient, $this->recipeUnitId()) === IngredientQuantity::UNIT_CONSUMPTION) {
            return null;
        }

        $qtyInBase = $this->quantityInBaseUnit();
        if ($qtyInBase === null) {
            return null;
        }

        $baseName = $ingredient->consumptionUnit?->name ?? $ingredient->unit_name ?? 'consumption unit';

        return number_format($qtyInBase, 4).' '.$baseName.' stock';
    }
}
