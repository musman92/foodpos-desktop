<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Services\TimezoneService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TimezoneServiceTest extends TestCase
{
    private function karachiBranch(): Branch
    {
        $branch = new Branch();
        $branch->timezone = 'Asia/Karachi';

        return $branch;
    }

    public function test_normalize_maps_common_aliases(): void
    {
        $service = new TimezoneService();

        $this->assertSame('Asia/Karachi', $service->normalize('UTC+5'));
        $this->assertSame('Asia/Karachi', $service->normalize('PKT'));
    }

    public function test_local_date_to_utc_range_for_utc_plus_five(): void
    {
        $service = new TimezoneService();

        [$start, $end] = $service->localDateToUtcRange('2026-06-16', $this->karachiBranch());

        $this->assertSame('2026-06-15 19:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-16 18:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_completed_at_near_midnight_stays_on_selected_local_day(): void
    {
        $service = new TimezoneService();
        [$start, $end] = $service->localDateToUtcRange('2026-06-16', $this->karachiBranch());

        $completedAt = Carbon::parse('2026-06-16 23:30:00', 'Asia/Karachi')->utc();

        $this->assertTrue($completedAt->between($start, $end));

        $nextDayEarly = Carbon::parse('2026-06-17 04:21:00', 'Asia/Karachi')->utc();
        $this->assertFalse($nextDayEarly->between($start, $end));
    }
}
