<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\DetachGlobalCatalogService;
use Illuminate\Console\Command;

class DetachGlobalCatalogCommand extends Command
{
    protected $signature = 'catalog:detach-globals
                            {--company= : Process one company (ID or slug)}
                            {--dry-run : Report changes without writing to the database}
                            {--purge-globals : Delete global catalog rows with no remaining references after detach}';

    protected $description = 'Clone or reuse tenant-owned catalog rows and re-point company data away from global categories and ingredients';

    public function handle(DetachGlobalCatalogService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $purgeGlobals = (bool) $this->option('purge-globals');
        $companyId = $this->resolveCompanyId($this->option('company'));

        if ($companyId === false) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry run — no database changes will be made.');
        }

        if ($purgeGlobals && $dryRun) {
            $this->warn('--purge-globals is ignored during dry run.');
        }

        $stats = $service->detach($companyId, $dryRun, $purgeGlobals && ! $dryRun);

        $this->info("Processed {$stats['companies']} company(ies).");

        $this->newLine();
        $this->info('Ingredient categories:');
        $this->line("  Reused existing: {$stats['ingredient_categories']['reused']}");
        $this->line("  Cloned: {$stats['ingredient_categories']['cloned']}");
        $this->line("  Ingredient category links updated: {$stats['ingredient_categories']['repointed']}");

        $this->newLine();
        $this->info('Ingredients:');
        $this->line("  Reused existing: {$stats['ingredients']['reused']}");
        $this->line("  Cloned: {$stats['ingredients']['cloned']}");
        $this->line("  References updated: {$stats['ingredients']['repointed']}");
        $this->line("  Duplicate recipe lines dropped: {$stats['ingredients']['recipes_dropped']}");
        $this->line("  Branch stock rows merged: {$stats['ingredients']['stock_merged']}");

        $this->newLine();
        $this->info('Menu categories:');
        $this->line("  Reused existing: {$stats['menu_categories']['reused']}");
        $this->line("  Cloned: {$stats['menu_categories']['cloned']}");
        $this->line("  Menu item links updated: {$stats['menu_categories']['repointed']}");

        if ($purgeGlobals && ! $dryRun) {
            $this->newLine();
            $this->info('Purged unreferenced globals:');
            $this->line("  Ingredient categories: {$stats['purged']['ingredient_categories']}");
            $this->line("  Ingredients: {$stats['purged']['ingredients']}");
            $this->line("  Menu categories: {$stats['purged']['menu_categories']}");
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'Detach complete.');

        if (! $dryRun && ! $purgeGlobals) {
            $this->comment('Run again with --purge-globals after verifying all companies to remove leftover global rows.');
        }

        return self::SUCCESS;
    }

    private function resolveCompanyId(mixed $companyOpt): int|false|null
    {
        if ($companyOpt === null || $companyOpt === '') {
            return null;
        }

        $company = is_numeric($companyOpt)
            ? Company::query()->find($companyOpt)
            : Company::query()->where('slug', $companyOpt)->first();

        if (! $company) {
            $this->error("Company not found: {$companyOpt}");

            return false;
        }

        return (int) $company->id;
    }
}
