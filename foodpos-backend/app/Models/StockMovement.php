<?php

namespace App\Models;

use App\Traits\BranchScope;
use App\Traits\StampsBusinessDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BranchScope, HasFactory, StampsBusinessDate;

    protected $fillable = [
        'branch_id',
        'ingredient_id',
        'menu_item_id',
        'type',
        'movement',
        'quantity',
        'unit_id',
        'unit_cost',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
        'business_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'business_date' => 'date',
    ];

    /**
     * Get the branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the ingredient (null for menu-item-only movements).
     */
    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * Get the menu item (manual single-item stock adjustments).
     */
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * Get the unit name from config.
     */
    public function getUnitNameAttribute(): ?string
    {
        if (! $this->unit_id) {
            return null;
        }

        return \App\Support\UnitLabel::forIngredientUnitId($this->unit_id, $this->branch?->company_id);
    }

    /**
     * Get the user who created this movement.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the polymorphic reference.
     */
    public function reference()
    {
        return $this->morphTo();
    }
}
