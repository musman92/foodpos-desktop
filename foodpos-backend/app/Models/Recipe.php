<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Recipe extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function menuItemsAsDefault()
    {
        return $this->hasMany(MenuItem::class, 'default_recipe_id');
    }

    public function variantOptionLinks()
    {
        return $this->hasMany(MenuItemVariantRecipe::class);
    }

    public function displayLabel(): string
    {
        if ($this->code) {
            return "{$this->code} — {$this->name}";
        }

        return $this->name;
    }

    public function calculateCost(): float
    {
        if (! $this->relationLoaded('items')) {
            $this->load('items.ingredient');
        }

        return (float) $this->items->sum(fn (RecipeItem $item) => $item->lineCost());
    }

    /**
     * Sync ingredient lines: update existing, create new, delete removed.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncItems(array $rows): void
    {
        $keepIds = [];
        $companyId = $this->company_id;

        foreach ($rows as $row) {
            $ingredientId = (int) ($row['ingredient_id'] ?? 0);
            $quantity = $row['quantity'] ?? null;
            if ($ingredientId <= 0 || $quantity === null || $quantity === '' || (float) $quantity <= 0) {
                continue;
            }

            if ($companyId) {
                $ingredientBelongsToRecipeCompany = Ingredient::withoutGlobalScopes()
                    ->where('id', $ingredientId)
                    ->where('company_id', $companyId)
                    ->exists();

                if (! $ingredientBelongsToRecipeCompany) {
                    continue;
                }
            }

            $payload = [
                'quantity' => (float) $quantity,
                'unit_id' => $row['unit_id'] ?? null,
                'waste_percentage' => $row['waste_percentage'] ?? 0,
                'notes' => $row['notes'] ?? null,
            ];

            $item = $this->items()->where('ingredient_id', $ingredientId)->first();
            if ($item) {
                $item->update($payload);
                $keepIds[] = $item->id;
            } else {
                $created = $this->items()->create(array_merge($payload, [
                    'ingredient_id' => $ingredientId,
                ]));
                $keepIds[] = $created->id;
            }
        }

        if ($keepIds === []) {
            $this->items()->delete();
        } else {
            $this->items()->whereNotIn('id', $keepIds)->delete();
        }
    }

    public static function generateNextCode(?int $companyId): string
    {
        $codes = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^R(\d+)$/i', trim((string) $code), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        return 'R'.($next < 100
            ? str_pad((string) $next, 2, '0', STR_PAD_LEFT)
            : (string) $next);
    }

    public static function resolveCode(?int $companyId, ?string $requestedCode): string
    {
        $code = trim((string) $requestedCode);

        return $code !== '' ? $code : static::generateNextCode($companyId);
    }

    /**
     * Usage count for delete guards.
     */
    public function usageCount(): int
    {
        return (int) $this->menuItemsAsDefault()->count()
            + (int) $this->variantOptionLinks()->count();
    }

    /**
     * @return Collection<int, RecipeItem>
     */
    public function itemsWithIngredients(): Collection
    {
        if (! $this->relationLoaded('items')) {
            $this->load('items.ingredient');
        } else {
            $this->loadMissing('items.ingredient');
        }

        return $this->items;
    }
}
