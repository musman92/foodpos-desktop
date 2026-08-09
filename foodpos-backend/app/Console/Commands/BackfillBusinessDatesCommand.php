<?php

namespace App\Console\Commands;

use App\Services\BusinessDateBackfillService;
use Illuminate\Console\Command;

class BackfillBusinessDatesCommand extends Command
{
    protected $signature = 'business-date:backfill
                            {--company= : Limit to a company ID}
                            {--dry-run : Count rows that would be updated without writing}';

    protected $description = 'Backfill business_date on transactional tables from shift_date, shift windows, then created_at';

    public function handle(BusinessDateBackfillService $backfill): int
    {
        $companyId = $this->option('company') !== null
            ? (int) $this->option('company')
            : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — simulates backfill inside a transaction, then rolls back (no lasting changes).');
        }

        $summary = $backfill->backfill($companyId, $dryRun);

        $this->table(
            ['Table', 'From shift', 'From window', 'From calendar', 'Still null'],
            collect($summary)->map(fn (array $row, string $table) => [
                $table,
                $row['shift'],
                $row['window'],
                $row['calendar'],
                $row['remaining'],
            ])->values()->all()
        );

        $this->line('Tiers run in order: shift → window → calendar. Counts are sequential (no double-counting).');
        $this->line('After a real backfill, "Still null" should be 0 (calendar fills anything left).');

        $this->info($dryRun ? 'Dry run complete (rolled back).' : 'Backfill complete.');

        return self::SUCCESS;
    }
}
