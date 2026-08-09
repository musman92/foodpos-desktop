<?php

namespace App\Console\Commands;

use App\Services\FloorStaffEmployeeBackfillService;
use Illuminate\Console\Command;

class BackfillFloorStaffEmployeesCommand extends Command
{
    protected $signature = 'hr:backfill-floor-employees
                            {--company= : Limit to a company ID}
                            {--dry-run : Preview actions without writing}';

    protected $description = 'Create employee profiles for existing waiter/rider/waiter_rider users and link them';

    public function handle(FloorStaffEmployeeBackfillService $backfill): int
    {
        $companyId = $this->option('company') !== null
            ? (int) $this->option('company')
            : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no employee profiles will be written.');
        }

        $summary = $backfill->backfill($companyId, $dryRun);

        if ($summary['rows'] === []) {
            $this->info('No waiter/rider users found'.($companyId ? " for company {$companyId}" : '').'.');

            return self::SUCCESS;
        }

        $this->table(
            ['User ID', 'Company', 'Name', 'Email', 'Type', 'Login', 'Action', 'Detail'],
            collect($summary['rows'])->map(fn (array $row) => [
                $row['user_id'],
                $row['company_id'],
                $row['name'],
                $row['email'],
                $row['type'],
                $row['can_login'],
                $row['action'],
                $row['detail'],
            ])->all()
        );

        $this->newLine();
        $this->line("Candidates: {$summary['candidates']}");
        $this->line(($dryRun ? 'Would create' : 'Created').": {$summary['created']}");
        $this->line(($dryRun ? 'Would restore' : 'Restored').": {$summary['restored']}");
        $this->line("Skipped (already linked): {$summary['skipped']}");
        $this->newLine();
        $this->line('Pay fields are set to safe defaults (monthly / rate from users.salary or 0). Review in HR → Employees.');
        $this->info($dryRun ? 'Dry run complete.' : 'Backfill complete.');

        return self::SUCCESS;
    }
}
