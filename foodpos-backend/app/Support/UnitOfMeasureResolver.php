<?php

namespace App\Support;

use App\Models\IngredientUnit;
use App\Models\UnitOfMeasure;

class UnitOfMeasureResolver
{
    /**
     * Resolve an ingredient unit id, units_of_measure id, or legacy abbreviation to units_of_measure.id.
     */
    public function resolveId(string|int|null $unitIdentifier, int $companyId): ?int
    {
        if ($unitIdentifier === null || $unitIdentifier === '') {
            return null;
        }

        if (is_numeric($unitIdentifier)) {
            $numericId = (int) $unitIdentifier;

            $existingUom = UnitOfMeasure::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('id', $numericId)
                ->first();

            if ($existingUom) {
                return $numericId;
            }

            $ingredientUnit = IngredientUnit::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->find($numericId);

            if ($ingredientUnit) {
                return $this->resolveFromIngredientUnit($ingredientUnit, $companyId);
            }

            return null;
        }

        $key = (string) $unitIdentifier;

        $unit = UnitOfMeasure::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('abbreviation', $key)
            ->first();

        if ($unit) {
            return (int) $unit->id;
        }

        $ingredientUnit = IngredientUnit::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($key) {
                $query->where('code', $key)
                    ->orWhereRaw('LOWER(code) = ?', [strtolower($key)])
                    ->orWhereRaw('LOWER(name) = ?', [strtolower($key)]);
            })
            ->first();

        if ($ingredientUnit) {
            return $this->resolveFromIngredientUnit($ingredientUnit, $companyId);
        }

        return null;
    }

    protected function resolveFromIngredientUnit(IngredientUnit $ingredientUnit, int $companyId): int
    {
        $abbrev = $ingredientUnit->code ?: 'u'.$ingredientUnit->id;
        $name = $ingredientUnit->name;

        $unit = UnitOfMeasure::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($abbrev, $name) {
                $query->where('abbreviation', $abbrev)->orWhere('name', $name);
            })
            ->first();

        if ($unit) {
            return (int) $unit->id;
        }

        $unit = UnitOfMeasure::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'name' => $name,
            'abbreviation' => $abbrev,
            'type' => $this->guessUnitType($abbrev, $name),
            'is_base_unit' => true,
        ]);

        return (int) $unit->id;
    }

    protected function guessUnitType(string $abbreviation, string $name): string
    {
        $haystack = strtolower($abbreviation.' '.$name);

        $weightUnits = ['g', 'kg', 'mg', 'gram', 'kilogram', 'lb', 'oz'];
        $volumeUnits = ['ml', 'l', 'liter', 'litre', 'ltr', 'fl oz', 'cup'];
        $countUnits = ['pcs', 'pc', 'piece', 'pieces', 'unit', 'units', 'dozen'];

        foreach ($weightUnits as $token) {
            if (str_contains($haystack, $token)) {
                return 'weight';
            }
        }

        foreach ($volumeUnits as $token) {
            if (str_contains($haystack, $token)) {
                return 'volume';
            }
        }

        foreach ($countUnits as $token) {
            if (str_contains($haystack, $token)) {
                return 'count';
            }
        }

        return 'other';
    }
}
