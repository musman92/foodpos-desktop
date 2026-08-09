<?php

namespace App\Support;

use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;

final class IngredientSku
{
    /**
     * Preview the next SKU without reserving it.
     */
    public static function peekNext(int $companyId): string
    {
        $max = Ingredient::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('sku')
            ->pluck('sku')
            ->map(fn ($sku) => is_numeric(trim((string) $sku)) ? (int) trim((string) $sku) : 0)
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    /**
     * Use provided SKU or allocate the next one when blank.
     */
    public static function resolve(int $companyId, ?string $requestedSku): string
    {
        $sku = trim((string) $requestedSku);

        return $sku !== '' ? $sku : self::allocate($companyId);
    }

    /**
     * Allocate the next numeric SKU/code for a company (e.g. 114, 115).
     */
    public static function allocate(int $companyId): string
    {
        return (string) DB::transaction(function () use ($companyId) {
            $max = Ingredient::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereNotNull('sku')
                ->lockForUpdate()
                ->pluck('sku')
                ->map(fn ($sku) => is_numeric(trim((string) $sku)) ? (int) trim((string) $sku) : 0)
                ->max();

            return (string) (($max ?? 0) + 1);
        });
    }
}
