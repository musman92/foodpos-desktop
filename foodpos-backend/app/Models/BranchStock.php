<?php

namespace App\Models;

use App\Traits\BranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchStock extends Model
{
    use HasFactory, BranchScope;

    protected $table = 'branch_stock';

    protected $fillable = [
        'branch_id',
        'ingredient_id',
        'quantity',
        'reserved_quantity',
        'unit_id',
        'average_cost',
        'last_restocked_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'reserved_quantity' => 'decimal:2',
        'average_cost' => 'decimal:2',
        'last_restocked_at' => 'datetime',
    ];

    /**
     * Get the branch that owns this stock.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the ingredient.
     */
    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * Get the unit of measure.
     */
    public function unit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    /**
     * Get available quantity (total - reserved).
     */
    public function getAvailableQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    /**
     * Check if stock is low.
     */
    public function isLowStock(): bool
    {
        if (!$this->ingredient || !$this->ingredient->min_stock_level) {
            return false;
        }

        return $this->available_quantity <= $this->ingredient->min_stock_level;
    }
}

