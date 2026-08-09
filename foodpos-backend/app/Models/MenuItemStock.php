<?php

namespace App\Models;

use App\Traits\BranchScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItemStock extends Model
{
    use HasFactory, BranchScope;

    protected $table = 'menu_item_stock';

    protected $fillable = [
        'branch_id',
        'menu_item_id',
        'quantity',
        'unit_price',
        'expiry_date',
        'last_restocked_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'expiry_date' => 'date',
        'last_restocked_at' => 'datetime',
    ];

    /**
     * Get the branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the menu item.
     */
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
