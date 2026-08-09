<?php

namespace App\Services;

use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessDateBackfillService
{
    /** @var list<string> */
    private const TABLES_WITH_SHIFT = [
        'orders',
        'transactions',
        'purchases',
        'money_source_fund_movements',
    ];

    /**
     * @return array<string, array{shift: int, window: int, calendar: int, remaining: int}>
     */
    public function backfill(?int $companyId = null, bool $dryRun = false): array
    {
        if (! $dryRun) {
            return $this->runBackfill($companyId);
        }

        // Simulate a real sequential backfill, then discard writes so counts are accurate.
        DB::beginTransaction();

        try {
            $summary = $this->runBackfill($companyId);
            DB::rollBack();

            return $summary;
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * @return array<string, array{shift: int, window: int, calendar: int, remaining: int}>
     */
    private function runBackfill(?int $companyId): array
    {
        $summary = [];

        foreach (self::TABLES_WITH_SHIFT as $table) {
            $summary[$table] = $this->backfillShiftLinkedTable($table, $companyId);
        }

        $summary['stock_movements'] = $this->backfillStockMovements($companyId);

        return $summary;
    }

    /**
     * @return array{shift: int, window: int, calendar: int, remaining: int}
     */
    private function backfillShiftLinkedTable(string $table, ?int $companyId): array
    {
        $shift = $this->updateFromShiftId($table, $companyId);
        $window = $this->updateFromShiftWindow($table, $companyId);
        $calendar = $this->updateFromCreatedAtCalendar($table, $companyId);
        $remaining = $this->countNullBusinessDates($table, $companyId);

        return compact('shift', 'window', 'calendar', 'remaining');
    }

    /**
     * @return array{shift: int, window: int, calendar: int, remaining: int}
     */
    private function backfillStockMovements(?int $companyId): array
    {
        $shift = $this->updateStockMovementsFromOrderShift($companyId);
        $window = $this->updateFromShiftWindow('stock_movements', $companyId);
        $calendar = $this->updateFromCreatedAtCalendar('stock_movements', $companyId);
        $remaining = $this->countNullBusinessDates('stock_movements', $companyId);

        return compact('shift', 'window', 'calendar', 'remaining');
    }

    private function updateFromShiftId(string $table, ?int $companyId): int
    {
        $rows = DB::table($table)
            ->whereNull("{$table}.business_date")
            ->whereNotNull("{$table}.shift_id")
            ->join('shifts', 'shifts.id', '=', "{$table}.shift_id")
            ->when($companyId && Schema::hasColumn($table, 'company_id'), function ($q) use ($table, $companyId) {
                $q->where("{$table}.company_id", $companyId);
            })
            ->when($companyId && ! Schema::hasColumn($table, 'company_id'), function ($q) use ($table, $companyId) {
                $q->join('branches as bd_branches', 'bd_branches.id', '=', "{$table}.branch_id")
                    ->where('bd_branches.company_id', $companyId);
            })
            ->select("{$table}.id", 'shifts.shift_date')
            ->get();

        return $this->applyDateByIds($table, $rows);
    }

    private function updateStockMovementsFromOrderShift(?int $companyId): int
    {
        $rows = DB::table('stock_movements')
            ->whereNull('stock_movements.business_date')
            ->where('stock_movements.reference_type', OrderItem::class)
            ->join('order_items', 'order_items.id', '=', 'stock_movements.reference_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('shifts', 'shifts.id', '=', 'orders.shift_id')
            ->whereNotNull('orders.shift_id')
            ->when($companyId, fn ($q) => $q->where('orders.company_id', $companyId))
            ->select('stock_movements.id', 'shifts.shift_date')
            ->get();

        return $this->applyDateByIds('stock_movements', $rows);
    }

    private function updateFromShiftWindow(string $table, ?int $companyId): int
    {
        $updated = 0;
        $hasCreatedBy = Schema::hasColumn($table, 'created_by');

        $shifts = DB::table('shifts')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('opened_at')
            ->orderBy('id')
            ->get(['id', 'branch_id', 'opened_by', 'opened_at', 'closed_at', 'shift_date']);

        foreach ($shifts as $shift) {
            $end = $shift->closed_at ?? now()->toDateTimeString();
            $date = substr((string) $shift->shift_date, 0, 10);

            $base = DB::table($table)
                ->whereNull('business_date')
                ->where('branch_id', $shift->branch_id)
                ->whereBetween('created_at', [$shift->opened_at, $end]);

            if ($companyId && Schema::hasColumn($table, 'company_id')) {
                $base->where('company_id', $companyId);
            }

            if ($hasCreatedBy) {
                $ids = (clone $base)->where('created_by', $shift->opened_by)->pluck('id');
                if ($ids->isNotEmpty()) {
                    $updated += $this->stampIds($table, $ids->all(), $date);

                    continue;
                }
            }

            $ids = $base->pluck('id');
            if ($ids->isEmpty()) {
                continue;
            }

            $updated += $this->stampIds($table, $ids->all(), $date);
        }

        return $updated;
    }

    private function updateFromCreatedAtCalendar(string $table, ?int $companyId): int
    {
        $query = DB::table($table)->whereNull('business_date');

        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif ($companyId) {
            $query->whereIn('branch_id', function ($sub) use ($companyId) {
                $sub->select('id')->from('branches')->where('company_id', $companyId);
            });
        }

        $expression = DB::getDriverName() === 'sqlite'
            ? 'date(created_at)'
            : 'DATE(created_at)';

        return $query->update([
            'business_date' => DB::raw($expression),
        ]);
    }

    private function countNullBusinessDates(string $table, ?int $companyId): int
    {
        $query = DB::table($table)->whereNull('business_date');

        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif ($companyId) {
            $query->whereIn('branch_id', function ($sub) use ($companyId) {
                $sub->select('id')->from('branches')->where('company_id', $companyId);
            });
        }

        return (int) $query->count();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object{id: mixed, shift_date: mixed}>  $rows
     */
    private function applyDateByIds(string $table, $rows): int
    {
        if ($rows->isEmpty()) {
            return 0;
        }

        $updated = 0;
        foreach ($rows->groupBy(fn ($row) => substr((string) $row->shift_date, 0, 10)) as $date => $group) {
            $updated += $this->stampIds($table, $group->pluck('id')->all(), $date);
        }

        return $updated;
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function stampIds(string $table, array $ids, string $date): int
    {
        if ($ids === []) {
            return 0;
        }

        $updated = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $updated += DB::table($table)
                ->whereIn('id', $chunk)
                ->whereNull('business_date')
                ->update(['business_date' => $date]);
        }

        return $updated;
    }
}
