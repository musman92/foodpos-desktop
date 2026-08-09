<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngredientCategory extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
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

    /**
     * Get the company that owns this ingredient category.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all ingredients in this category.
     */
    public function ingredients()
    {
        return $this->hasMany(Ingredient::class, 'category_id');
    }

    /**
     * Label for dropdowns and lists: "C01 — Dairy".
     */
    public function displayLabel(): string
    {
        if ($this->code) {
            return "{$this->code} — {$this->name}";
        }

        return $this->name;
    }

    /**
     * Generate the next auto-increment style code (C01, C02, …) for a company.
     */
    public static function generateNextCode(?int $companyId): string
    {
        $codes = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^C(\d+)$/i', trim((string) $code), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        return 'C'.($next < 100
            ? str_pad((string) $next, 2, '0', STR_PAD_LEFT)
            : (string) $next);
    }

    /**
     * Resolve the code to store: use user input or auto-generate when blank.
     */
    public static function resolveCode(?int $companyId, ?string $requestedCode): string
    {
        $code = trim((string) $requestedCode);

        return $code !== '' ? $code : static::generateNextCode($companyId);
    }
}
