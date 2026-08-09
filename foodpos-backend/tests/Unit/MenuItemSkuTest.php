<?php

namespace Tests\Unit;

use App\Models\MenuItem;
use PHPUnit\Framework\TestCase;

class MenuItemSkuTest extends TestCase
{
    public function test_resolve_sku_uses_requested_value_when_present(): void
    {
        $this->assertSame('CUSTOM01', MenuItem::resolveSku(1, 'CUSTOM01'));
    }

    public function test_resolve_sku_trims_whitespace(): void
    {
        $this->assertSame('MI01', MenuItem::resolveSku(1, '  MI01  '));
    }
}
