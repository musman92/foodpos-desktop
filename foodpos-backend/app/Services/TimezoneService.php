<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TimezoneService
{
    /** @var array<string, string> */
    private const ALIASES = [
        'UTC+5' => 'Asia/Karachi',
        'UTC+05:00' => 'Asia/Karachi',
        'UTC+5:00' => 'Asia/Karachi',
        'GMT+5' => 'Asia/Karachi',
        'GMT+05:00' => 'Asia/Karachi',
        'PKT' => 'Asia/Karachi',
    ];

    public function normalize(string $timezone): string
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            return 'UTC';
        }

        if (isset(self::ALIASES[$timezone])) {
            return self::ALIASES[$timezone];
        }

        try {
            new \DateTimeZone($timezone);

            return $timezone;
        } catch (\Exception) {
            return 'UTC';
        }
    }

    public function resolveForCompany(Company|int|null $company = null): string
    {
        if ($company === null) {
            return $this->normalize((string) (get_company_config()['timezone'] ?? config('app.timezone', 'UTC')));
        }

        if (is_int($company)) {
            $company = Company::query()->find($company);
        }

        return $this->normalize((string) ($company?->timezone ?? config('app.timezone', 'UTC')));
    }

    public function resolveForBranch(Branch|int|null $branch = null): string
    {
        if ($branch === null) {
            $user = auth()->user();
            if ($user?->branch_id) {
                return $this->resolveForBranch((int) $user->branch_id);
            }

            return $this->resolveForCompany($user?->company_id);
        }

        if (is_int($branch)) {
            $branch = Branch::withoutGlobalScopes(['tenant', 'branch'])
                ->with('company:id,timezone')
                ->find($branch);
        }

        $branchTz = $this->normalize((string) ($branch?->timezone ?? ''));

        if ($branchTz !== '' && strtoupper($branchTz) !== 'UTC') {
            return $branchTz;
        }

        if ($branch instanceof Branch && $branch->relationLoaded('company')) {
            return $this->resolveForCompany($branch->company);
        }

        return $this->resolveForCompany($branch?->company_id);
    }

    public function now(Branch|int|null $branch = null): Carbon
    {
        return Carbon::now($this->resolveForBranch($branch));
    }

    public function today(Branch|int|null $branch = null): string
    {
        return $this->now($branch)->toDateString();
    }

    /**
     * Local business calendar date (Y-m-d) for a branch — single source of truth for "today".
     */
    public function businessDate(Branch|int|null $branch = null): string
    {
        return $this->today($branch);
    }

    /**
     * Compact date key (Ymd) for reference numbers on the branch business day.
     */
    public function businessDateKey(Branch|int|null $branch = null): string
    {
        return $this->now($branch)->format('Ymd');
    }

    /**
     * @return array{0: string, 1: string} [Y-m-d, Ymd]
     */
    public function businessDateParts(Branch|int|null $branch = null): array
    {
        $now = $this->now($branch);

        return [$now->toDateString(), $now->format('Ymd')];
    }

    public function toLocal(Carbon|string $datetime, Branch|int|null $branch = null): Carbon
    {
        if ($datetime instanceof Carbon) {
            // Use the stored instant; Eloquent may already label it in app timezone.
            $carbon = Carbon::createFromTimestamp($datetime->getTimestamp(), 'UTC');
        } else {
            $carbon = Carbon::parse($datetime, 'UTC');
        }

        return $carbon->timezone($this->resolveForBranch($branch));
    }

    /**
     * @return array{0: Carbon, 1: Carbon} UTC start/end for one local calendar day
     */
    public function localDateToUtcRange(string $date, Branch|int|null $branch = null): array
    {
        $tz = $this->resolveForBranch($branch);
        $start = Carbon::parse($date, $tz)->startOfDay()->utc();
        $end = Carbon::parse($date, $tz)->endOfDay()->utc();

        return [$start, $end];
    }

    public function localDateStartUtc(string $date, Branch|int|null $branch = null): Carbon
    {
        return $this->localDateToUtcRange($date, $branch)[0];
    }

    public function localDateEndUtc(string $date, Branch|int|null $branch = null): Carbon
    {
        return $this->localDateToUtcRange($date, $branch)[1];
    }

    /**
     * @return array{0: Carbon, 1: Carbon} UTC start/end for an inclusive local date range
     */
    public function localRangeToUtcRange(string $from, string $to, Branch|int|null $branch = null): array
    {
        $tz = $this->resolveForBranch($branch);
        $start = Carbon::parse($from, $tz)->startOfDay()->utc();
        $end = Carbon::parse($to, $tz)->endOfDay()->utc();

        if ($end->lt($start)) {
            [$start, $end] = [
                Carbon::parse($to, $tz)->startOfDay()->utc(),
                Carbon::parse($from, $tz)->endOfDay()->utc(),
            ];
        }

        return [$start, $end];
    }

    public function applyTimestampRange(
        Builder $query,
        string $column,
        string $from,
        string $to,
        Branch|int|null $branch = null
    ): Builder {
        [$start, $end] = $this->localRangeToUtcRange($from, $to, $branch);

        return $query->whereBetween($column, [$start, $end]);
    }

    /**
     * Prefer business_date (shift/business day). Rows still missing business_date
     * fall back to created_at within the branch-local calendar window.
     */
    public function applyBusinessDateRange(
        Builder $query,
        string $from,
        string $to,
        Branch|int|null $branch = null,
        string $timestampColumn = 'created_at'
    ): Builder {
        [$start, $end] = $this->localRangeToUtcRange($from, $to, $branch);

        return $query->where(function (Builder $outer) use ($from, $to, $start, $end, $timestampColumn) {
            $outer->where(function (Builder $dated) use ($from, $to) {
                $dated->whereDate('business_date', '>=', $from)
                    ->whereDate('business_date', '<=', $to);
            })->orWhere(function (Builder $legacy) use ($start, $end, $timestampColumn) {
                $legacy->whereNull('business_date')
                    ->whereBetween($timestampColumn, [$start, $end]);
            });
        });
    }

    public function applyTimestampDayRange(
        Builder $query,
        string $column,
        string $date,
        Branch|int|null $branch = null
    ): Builder {
        [$start, $end] = $this->localDateToUtcRange($date, $branch);

        return $query->whereBetween($column, [$start, $end]);
    }

    /**
     * Match rows whose UTC timestamp falls on a local calendar date (branch/company TZ).
     */
    public function applyLocalDateColumn(
        Builder $query,
        string $column,
        string $date,
        Branch|int|null $branch = null
    ): Builder {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            return $query->whereDate($column, $date);
        }

        $offset = $this->mysqlOffset($branch);

        return $query->whereRaw(
            "DATE(CONVERT_TZ({$column}, '+00:00', ?)) = ?",
            [$offset, $date]
        );
    }

    public function formatHistoryTimestamp(Carbon|string|null $datetime, Branch|int|null $branch = null): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }

        $timeFormat = (get_company_config()['time_format'] ?? '12') === '12' ? 'g:i A' : 'H:i';

        return $this->toLocal($datetime, $branch)->format('M j, '.$timeFormat);
    }

    public function mysqlOffset(Branch|int|null $branch = null): string
    {
        return $this->now($branch)->format('P');
    }

    public function localDateSql(string $column, Branch|int|null $branch = null): string
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            return "DATE({$column})";
        }

        $offset = $this->mysqlOffset($branch);

        return "DATE(CONVERT_TZ({$column}, '+00:00', '{$offset}'))";
    }

    /**
     * @return array<int, string>
     */
    public function branchTimezonesMap(iterable $branches): array
    {
        $map = [];
        foreach ($branches as $branch) {
            $map[$branch->id] = $this->resolveForBranch($branch);
        }

        return $map;
    }

    public function applyRuntimeTimezone(Branch|int|null $branch = null): string
    {
        $tz = $this->resolveForBranch($branch);
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);

        return $tz;
    }
}
