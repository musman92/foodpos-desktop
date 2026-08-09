<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'item_type',
        'item_id',
        'quantity',
        'quantity_returned',
        'unit_id',
        'unit_price',
        'total_price',
        'expiry_date',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_returned' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    /**
     * Get the purchase.
     */
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Get the item (polymorphic-like relationship).
     * Since we use enum values instead of class names, we manually resolve.
     * This avoids tenant scope filtering issues.
     */
    public function getItemAttribute()
    {
        if (!$this->item_type || !$this->item_id) {
            return null;
        }

        $typeMap = [
            'ingredient' => Ingredient::class,
            'menu_item' => MenuItem::class,
        ];

        $modelClass = $typeMap[$this->item_type] ?? null;
        if (!$modelClass) {
            return null;
        }

        // Load without global scopes to avoid tenant filtering issues
        // The purchase's company_id ensures we get the right data
        return $modelClass::withoutGlobalScopes()->find($this->item_id);
    }

    /**
     * Get the ingredient (if item_type is ingredient).
     * Kept for backward compatibility.
     */
    public function ingredient()
    {
        if ($this->item_type === 'ingredient') {
            return $this->item();
        }
        return null;
    }

    /**
     * Get the menu item (if item_type is menu_item).
     * Kept for backward compatibility.
     */
    public function menuItem()
    {
        if ($this->item_type === 'menu_item') {
            return $this->item();
        }
        return null;
    }

    /**
     * Quantity still available to return on this line.
     */
    public function returnableQuantity(): float
    {
        return max(0, round((float) $this->quantity - (float) ($this->quantity_returned ?? 0), 4));
    }

    /**
     * Get unit name from config.
     */
    public function getUnitNameAttribute(): ?string
    {
        if (! $this->unit_id) {
            return null;
        }

        $companyId = $this->purchase?->company_id;

        return \App\Support\UnitLabel::forIngredientUnitId($this->unit_id, $companyId);
    }
}
