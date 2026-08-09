<?php

namespace App\Support;

use App\Models\Ingredient;

class TenantIngredientAccess
{
    public static function isUsableByCompany(Ingredient $ingredient, ?int $companyId): bool
    {
        return $companyId !== null && (int) $ingredient->company_id === (int) $companyId;
    }
}
