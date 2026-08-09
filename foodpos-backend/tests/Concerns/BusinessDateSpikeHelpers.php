<?php

namespace Tests\Concerns;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Shared fixtures for business_date overnight spikes (test-only columns).
 */
trait BusinessDateSpikeHelpers
{
    protected const BUSINESS_DAY = '2026-07-16';

    protected const NEXT_CALENDAR_DAY = '2026-07-17';

    protected function eveningAt(): Carbon
    {
        return Carbon::parse(self::BUSINESS_DAY.' 18:00:00', 'Asia/Karachi')->utc();
    }

    protected function overnightAt(): Carbon
    {
        return Carbon::parse(self::NEXT_CALENDAR_DAY.' 01:30:00', 'Asia/Karachi')->utc();
    }

    protected function createOvernightShift(): Shift
    {
        return Shift::create([
            'company_id' => $this->tenantCompany->id,
            'branch_id' => $this->tenantBranch->id,
            'opened_by' => $this->companyAdmin->id,
            'closed_by' => $this->companyAdmin->id,
            'shift_date' => self::BUSINESS_DAY,
            'opened_at' => Carbon::parse(self::BUSINESS_DAY.' 16:00:00', 'Asia/Karachi')->utc(),
            'closed_at' => Carbon::parse(self::NEXT_CALENDAR_DAY.' 03:00:00', 'Asia/Karachi')->utc(),
            'status' => 'closed',
            'expected_cash' => 0,
            'cash_difference' => 0,
        ]);
    }

    protected function pinCreatedAt(Model $model, Carbon $at): Model
    {
        $model->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->saveQuietly();

        return $model->fresh();
    }

    protected function stampBusinessDate(Model $model, string $businessDate = self::BUSINESS_DAY): Model
    {
        $model->forceFill(['business_date' => $businessDate])->saveQuietly();

        return $model->fresh();
    }

    /**
     * Sum a numeric column for rows whose created_at falls on a branch-local calendar day.
     */
    protected function sumByCreatedAtCalendarDay(string $table, string $amountColumn, string $localDate): float
    {
        [$start, $end] = tz()->localRangeToUtcRange($localDate, $localDate, $this->tenantBranch->id);

        return (float) DB::table($table)
            ->where('branch_id', $this->tenantBranch->id)
            ->whereBetween('created_at', [$start, $end])
            ->sum($amountColumn);
    }

    /**
     * Sum a numeric column for rows stamped with business_date in range.
     */
    protected function sumByBusinessDate(string $table, string $amountColumn, string $from, string $to): float
    {
        return (float) DB::table($table)
            ->where('branch_id', $this->tenantBranch->id)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->sum($amountColumn);
    }

    /**
     * Classic spike: calendar misses overnight; business_date includes evening + overnight.
     */
    protected function assertCalendarMissesOvernightButBusinessDateIncludesBoth(
        string $table,
        string $amountColumn,
        float $eveningAmount,
        float $overnightAmount,
        Model $eveningRow,
        Model $overnightRow
    ): void {
        $total = $eveningAmount + $overnightAmount;

        $this->assertSame(
            $eveningAmount,
            $this->sumByCreatedAtCalendarDay($table, $amountColumn, self::BUSINESS_DAY),
            "[{$table}] calendar created_at filter for business day should include evening only."
        );

        $this->stampBusinessDate($eveningRow);
        $this->stampBusinessDate($overnightRow);

        $this->assertSame(
            $eveningAmount,
            $this->sumByCreatedAtCalendarDay($table, $amountColumn, self::BUSINESS_DAY),
            "[{$table}] stamping business_date must not change created_at calendar filtering."
        );

        $this->assertSame(
            $total,
            $this->sumByBusinessDate($table, $amountColumn, self::BUSINESS_DAY, self::BUSINESS_DAY),
            "[{$table}] business_date filter for business day should include evening + overnight."
        );
    }
}
