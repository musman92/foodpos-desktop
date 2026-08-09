<?php

namespace App\Services;

use App\Models\Company;
use App\Support\ListingPerPage;
use App\Support\PosLayout;
use App\Support\ReceiptSections;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CompanyConfigService
{
    /**
     * Get the current company's configuration.
     */
    public static function getConfig(?Company $company = null): array
    {
        if (!$company) {
            $user = Auth::user();
            $company = $user->company ?? Company::first();
        }

        if (!$company) {
            return self::getDefaultConfig();
        }

        $settings = $company->settings ?? [];

        $config = [
            'currency' => $company->currency ?? 'USD',
            'currency_position' => $settings['currency_position'] ?? 'left',
            'decimal_points' => $settings['decimal_points'] ?? 2,
            'timezone' => $company->timezone ?? 'America/New_York',
            'time_format' => $settings['time_format'] ?? '12',
            'date_format' => $settings['date_format'] ?? 'Y-m-d',
            'allow_pos_credit_sales' => (bool) ($settings['allow_pos_credit_sales'] ?? false),
            'direct_pos_print' => (bool) ($settings['direct_pos_print'] ?? false),
            'show_pos_auto_bill_toggle' => (bool) ($settings['show_pos_auto_bill_toggle'] ?? false),
            'strict_direct_pay_rate' => (bool) ($settings['strict_direct_pay_rate'] ?? false),
            'activity_logging_enabled' => (bool) ($settings['activity_logging_enabled'] ?? false),
            'week_starts_on' => $settings['week_starts_on'] ?? 'monday',
            'addon_kitchen_tracking' => app(CompanyAddonService::class)->kitchenTrackingEnabled($company),
            'logo' => $company->logo ? Storage::url($company->logo) : null,
            'favicon' => $company->favicon ? Storage::url($company->favicon) : null,
            'receipt_font_size' => self::normalizeReceiptFontSize($settings['receipt_font_size'] ?? null),
            'receipt_paper_width_mm' => self::normalizeReceiptPaperWidth($settings['receipt_paper_width_mm'] ?? null),
            'receipt_sections' => ReceiptSections::normalize($settings['receipt_sections'] ?? null),
            'pos_layout' => PosLayout::normalizeLayout($settings['pos_layout'] ?? null),
            'pos_product_density' => PosLayout::normalizeProductDensity($settings['pos_product_density'] ?? null),
            'pos_order_context_style' => PosLayout::normalizeOrderContextStyle($settings['pos_order_context_style'] ?? null),
            'pos_category_size' => PosLayout::normalizeCategorySize($settings['pos_category_size'] ?? null),
            'pos_category_layout' => PosLayout::normalizeCategoryLayout($settings['pos_category_layout'] ?? null),
            'listing_per_page' => ListingPerPage::normalize($settings['listing_per_page'] ?? ListingPerPage::DEFAULT),
        ];

        $config['receipt_layout'] = self::receiptLayoutSettings($config);
        $config['pos_layout_config'] = self::posLayoutSettings($config);

        return $config;
    }

    /**
     * Get default configuration.
     */
    public static function getDefaultConfig(): array
    {
        $config = [
            'currency' => 'USD',
            'currency_position' => 'left',
            'decimal_points' => 2,
            'timezone' => 'America/New_York',
            'time_format' => '12',
            'date_format' => 'Y-m-d',
            'allow_pos_credit_sales' => false,
            'direct_pos_print' => false,
            'show_pos_auto_bill_toggle' => false,
            'strict_direct_pay_rate' => false,
            'activity_logging_enabled' => false,
            'week_starts_on' => 'monday',
            'addon_kitchen_tracking' => false,
            'logo' => null,
            'favicon' => null,
            'receipt_font_size' => 14,
            'receipt_paper_width_mm' => 80,
            'receipt_sections' => ReceiptSections::defaults(),
            'pos_layout' => PosLayout::LAYOUT_CLASSIC,
            'pos_product_density' => PosLayout::DENSITY_COMFORTABLE,
            'pos_order_context_style' => PosLayout::ORDER_CONTEXT_LABELED,
            'pos_category_size' => PosLayout::CATEGORY_SIZE_NORMAL,
            'pos_category_layout' => PosLayout::CATEGORY_LAYOUT_STRIP,
            'listing_per_page' => ListingPerPage::DEFAULT,
        ];

        $config['receipt_layout'] = self::receiptLayoutSettings($config);
        $config['pos_layout_config'] = self::posLayoutSettings($config);

        return $config;
    }

    /**
     * POS screen layout derived from company preferences.
     *
     * @return array{
     *     layout: string,
     *     product_density: string,
     *     uses_sidebar_checkout: bool,
     *     main_shell_grid: string,
     *     cart_column: string,
     *     browse_column: string,
     *     product_grid: array{grid: string, card: string, deal_card: string, image: string, title: string, price: string},
     *     fulfillment_actions: list<array{mode: string, label: string, short_label: string, icon: string, enabled_class: string}>
     * }
     */
    public static function posLayoutSettings(?array $config = null): array
    {
        $config ??= self::getDefaultConfig();
        $layout = PosLayout::normalizeLayout($config['pos_layout'] ?? null);
        $density = PosLayout::normalizeProductDensity($config['pos_product_density'] ?? null);
        $orderContextStyle = PosLayout::normalizeOrderContextStyle($config['pos_order_context_style'] ?? null);
        $categorySize = PosLayout::normalizeCategorySize($config['pos_category_size'] ?? null);
        $categoryLayout = PosLayout::normalizeCategoryLayout($config['pos_category_layout'] ?? null);

        return [
            'layout' => $layout,
            'product_density' => $density,
            'order_context_style' => $orderContextStyle,
            'category_size' => $categorySize,
            'category_layout' => $categoryLayout,
            'uses_category_grid' => PosLayout::usesCategoryGrid($categoryLayout),
            'category_bar' => PosLayout::categoryBarClasses($categoryLayout, $categorySize),
            'uses_compact_order_context' => PosLayout::usesCompactOrderContext($orderContextStyle),
            'uses_sidebar_checkout' => PosLayout::usesSidebarCheckout($layout),
            'main_shell_grid' => PosLayout::mainShellGridClasses($layout),
            'cart_column' => PosLayout::cartColumnClasses($layout),
            'browse_column' => PosLayout::browseColumnClasses($layout),
            'product_grid' => PosLayout::productGridClasses($density),
            'fulfillment_actions' => PosLayout::visibleFulfillmentActions(),
        ];
    }

    public static function normalizeReceiptFontSize(mixed $value): int
    {
        $size = (int) ($value ?? 14);

        return max(10, min(20, $size === 0 ? 14 : $size));
    }

    /**
     * Receipt print layout derived from font size and paper width (prevents edge clipping at larger fonts).
     *
     * @return array{
     *     font_size_px: int,
     *     paper_width_mm: int,
     *     pad_left_mm: int,
     *     pad_right_mm: int,
     *     amount_pad_right_mm: int,
     *     col_item_pct: int,
     *     col_qty_pct: int,
     *     col_price_pct: int,
     *     sections: array<string, bool>
     * }
     */
    public static function receiptLayoutSettings(?array $config = null): array
    {
        $config ??= self::getDefaultConfig();
        $font = self::normalizeReceiptFontSize($config['receipt_font_size'] ?? null);
        $paper = self::normalizeReceiptPaperWidth($config['receipt_paper_width_mm'] ?? null);

        // Thermal rolls claim 58/80mm, but the print head leaves an unprintable
        // strip on the right — too little pad clips currency amounts (Rs300 → Rs30).
        $padLeft = 2;
        $fontOverBase = max(0, $font - 14);
        $padRight = ($paper === 58 ? 6 : 5) + (int) floor($fontOverBase / 2);
        if ($font >= 18) {
            $padRight++;
        }
        if ($paper === 58 && $font >= 16) {
            $padRight++;
        }

        // Extra inset on amount cells beyond body pad (second safety margin).
        $amountPad = max(2, (int) ceil($padRight / 3));

        if ($font >= 16) {
            $itemCol = $paper === 58 ? 48 : 50;
            $qtyCol = 12;
        } elseif ($font >= 15) {
            $itemCol = $paper === 58 ? 50 : 52;
            $qtyCol = 13;
        } else {
            $itemCol = $paper === 58 ? 52 : 54;
            $qtyCol = 14;
        }

        $priceCol = 100 - $itemCol - $qtyCol;

        return [
            'font_size_px' => $font,
            'paper_width_mm' => $paper,
            'pad_left_mm' => $padLeft,
            'pad_right_mm' => $padRight,
            'amount_pad_right_mm' => $amountPad,
            'col_item_pct' => $itemCol,
            'col_qty_pct' => $qtyCol,
            'col_price_pct' => $priceCol,
            'sections' => ReceiptSections::normalize($config['receipt_sections'] ?? null),
        ];
    }

    public static function normalizeReceiptPaperWidth(mixed $value): int
    {
        $width = (int) ($value ?? 80);

        return in_array($width, [58, 80], true) ? $width : 80;
    }

    /**
     * Format currency according to company settings.
     */
    public static function formatCurrency(float $amount, ?Company $company = null): string
    {
        $config = self::getConfig($company);
        $currency = $config['currency'];
        $decimals = $config['decimal_points'];
        $position = $config['currency_position'];

        $formatted = number_format($amount, $decimals, '.', ',');

        $currencySymbol = self::getCurrencySymbol($currency);

        return $position === 'left' 
            ? "{$currencySymbol}{$formatted}"
            : "{$formatted} {$currencySymbol}";
    }

    /**
     * Get currency symbol.
     */
    public static function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'INR' => '₹',
            'AED' => 'د.إ',
            'SAR' => '﷼',
            'PKR' => '₨',
            'BHD' => '.د.ب',
            'KWD' => 'د.ك',
            'OMR' => '﷼',
            'QAR' => '﷼',
        ];

        return $symbols[$currency] ?? $currency;
    }

    /**
     * Format date according to company settings.
     */
    public static function formatDate($date, ?Company $company = null): string
    {
        $config = self::getConfig($company);
        $format = $config['date_format'];
        
        if (!$date instanceof \Carbon\Carbon) {
            $date = \Carbon\Carbon::parse($date);
        }

        return $date->format($format);
    }

    /**
     * Format time according to company settings.
     */
    public static function formatTime($time, ?Company $company = null): string
    {
        $config = self::getConfig($company);
        $format = $config['time_format'] === '12' ? 'g:i A' : 'H:i';
        
        if (!$time instanceof \Carbon\Carbon) {
            $time = \Carbon\Carbon::parse($time);
        }

        return $time->format($format);
    }

    /**
     * Refresh session caches used across the app (preferences + receipt branding).
     */
    public static function warmSessionCaches(?Company $company = null): void
    {
        if (! $company) {
            $user = Auth::user();
            $company = $user?->company;
        }

        if (! $company) {
            return;
        }

        $company->refresh();

        \Illuminate\Support\Facades\Session::put('company_config', self::getConfig($company));
        CompanyReceiptBrandingService::warmSession($company);
    }
}

