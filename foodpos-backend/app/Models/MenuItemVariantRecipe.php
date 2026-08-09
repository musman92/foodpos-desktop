<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItemVariantRecipe extends Model
{
    use HasFactory;

    protected $table = 'menu_item_variant_recipes';

    protected $fillable = [
        'menu_item_id',
        'variant_id',
        'option_name',
        'recipe_id',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
