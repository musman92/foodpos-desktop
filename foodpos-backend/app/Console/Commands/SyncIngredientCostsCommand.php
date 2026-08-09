<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Services\IngredientCostService;
use Illuminate\Console\Command;

class SyncIngredientCostsCommand extends Command
{
    protected $signature = 'ingredients:sync-costs {--company= : Limit to a company ID}';

    protected $description = 'Recalculate ingredient costs from purchase stock and history';

    public function handle(IngredientCostService $ingredientCosts): int
    {
        $query = Ingredient::withoutGlobalScopes();

        if ($companyId = $this->option('company')) {
            $query->where('company_id', (int) $companyId);
        }

        $synced = 0;
        $skipped = 0;

        $query->orderBy('id')->chunk(100, function ($ingredients) use ($ingredientCosts, &$synced, &$skipped) {
            foreach ($ingredients as $ingredient) {
                if ($ingredientCosts->syncIngredient($ingredient)) {
                    $synced++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Synced {$synced} ingredient(s). Skipped {$skipped} with no purchase data.");

        return self::SUCCESS;
    }
}
