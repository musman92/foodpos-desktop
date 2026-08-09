<?php

namespace App\Console\Commands;

use App\Helpers\AppPermissions;
use App\Models\Company;
use App\Services\TenantRoleBootstrapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncRolesAndPermissionsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'permissions:sync
                            {--company= : Sync default tenant roles for one company (ID or slug) }';

    /**
     * @var string
     */
    protected $description = 'Sync permission definitions from AppPermissions and assign them to default tenant roles (Administrator = full tenant catalog; Manager, Cashier, Order Taker)';

    public function handle(TenantRoleBootstrapService $bootstrap): int
    {
        $bootstrap->syncGlobalPermissions();
        $count = count(AppPermissions::all());
        $this->info("Synced {$count} global permission definitions.");

        $companyOpt = $this->option('company');
        if ($companyOpt !== null && $companyOpt !== '') {
            $company = is_numeric($companyOpt)
                ? Company::query()->find($companyOpt)
                : Company::query()->where('slug', $companyOpt)->first();

            if (! $company) {
                $this->error("Company not found: {$companyOpt}");

                return self::FAILURE;
            }

            $companies = collect([$company]);
        } else {
            $companies = Company::query()->orderBy('id')->get();
        }

        if ($companies->isEmpty()) {
            $this->warn('No companies to update.');
            $this->clearApplicationCache();

            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $bootstrap->syncDefaultRolesForCompany($company);
            $this->line(" <info>✓</info> Company #{$company->id} ({$company->slug}): default roles and permission assignments updated.");
        }

        $n = $companies->count();
        $this->info('Updated default roles for '.$n.' compan'.($n === 1 ? 'y' : 'ies').' (Administrator receives all tenant-scoped permissions).');

        $this->clearApplicationCache();

        return self::SUCCESS;
    }

    protected function clearApplicationCache(): void
    {
        Artisan::call('cache:clear');
        $this->line(' <info>✓</info> Application cache cleared.');
    }
}
