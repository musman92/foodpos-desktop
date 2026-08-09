<?php

namespace Tests\Unit;

use App\Models\KitchenKot;
use App\Services\PrintJobService;
use Tests\TestCase;

class PrintJobServiceTest extends TestCase
{
    public function test_signed_kitchen_kot_url_is_generated(): void
    {
        $kot = new KitchenKot();
        $kot->id = 42;

        $url = app(PrintJobService::class)->signedKitchenKotUrl($kot);

        $this->assertStringContainsString('/print/kitchen-kot/42', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_branch_desktop_key_rejects_invalid_prefix(): void
    {
        $this->assertNull(\App\Models\BranchDesktopKey::findByPlainKey('invalid_key'));
        $this->assertNull(\App\Models\BranchDesktopKey::findByPlainKey(''));
    }
}
