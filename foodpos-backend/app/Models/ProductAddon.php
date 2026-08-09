<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAddon extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    public const TYPE_NONE = 'none';

    public const TYPE_SINGLE = 'single';

    public const TYPE_RECIPE = 'recipe';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'price',
        'type',
        'cost',
        'track_inventory',
        'menu_item_id',
        'tax_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'track_inventory' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function recipes()
    {
        return $this->hasMany(ProductAddonRecipe::class);
    }

    public function menuItems()
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_product_addon')
            ->withTimestamps();
    }

    public function calculateCost(): float
    {
        if ($this->type === self::TYPE_RECIPE) {
            if (! $this->relationLoaded('recipes')) {
                $this->load('recipes.ingredient');
            }

            return $this->recipes->sum(fn (ProductAddonRecipe $recipe) => $recipe->lineCost());
        }

        if ($this->type === self::TYPE_SINGLE && $this->menuItem) {
            return (float) ($this->menuItem->cost ?? 0);
        }

        return (float) ($this->cost ?? 0);
    }

    /**
     * @return list<array{id: int, name: string, price: float, code: ?string}>
     */
    public function posPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'price' => (float) $this->price,
        ];
    }

    public static function generateNextCode(?int $companyId): string
    {
        $codes = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^PA(\d+)$/i', trim((string) $code), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        return 'PA'.($next < 100
            ? str_pad((string) $next, 2, '0', STR_PAD_LEFT)
            : (string) $next);
    }

    public static function resolveCode(?int $companyId, ?string $requestedCode): string
    {
        $code = trim((string) $requestedCode);

        return $code !== '' ? $code : static::generateNextCode($companyId);
    }
}
