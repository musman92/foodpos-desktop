<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\IngredientUnit;

/**
 * Convert recipe quantities to ingredient consumption (stock) units.
 * Uses only the ingredient's purchase/consumption units and conversion_rate.
 */
class IngredientQuantity
{
    public const UNIT_CONSUMPTION = 'consumption';

    public const UNIT_PURCHASE = 'purchase';

    public const UNIT_UNKNOWN = 'unknown';

    /**
     * Whether a recipe line unit is the consumption or purchase unit for this ingredient.
     */
    public static function matchRecipeUnit(Ingredient $ingredient, ?string $unit): string
    {
        $ingredient->loadMissing([
            'consumptionUnit' => fn ($query) => $query->withoutGlobalScopes(),
            'purchaseUnit' => fn ($query) => $query->withoutGlobalScopes(),
        ]);

        $unit = $unit !== null ? IngredientImportReferences::normalizeUnitReference($unit) : '';

        if ($unit === '') {
            return self::UNIT_CONSUMPTION;
        }

        if (self::matchesIngredientUnit($unit, $ingredient->consumptionUnit, $ingredient->base_unit_id, $ingredient->company_id)) {
            return self::UNIT_CONSUMPTION;
        }

        if (self::matchesIngredientUnit($unit, $ingredient->purchaseUnit, null, $ingredient->company_id)) {
            return self::UNIT_PURCHASE;
        }

        return self::UNIT_UNKNOWN;
    }

    public static function isValidRecipeUnit(Ingredient $ingredient, ?string $unit): bool
    {
        return self::matchRecipeUnit($ingredient, $unit) !== self::UNIT_UNKNOWN;
    }

    /**
     * Convert a recipe-line quantity into consumption-unit quantity (branch stock unit).
     */
    public static function toConsumptionQuantity(Ingredient $ingredient, float $quantity, ?string $recipeUnit): ?float
    {
        return match (self::matchRecipeUnit($ingredient, $recipeUnit)) {
            self::UNIT_CONSUMPTION => $quantity,
            self::UNIT_PURCHASE => $quantity * max((float) ($ingredient->conversion_rate ?: 1), 0.0001),
            default => null,
        };
    }

    public static function conversionErrorMessage(Ingredient $ingredient, ?string $recipeUnit): string
    {
        $ingredient->loadMissing([
            'consumptionUnit' => fn ($query) => $query->withoutGlobalScopes(),
            'purchaseUnit' => fn ($query) => $query->withoutGlobalScopes(),
        ]);

        $consumption = $ingredient->consumptionUnit?->displayLabel()
            ?? $ingredient->unit_name
            ?? 'consumption unit';
        $purchase = $ingredient->purchaseUnit?->displayLabel() ?? 'purchase unit';
        $given = $recipeUnit !== null && trim($recipeUnit) !== '' ? trim($recipeUnit) : 'not set';

        return "Invalid unit for {$ingredient->name}. Recipe unit \"{$given}\" must be the consumption unit ({$consumption}) or purchase unit ({$purchase}). Stock is tracked in the consumption unit.";
    }

    /**
     * Canonical unit id to store on new recipe lines (consumption unit id).
     */
    public static function canonicalRecipeUnitId(Ingredient $ingredient): ?string
    {
        $ingredient->loadMissing([
            'consumptionUnit' => fn ($query) => $query->withoutGlobalScopes(),
        ]);

        if ($ingredient->consumption_unit_id) {
            return (string) $ingredient->consumption_unit_id;
        }

        return $ingredient->base_unit_id ?: null;
    }

    /**
     * Resolve import/API unit string to canonical consumption unit id when valid.
     */
    public static function resolveRecipeUnitId(Ingredient $ingredient, ?string $unit): ?string
    {
        if ($unit === null || trim($unit) === '') {
            return self::canonicalRecipeUnitId($ingredient);
        }

        if (! self::isValidRecipeUnit($ingredient, $unit)) {
            return null;
        }

        if (self::matchRecipeUnit($ingredient, $unit) === self::UNIT_CONSUMPTION) {
            return self::canonicalRecipeUnitId($ingredient);
        }

        $ingredient->loadMissing([
            'purchaseUnit' => fn ($query) => $query->withoutGlobalScopes(),
        ]);

        if ($ingredient->purchase_unit_id) {
            return (string) $ingredient->purchase_unit_id;
        }

        return $unit;
    }

    protected static function matchesIngredientUnit(string $needle, ?IngredientUnit $unit, ?string $legacyBaseUnitId, ?int $companyId = null): bool
    {
        $needle = IngredientImportReferences::normalizeUnitReference($needle);
        if ($needle === '') {
            return false;
        }

        if ($unit) {
            if ($companyId !== null && (int) $unit->company_id !== (int) $companyId) {
                return false;
            }
            if ((string) $unit->id === $needle) {
                return true;
            }

            if ($unit->code && IngredientImportReferences::codesReferToSame($unit->code, $needle)) {
                return true;
            }

            if (strcasecmp($unit->name, $needle) === 0) {
                return true;
            }

            if ($needle === $unit->baseUnitIdValue()) {
                return true;
            }

            if (self::abbreviationMatchesUnit($needle, $unit)) {
                return true;
            }
        }

        if ($legacyBaseUnitId) {
            if ($needle === $legacyBaseUnitId) {
                return true;
            }

            if (IngredientImportReferences::codesReferToSame((string) $legacyBaseUnitId, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match legacy spreadsheet abbreviations (g, kg, pcs) to ingredient unit names.
     */
    protected static function abbreviationMatchesUnit(string $needle, IngredientUnit $unit): bool
    {
        $needle = strtolower(trim($needle));
        $name = strtolower(trim($unit->name));

        if ($needle === '' || $name === '') {
            return false;
        }

        return match ($needle) {
            'g', 'gm', 'gram', 'grams' => ! str_contains($name, 'kilo')
                && (str_contains($name, 'gram') || $name === 'g'),
            'kg', 'kilo', 'kilogram', 'kilograms' => str_contains($name, 'kilo'),
            'ml', 'milliliter', 'millilitre', 'milliliters', 'millilitres' => str_contains($name, 'milli'),
            'l', 'ltr', 'liter', 'litre', 'liters', 'litres' => (str_contains($name, 'liter') || str_contains($name, 'litre'))
                && ! str_contains($name, 'milli'),
            'pcs', 'pc', 'piece', 'pieces' => str_contains($name, 'piece'),
            default => false,
        };
    }
}
