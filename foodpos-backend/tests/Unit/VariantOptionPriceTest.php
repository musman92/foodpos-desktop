<?php

namespace Tests\Unit;

use App\Models\Variant;
use PHPUnit\Framework\TestCase;

class VariantOptionPriceTest extends TestCase
{
    public function test_resolve_options_stores_default_price(): void
    {
        $resolved = Variant::resolveOptions([
            ['name' => 'Small', 'price' => '500'],
            ['name' => 'Large', 'price' => ''],
        ]);

        $this->assertNotNull($resolved);
        $this->assertSame('Small', $resolved[0]['name']);
        $this->assertSame(500.0, $resolved[0]['price']);
        $this->assertSame('Large', $resolved[1]['name']);
        $this->assertNull($resolved[1]['price']);
    }

    public function test_default_price_for_option_reads_from_options_json(): void
    {
        $variant = new Variant([
            'options' => [
                ['name' => 'Medium', 'code' => 'O02', 'price' => 800],
            ],
        ]);

        $this->assertSame(800.0, $variant->defaultPriceForOption('Medium'));
        $this->assertNull($variant->defaultPriceForOption('Small'));
    }
}
