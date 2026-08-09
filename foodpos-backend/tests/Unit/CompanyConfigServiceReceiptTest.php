<?php

namespace Tests\Unit;

use App\Services\CompanyConfigService;
use Tests\TestCase;

class CompanyConfigServiceReceiptTest extends TestCase
{
    public function test_get_default_config_includes_receipt_layout_from_settings(): void
    {
        $defaults = CompanyConfigService::getDefaultConfig();

        $this->assertArrayHasKey('receipt_layout', $defaults);
        $this->assertSame(14, $defaults['receipt_layout']['font_size_px']);
        $this->assertSame(80, $defaults['receipt_layout']['paper_width_mm']);
        $this->assertArrayHasKey('sections', $defaults['receipt_layout']);
        $this->assertTrue($defaults['receipt_layout']['sections']['thank_you']);
    }

    public function test_receipt_layout_is_derived_from_font_and_paper_settings(): void
    {
        $layout = CompanyConfigService::receiptLayoutSettings([
            'receipt_font_size' => 18,
            'receipt_paper_width_mm' => 58,
        ]);

        $this->assertSame(18, $layout['font_size_px']);
        $this->assertSame(58, $layout['paper_width_mm']);
        $this->assertSame(2, $layout['pad_left_mm']);
        $this->assertGreaterThanOrEqual(6, $layout['pad_right_mm']);
        $this->assertGreaterThanOrEqual(2, $layout['amount_pad_right_mm']);
    }

    public function test_receipt_font_size_is_clamped(): void
    {
        $this->assertSame(14, CompanyConfigService::normalizeReceiptFontSize(null));
        $this->assertSame(10, CompanyConfigService::normalizeReceiptFontSize(8));
        $this->assertSame(20, CompanyConfigService::normalizeReceiptFontSize(24));
        $this->assertSame(16, CompanyConfigService::normalizeReceiptFontSize(16));
    }

    public function test_receipt_layout_keeps_safe_right_margin_at_default_font(): void
    {
        $layout = CompanyConfigService::receiptLayoutSettings([
            'receipt_font_size' => 14,
            'receipt_paper_width_mm' => 80,
        ]);

        // Thermal print heads clip the right edge if pad is too thin (reported as Rs300 → Rs30).
        $this->assertGreaterThanOrEqual(5, $layout['pad_right_mm']);
        $this->assertGreaterThanOrEqual(2, $layout['amount_pad_right_mm']);
    }

    public function test_receipt_layout_increases_right_margin_for_larger_fonts(): void
    {
        $base = CompanyConfigService::receiptLayoutSettings([
            'receipt_font_size' => 14,
            'receipt_paper_width_mm' => 80,
        ]);
        $large = CompanyConfigService::receiptLayoutSettings([
            'receipt_font_size' => 18,
            'receipt_paper_width_mm' => 80,
        ]);

        $this->assertGreaterThan($base['pad_right_mm'], $large['pad_right_mm']);
        $this->assertGreaterThanOrEqual($base['col_price_pct'], $large['col_price_pct']);
    }

    public function test_receipt_layout_58mm_has_more_padding_than_80mm_at_same_font(): void
    {
        $w80 = CompanyConfigService::receiptLayoutSettings([
            'receipt_font_size' => 16,
            'receipt_paper_width_mm' => 80,
        ]);
        $w58 = CompanyConfigService::receiptLayoutSettings([
            'receipt_font_size' => 16,
            'receipt_paper_width_mm' => 58,
        ]);

        $this->assertGreaterThan($w80['pad_right_mm'], $w58['pad_right_mm']);
    }

    public function test_receipt_paper_width_only_allows_58_or_80(): void
    {
        $this->assertSame(80, CompanyConfigService::normalizeReceiptPaperWidth(null));
        $this->assertSame(58, CompanyConfigService::normalizeReceiptPaperWidth(58));
        $this->assertSame(80, CompanyConfigService::normalizeReceiptPaperWidth(72));
    }

    public function test_get_default_config_includes_pos_layout_settings(): void
    {
        $defaults = CompanyConfigService::getDefaultConfig();

        $this->assertArrayHasKey('pos_layout_config', $defaults);
        $this->assertFalse($defaults['show_pos_auto_bill_toggle']);
        $this->assertSame('classic', $defaults['pos_layout_config']['layout']);
        $this->assertSame('comfortable', $defaults['pos_layout_config']['product_density']);
        $this->assertSame('labeled', $defaults['pos_layout_config']['order_context_style']);
        $this->assertFalse($defaults['pos_layout_config']['uses_compact_order_context']);
        $this->assertFalse($defaults['pos_layout_config']['uses_sidebar_checkout']);
        $this->assertSame('normal', $defaults['pos_layout_config']['category_size']);
        $this->assertSame('strip', $defaults['pos_layout_config']['category_layout']);
        $this->assertFalse($defaults['pos_layout_config']['uses_category_grid']);
        $this->assertArrayHasKey('category_bar', $defaults['pos_layout_config']);
        $this->assertArrayHasKey('main_shell_grid', $defaults['pos_layout_config']);
        $this->assertCount(4, $defaults['pos_layout_config']['fulfillment_actions']);
    }

    public function test_pos_layout_config_reflects_compact_density(): void
    {
        $layout = CompanyConfigService::posLayoutSettings([
            'pos_layout' => 'classic',
            'pos_product_density' => 'compact',
        ]);

        $this->assertSame('compact', $layout['product_density']);
        $this->assertStringContainsString('xl:grid-cols-6', $layout['product_grid']['grid']);
    }

    public function test_pos_layout_config_reflects_compact_category_bar(): void
    {
        $layout = CompanyConfigService::posLayoutSettings([
            'pos_layout' => 'classic',
            'pos_category_size' => 'compact',
            'pos_category_layout' => 'grid',
        ]);

        $this->assertSame('compact', $layout['category_size']);
        $this->assertSame('grid', $layout['category_layout']);
        $this->assertTrue($layout['uses_category_grid']);
        $this->assertStringContainsString('pos-category-grid', $layout['category_bar']['container']);
        $this->assertStringContainsString('flex-wrap', $layout['category_bar']['container']);
        $this->assertStringContainsString('text-[11px]', $layout['category_bar']['button']);
    }

    public function test_pos_layout_settings_helper_recomputes_missing_shell_keys_from_stale_session(): void
    {
        $staleSession = [
            'pos_layout' => 'sidebar_actions',
            'pos_product_density' => 'comfortable',
            'pos_layout_config' => [
                'layout' => 'sidebar_actions',
                'product_density' => 'comfortable',
                'uses_sidebar_checkout' => true,
                'product_grid' => [],
                'fulfillment_actions' => [],
            ],
        ];

        $layout = pos_layout_settings($staleSession);

        $this->assertArrayHasKey('main_shell_grid', $layout);
        $this->assertArrayHasKey('cart_column', $layout);
        $this->assertArrayHasKey('browse_column', $layout);
        $this->assertStringContainsString('minmax(0,min(26rem,100%))', $layout['main_shell_grid']);
    }
}
