# Company Configuration Usage Guide

## Overview

The company configuration system allows you to store and use company-wide settings like currency, timezone, date/time formats, and display preferences throughout your application.

## Configuration Options

### Stored in Database
- **Currency**: 3-letter currency code (USD, EUR, etc.)
- **Timezone**: PHP timezone string
- **Logo**: Company logo image path
- **Favicon**: Company favicon image path

### Stored in Settings JSON
- **currency_position**: `left` or `right` (e.g., $100 vs 100 $)
- **decimal_points**: `0-4` (number of decimal places)
- **time_format**: `12` or `24` (12-hour vs 24-hour)
- **date_format**: PHP date format string (Y-m-d, d/m/Y, etc.)

## Using CompanyConfigService

### In Controllers

```php
use App\Services\CompanyConfigService;

// Get all config
$config = CompanyConfigService::getConfig();

// Format currency
$formatted = CompanyConfigService::formatCurrency(1234.56);
// Returns: "$1,234.56" or "1,234.56 $" based on position

// Format date
$formatted = CompanyConfigService::formatDate(now());
// Returns date in company's preferred format

// Format time
$formatted = CompanyConfigService::formatTime(now());
// Returns time in 12 or 24 hour format

// Get currency symbol
$symbol = CompanyConfigService::getCurrencySymbol('USD');
// Returns: "$"
```

### In Blade Views

```blade
@php
    $config = \App\Services\CompanyConfigService::getConfig();
@endphp

<!-- Display currency -->
{{ \App\Services\CompanyConfigService::formatCurrency($order->total_amount) }}

<!-- Display date -->
{{ \App\Services\CompanyConfigService::formatDate($order->created_at) }}

<!-- Display time -->
{{ \App\Services\CompanyConfigService::formatTime($order->created_at) }}

<!-- Use config values -->
Currency: {{ $config['currency'] }}
Decimal Points: {{ $config['decimal_points'] }}
```

### In API Responses

```php
use App\Services\CompanyConfigService;

return response()->json([
    'amount' => CompanyConfigService::formatCurrency($order->total),
    'date' => CompanyConfigService::formatDate($order->created_at),
    'time' => CompanyConfigService::formatTime($order->created_at),
]);
```

## Example Usage in Order Display

```blade
<div class="order-total">
    <p>Total: {{ \App\Services\CompanyConfigService::formatCurrency($order->total_amount) }}</p>
    <p>Date: {{ \App\Services\CompanyConfigService::formatDate($order->created_at) }}</p>
    <p>Time: {{ \App\Services\CompanyConfigService::formatTime($order->created_at) }}</p>
</div>
```

## Default Values

If no company is found or settings are missing, defaults are used:
- Currency: USD
- Currency Position: left
- Decimal Points: 2
- Timezone: America/New_York
- Time Format: 12-hour
- Date Format: Y-m-d (2024-12-25)

## File Storage

- Logo: `storage/app/public/companies/logos/`
- Favicon: `storage/app/public/companies/favicons/`
- Access via: `Storage::url($company->logo)`

Make sure to run `php artisan storage:link` to create the symbolic link.

