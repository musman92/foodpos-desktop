<?php

namespace App\Support;

use App\Models\IngredientUnit;

final class UnitLabel
{
    public static function forIngredientUnitId(string|int|null $unitId, ?int $companyId = null): string
    {
        if ($unitId === null || $unitId === '') {
            return '—';
        }

        $query = IngredientUnit::withoutGlobalScopes();
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if (is_numeric($unitId)) {
            $unit = $query->where('id', (int) $unitId)->first();

            return $unit?->displayLabel() ?? '—';
        }

        $needle = strtolower((string) $unitId);
        $unit = $query->where(function ($q) use ($needle, $unitId) {
            $q->whereRaw('LOWER(code) = ?', [$needle])
                ->orWhereRaw('LOWER(name) = ?', [$needle])
                ->orWhere('code', (string) $unitId);
        })->first();

        return $unit?->displayLabel() ?? '—';
    }
}
