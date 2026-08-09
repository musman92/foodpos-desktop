<?php

namespace Tests\Unit;

use App\Support\PosLayout;
use Tests\TestCase;

class PosLayoutTest extends TestCase
{
    public function test_default_layout_is_classic(): void
    {
        $this->assertSame(PosLayout::LAYOUT_CLASSIC, PosLayout::normalizeLayout(null));
        $this->assertSame(PosLayout::LAYOUT_CLASSIC, PosLayout::normalizeLayout('invalid'));
    }

    public function test_sidebar_layout_is_available_and_normalizes(): void
    {
        $this->assertSame(PosLayout::LAYOUT_SIDEBAR, PosLayout::normalizeLayout(PosLayout::LAYOUT_SIDEBAR));
        $this->assertTrue(PosLayout::layoutPresets()[PosLayout::LAYOUT_SIDEBAR]['available']);
    }

    public function test_sidebar_layout_uses_single_row_grid_and_shrinkable_cart(): void
    {
        $this->assertStringContainsString('grid-rows-[minmax(0,1fr)]', PosLayout::mainShellGridClasses(PosLayout::LAYOUT_SIDEBAR));
        $this->assertStringContainsString('minmax(0,min(26rem,100%))', PosLayout::mainShellGridClasses(PosLayout::LAYOUT_SIDEBAR));
        $this->assertStringContainsString('min-w-0', PosLayout::cartColumnClasses(PosLayout::LAYOUT_SIDEBAR));
        $this->assertStringNotContainsString('min-w-[28rem]', PosLayout::cartColumnClasses(PosLayout::LAYOUT_SIDEBAR));
    }

    public function test_default_product_density_is_comfortable(): void
    {
        $this->assertSame(PosLayout::DENSITY_COMFORTABLE, PosLayout::normalizeProductDensity(null));
        $this->assertSame(PosLayout::DENSITY_COMFORTABLE, PosLayout::normalizeProductDensity('invalid'));
    }

    public function test_compact_density_returns_denser_grid_classes(): void
    {
        $comfortable = PosLayout::productGridClasses(PosLayout::DENSITY_COMFORTABLE);
        $compact = PosLayout::productGridClasses(PosLayout::DENSITY_COMPACT);

        $this->assertStringContainsString('xl:grid-cols-5', $comfortable['grid']);
        $this->assertStringContainsString('xl:grid-cols-6', $compact['grid']);
        $this->assertStringContainsString('overflow-hidden', $compact['card']);
        $this->assertArrayHasKey('price_badge', $compact);
        $this->assertArrayHasKey('deal_price_badge', $compact);
    }

    public function test_fulfillment_actions_include_all_pos_modes(): void
    {
        $modes = array_column(PosLayout::fulfillmentActions(), 'mode');

        $this->assertSame(
            ['save', 'kot', 'kot_bill', 'kot_bill_pay', 'print', 'checkout'],
            $modes
        );
    }

    public function test_visible_fulfillment_actions_hide_kot_bill_buttons_for_now(): void
    {
        $modes = array_column(PosLayout::visibleFulfillmentActions(), 'mode');

        $this->assertSame(['save', 'kot', 'print', 'checkout'], $modes);
        $this->assertNotContains('kot_bill', $modes);
        $this->assertNotContains('kot_bill_pay', $modes);
    }

    public function test_sidebar_checkout_only_when_sidebar_layout_active(): void
    {
        $this->assertFalse(PosLayout::usesSidebarCheckout(PosLayout::LAYOUT_CLASSIC));
        $this->assertTrue(PosLayout::usesSidebarCheckout(PosLayout::LAYOUT_SIDEBAR));
    }

    public function test_default_order_context_style_is_labeled(): void
    {
        $this->assertSame(PosLayout::ORDER_CONTEXT_LABELED, PosLayout::normalizeOrderContextStyle(null));
        $this->assertSame(PosLayout::ORDER_CONTEXT_LABELED, PosLayout::normalizeOrderContextStyle('invalid'));
        $this->assertFalse(PosLayout::usesCompactOrderContext(PosLayout::ORDER_CONTEXT_LABELED));
        $this->assertTrue(PosLayout::usesCompactOrderContext(PosLayout::ORDER_CONTEXT_COMPACT));
    }
}
