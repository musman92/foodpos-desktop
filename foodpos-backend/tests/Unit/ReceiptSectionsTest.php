<?php

namespace Tests\Unit;

use App\Support\ReceiptSections;
use Tests\TestCase;

class ReceiptSectionsTest extends TestCase
{
    public function test_defaults_are_all_enabled(): void
    {
        $defaults = ReceiptSections::defaults();

        foreach ($defaults as $enabled) {
            $this->assertTrue($enabled);
        }
    }

    public function test_normalize_merges_partial_input(): void
    {
        $normalized = ReceiptSections::normalize([
            'thank_you' => false,
        ]);

        $this->assertFalse($normalized['thank_you']);
        $this->assertTrue($normalized['logo']);
        $this->assertTrue($normalized['deal_items']);
    }

    public function test_deal_items_can_be_disabled(): void
    {
        $normalized = ReceiptSections::normalize([
            'deal_items' => false,
        ]);

        $this->assertFalse($normalized['deal_items']);
        $this->assertFalse(ReceiptSections::enabled($normalized, 'deal_items'));
    }

    public function test_should_hide_redundant_subtotal(): void
    {
        $sections = ReceiptSections::defaults();

        $this->assertFalse(ReceiptSections::shouldShowSubtotal($sections, [
            'subtotal' => 600,
            'total_amount' => 600,
            'discount_amount' => 0,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'tax_amount' => 0,
        ]));

        $this->assertTrue(ReceiptSections::shouldShowSubtotal($sections, [
            'subtotal' => 600,
            'total_amount' => 550,
            'discount_amount' => 50,
            'service_charge' => 0,
            'delivery_fee' => 0,
            'tax_amount' => 0,
        ]));
    }
}
