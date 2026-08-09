<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class FormatDateHelperTest extends TestCase
{
    public function test_calendar_date_strings_are_not_shifted_by_timezone(): void
    {
        Session::put('company_config', [
            'date_format' => 'd-m-Y',
            'time_format' => '12',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
        ]);

        $this->assertSame('16-07-2026', format_date('2026-07-16'));
        $this->assertSame('16-07-2026', format_date('2026-07-16', null));
    }

    public function test_datetime_strings_still_convert_to_local_timezone(): void
    {
        Session::put('company_config', [
            'date_format' => 'd-m-Y',
            'time_format' => '12',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
        ]);

        // 2026-07-16 00:00:00 UTC → 15-07-2026 20:00 in New York
        $this->assertSame('15-07-2026', format_date('2026-07-16 00:00:00'));
    }
}
