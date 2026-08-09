<?php

use App\Services\CompanyConfigService;
use App\Services\TimezoneService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;

if (! function_exists('tz')) {
    /**
     * Application timezone service (company/branch preference, UTC query bounds).
     */
    function tz(): TimezoneService
    {
        return app(TimezoneService::class);
    }
}

if (! function_exists('branch_timezone')) {
    function branch_timezone($branch = null): string
    {
        return tz()->resolveForBranch($branch);
    }
}

if (! function_exists('local_now')) {
    function local_now($branch = null): Carbon
    {
        return tz()->now($branch);
    }
}

if (! function_exists('local_today')) {
    function local_today($branch = null): string
    {
        return tz()->businessDate($branch);
    }
}

if (! function_exists('get_company_config')) {
    /**
     * Get company configuration from session or fetch it.
     */
    function get_company_config(): array
    {
        // Check if config is in session
        if (Session::has('company_config')) {
            return Session::get('company_config');
        }

        // Fetch from service and store in session
        $config = CompanyConfigService::getConfig();
        Session::put('company_config', $config);

        return $config;
    }
}

if (! function_exists('receipt_layout_settings')) {
    /**
     * Invoice/receipt CSS layout (margins + columns scale with font size and paper width).
     * Always derived from font/paper settings — do not reuse a stale receipt_layout from session.
     */
    function receipt_layout_settings(?array $config = null): array
    {
        $config = $config ?? get_company_config();

        return CompanyConfigService::receiptLayoutSettings($config);
    }
}

if (! function_exists('pos_layout_settings')) {
    /**
     * POS screen layout (preset, product density, shared fulfillment action definitions).
     * Always derived from company config — do not read stale pos_layout_config from session.
     */
    function pos_layout_settings(?array $config = null): array
    {
        $config = $config ?? get_company_config();

        return CompanyConfigService::posLayoutSettings($config);
    }
}

if (! function_exists('listing_per_page')) {
    /**
     * Default rows per page for tenant listing screens (company preference).
     */
    function listing_per_page(): int
    {
        $config = get_company_config();

        return \App\Support\ListingPerPage::normalize($config['listing_per_page'] ?? \App\Support\ListingPerPage::DEFAULT);
    }
}

if (! function_exists('format_currency')) {
    /**
     * Format currency according to company settings.
     */
    function format_currency(float $amount): string
    {
        $config = get_company_config();
        $currency = $config['currency'];
        $decimals = $config['decimal_points'];
        $position = $config['currency_position'];

        $formatted = number_format($amount, $decimals, '.', ',');

        $currencySymbol = get_currency_symbol($currency);

        return $position === 'left'
            ? "{$currencySymbol}{$formatted}"
            : "{$formatted} {$currencySymbol}";
    }
}

if (! function_exists('format_platform_currency')) {
    /**
     * Format currency for super-admin platform billing (not tenant-scoped).
     */
    function format_platform_currency(float $amount, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: config('platform_billing.currency', 'USD'));
        $symbol = get_currency_symbol($currency);
        $formatted = number_format($amount, 2, '.', ',');

        return "{$symbol}{$formatted}";
    }
}

if (! function_exists('format_amount')) {
    /**
     * Format a numeric amount without the currency symbol (uses company decimal settings).
     */
    function format_amount(float $amount): string
    {
        $decimals = get_company_config()['decimal_points'] ?? 2;

        return number_format($amount, $decimals, '.', ',');
    }
}

if (! function_exists('format_quantity')) {
    /**
     * Format quantity: whole numbers without decimals; fractional values keep needed decimals.
     */
    function format_quantity(float $quantity, int $maxDecimals = 2): string
    {
        if (abs($quantity - round($quantity)) < 0.00001) {
            return number_format($quantity, 0, '.', ',');
        }

        return rtrim(rtrim(number_format($quantity, $maxDecimals, '.', ','), '0'), '.');
    }
}

if (! function_exists('currency_symbol')) {
    /**
     * Currency symbol for the active company.
     */
    function currency_symbol(): string
    {
        return get_currency_symbol(get_company_config()['currency'] ?? 'USD');
    }
}

if (! function_exists('pdf_currency_symbol')) {
    /**
     * ASCII-safe currency symbol for DomPDF (DejaVu Sans lacks many Unicode currency glyphs).
     */
    function pdf_currency_symbol(?string $currency = null): string
    {
        $currency ??= get_company_config()['currency'] ?? 'USD';

        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'INR' => 'Rs',
            'AED' => 'AED',
            'SAR' => 'SAR',
            'PKR' => 'Rs',
            'BHD' => 'BHD',
            'KWD' => 'KWD',
            'OMR' => 'OMR',
            'QAR' => 'QAR',
        ];

        return $symbols[$currency] ?? $currency;
    }
}

if (! function_exists('format_currency_for_pdf')) {
    /**
     * Format currency for PDF export using DomPDF-safe symbols.
     */
    function format_currency_for_pdf(float $amount): string
    {
        $config = get_company_config();
        $currency = $config['currency'];
        $decimals = $config['decimal_points'];
        $position = $config['currency_position'];

        $formatted = number_format($amount, $decimals, '.', ',');
        $currencySymbol = pdf_currency_symbol($currency);

        return $position === 'left'
            ? "{$currencySymbol} {$formatted}"
            : "{$formatted} {$currencySymbol}";
    }
}

if (! function_exists('get_currency_symbol')) {
    /**
     * Get currency symbol.
     */
    function get_currency_symbol(string $currency): string
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
}

if (! function_exists('format_date')) {
    /**
     * Format date according to company settings in branch/company timezone.
     *
     * Plain Y-m-d values are calendar labels (report filters, periods) and must
     * not be reinterpreted as UTC midnights — that shifts the displayed day in
     * timezones west of UTC.
     */
    function format_date($date, $branch = null): string
    {
        $config = get_company_config();
        $format = $config['date_format'];

        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay()->format($format);
        }

        return tz()->toLocal($date, $branch)->format($format);
    }
}

if (! function_exists('format_datetime')) {
    /**
     * Format date and time according to company settings in branch/company timezone.
     */
    function format_datetime($datetime, $branch = null): string
    {
        $config = get_company_config();
        $dateFormat = $config['date_format'];
        $timeFormat = $config['time_format'] === '12' ? 'g:i A' : 'H:i';

        return tz()->toLocal($datetime, $branch)->format("{$dateFormat} {$timeFormat}");
    }
}

if (! function_exists('format_time')) {
    /**
     * Format time according to company settings in branch/company timezone.
     */
    function format_time($time, $branch = null): string
    {
        $config = get_company_config();
        $format = $config['time_format'] === '12' ? 'g:i A' : 'H:i';

        return tz()->toLocal($time, $branch)->format($format);
    }
}

if (! function_exists('get_logo')) {
    /**
     * Get company logo URL.
     */
    function get_logo(): ?string
    {
        $config = get_company_config();

        return $config['logo'] ?? null;
    }
}

if (! function_exists('get_favicon')) {
    /**
     * Get company favicon URL.
     */
    function get_favicon(): ?string
    {
        $config = get_company_config();

        return $config['favicon'] ?? null;
    }
}

if (! function_exists('get_company_name')) {
    /**
     * Get company name.
     */
    function get_company_name(): string
    {
        if (auth()->check() && auth()->user()->company) {
            return auth()->user()->company->name;
        }

        return 'Food POS';
    }
}

if (! function_exists('get_company_receipt_branding')) {
    /**
     * Receipt header branding (company + branches) cached in session.
     */
    function get_company_receipt_branding(): array
    {
        return \App\Services\CompanyReceiptBrandingService::get();
    }
}

if (! function_exists('refresh_company_receipt_branding')) {
    /**
     * Rebuild receipt branding cache (after settings or branch updates).
     */
    function refresh_company_receipt_branding(?\App\Models\Company $company = null): array
    {
        return \App\Services\CompanyReceiptBrandingService::warmSession($company);
    }
}

if (! function_exists('clear_company_config')) {
    /**
     * Clear company session caches (preferences + receipt branding).
     */
    function clear_company_config(): void
    {
        Session::forget('company_config');
        \App\Services\CompanyReceiptBrandingService::forget();
    }
}

if (! function_exists('platform_media_categories')) {
    /**
     * Canonical platform media library categories (edit app/Helpers/PlatformMediaCatalog.php).
     *
     * @return list<string>
     */
    function platform_media_categories(): array
    {
        return \App\Helpers\PlatformMediaCatalog::categories();
    }
}

if (! function_exists('app_permissions_grouped')) {
    /**
     * Permissions grouped for UI (module title => rows with name, action, label, module_key).
     *
     * @return array<string, list<array{name: string, action: string, label: string, module_key: string}>>
     */
    function app_permissions_grouped(): array
    {
        return \App\Helpers\AppPermissions::groupedForFrontend();
    }
}

if (! function_exists('report_pdf_footer_html')) {
    /**
     * Platform footer for exported PDF reports (HTML with website and phone links).
     */
    function report_pdf_footer_html(string $reportName): string
    {
        $template = (string) config('reports.pdf.footer_line', '{REPORT_NAME} generated by {PRODUCT_NAME}, {WEBSITE} {PHONE}');
        $websiteLabel = (string) config('reports.pdf.website', 'thefoodpos.com');
        $websiteUrl = (string) config('reports.pdf.website_url', 'https://'.$websiteLabel);
        if (! str_starts_with($websiteUrl, 'http://') && ! str_starts_with($websiteUrl, 'https://')) {
            $websiteUrl = 'https://'.$websiteUrl;
        }

        $websiteLink = '<a href="'.e($websiteUrl).'" style="color:#4338ca;text-decoration:underline;">'
            .e($websiteLabel).'</a>';

        $phones = config('reports.pdf.phones', []);
        if (! is_array($phones) || $phones === []) {
            $legacyPhone = (string) config('reports.pdf.phone', '');
            if ($legacyPhone !== '') {
                $phones = array_map(
                    static fn (string $part) => ['label' => trim($part), 'tel' => preg_replace('/\D+/', '', trim($part)) ?? ''],
                    preg_split('/[\/|,]+/', $legacyPhone) ?: []
                );
            }
        }

        $phoneLinks = collect($phones)
            ->filter(fn ($phone) => is_array($phone) ? ($phone['label'] ?? $phone['tel'] ?? '') !== '' : (string) $phone !== '')
            ->map(function ($phone) {
                $label = is_array($phone) ? (string) ($phone['label'] ?? $phone['tel'] ?? '') : (string) $phone;
                $tel = is_array($phone) ? (string) ($phone['tel'] ?? $label) : (string) $phone;
                $tel = preg_replace('/[^\d+]/', '', $tel) ?? '';

                return '<a href="tel:'.e($tel).'" style="color:#4338ca;text-decoration:underline;">'
                    .e($label).'</a>';
            })
            ->implode(' / ');

        return str_replace(
            ['{REPORT_NAME}', '{PRODUCT_NAME}', '{WEBSITE}', '{PHONE}'],
            [
                e($reportName),
                e((string) config('reports.pdf.product_name', 'theFoodPOS')),
                $websiteLink,
                $phoneLinks,
            ],
            $template
        );
    }
}

if (! function_exists('report_pdf_footer_line')) {
    /**
     * Plain-text platform footer (no HTML links).
     */
    function report_pdf_footer_line(string $reportName): string
    {
        return strip_tags(report_pdf_footer_html($reportName));
    }
}

if (! function_exists('report_pdf_watermark_path')) {
    /**
     * Absolute filesystem path to the PDF watermark logo, or null when disabled/missing.
     */
    function report_pdf_watermark_path(): ?string
    {
        if (! config('reports.pdf.watermark.enabled', true)) {
            return null;
        }

        $logo = config('reports.pdf.watermark.logo');
        if (! is_string($logo) || $logo === '') {
            return null;
        }

        $path = public_path($logo);

        return is_file($path) ? $path : null;
    }
}

if (! function_exists('report_pdf_watermark_data_uri')) {
    /**
     * Base64 data URI for DomPDF (more reliable than filesystem paths).
     */
    function report_pdf_watermark_data_uri(): ?string
    {
        $path = report_pdf_watermark_path();
        if (! $path) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}

if (! function_exists('current_branch_id')) {
    /**
     * Effective branch for tenant operational screens (topbar selection or user default).
     */
    function current_branch_id(): ?int
    {
        return \App\Support\BranchContext::currentBranchId();
    }
}

if (! function_exists('show_branch_ui')) {
    /**
     * Whether to show branch switchers / filters (hidden in offline single-site edition).
     */
    function show_branch_ui(): bool
    {
        return ! (bool) config('offline.enabled');
    }
}

if (! function_exists('offline_edition')) {
    /**
     * Offline desktop edition (Tauri) — no SaaS branch / cloud print bridge UI.
     */
    function offline_edition(): bool
    {
        return (bool) config('offline.enabled');
    }
}
