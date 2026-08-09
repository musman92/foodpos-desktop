<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngredientUnit extends Model
{
    use HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseIngredients()
    {
        return $this->hasMany(Ingredient::class, 'purchase_unit_id');
    }

    public function consumptionIngredients()
    {
        return $this->hasMany(Ingredient::class, 'consumption_unit_id');
    }

    public function isUsedByIngredients(): bool
    {
        return Ingredient::query()
            ->where('purchase_unit_id', $this->id)
            ->orWhere('consumption_unit_id', $this->id)
            ->exists();
    }

    public function linkedIngredientsCount(): int
    {
        return (int) Ingredient::query()
            ->where(function ($q) {
                $q->where('purchase_unit_id', $this->id)
                    ->orWhere('consumption_unit_id', $this->id);
            })
            ->distinct()
            ->count('id');
    }

    /**
     * Label for dropdowns and lists: "C01 — Kilogram".
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

    /**
     * Canonical string id stored on recipe lines and legacy base_unit_id fields.
     */
    public function baseUnitIdValue(): string
    {
        return (string) $this->id;
    }

    /**
     * Default piece/sell unit for menu items (creates one if missing).
     */
    public static function findOrCreateDefaultPiece(int $companyId): self
    {
        $existing = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereRaw('LOWER(name) IN (?, ?, ?)', ['piece', 'pieces', 'pcs'])
                    ->orWhereRaw('LOWER(code) IN (?, ?)', ['pcs', 'pc']);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        return static::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'name' => 'Piece',
            'code' => static::resolveCode($companyId, 'pcs'),
            'description' => null,
        ]);
    }
}
