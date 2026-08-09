<?php

namespace App\Models;

use App\Support\IngredientQuantity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
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

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
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
        $wasteFactor = 1 + ((float) $this->waste_percentage / 100);

        return (float) $this->quantity * $wasteFactor;
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
        if (! $ingredient) {
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
