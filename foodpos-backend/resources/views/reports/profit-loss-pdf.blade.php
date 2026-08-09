<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profit &amp; Loss — {{ $report['period_label'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
            padding: 16px 20px;
        }
        .report-header {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .business-name {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 6px 0;
            text-transform: uppercase;
        }
        .business-line {
            font-size: 11px;
            color: #333;
            margin: 0 0 3px 0;
            line-height: 1.45;
        }
        .report-title-row {
            width: 100%;
            margin: 14px 0 12px 0;
            border-collapse: collapse;
        }
        .report-title-row td {
            vertical-align: middle;
            padding: 0;
        }
        .report-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
        }
        .generated-at {
            font-size: 10px;
            color: #444;
            text-align: right;
            margin: 0;
        }
        .summary {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .summary td {
            padding: 6px 8px;
            border: 1px solid #ccc;
            vertical-align: top;
        }
        .summary .label {
            font-weight: bold;
            width: 28%;
        }
        table.pl {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.pl td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.pl td.amount {
            text-align: right;
            white-space: nowrap;
            width: 28%;
        }
        table.pl tr.section td {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            color: #555;
            border-bottom: 1px solid #ccc;
            padding-top: 10px;
        }
        table.pl tr.subtotal td {
            font-weight: bold;
            border-top: 1px solid #ccc;
            border-bottom: none;
        }
        table.pl tr.total td {
            font-weight: bold;
            font-size: 12px;
            border-top: 2px solid #111;
            border-bottom: 2px solid #111;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        table.pl tr.indent td:first-child {
            padding-left: 18px;
        }
        table.pl .negative {
            color: #b91c1c;
        }
        table.pl .positive {
            color: #15803d;
        }
        .note {
            margin-top: 14px;
            font-size: 9px;
            color: #666;
            line-height: 1.5;
        }
    </style>
    @include('partials.pdf-export-styles')
</head>
<body>
@php
    $pdfReportName = 'Profit & Loss Statement';
@endphp
@include('partials.pdf-export-watermark')
@php
    $branchLabel = $selectedBranch?->name ?? ($availableBranches->count() > 1 ? 'All branches' : ($availableBranches->first()?->name ?? '—'));
@endphp

    <div class="report-header">
        <p class="business-name">{{ $businessName }}</p>
        @if($businessAddress)
            <p class="business-line">{{ $businessAddress }}</p>
        @endif
        @if($businessPhone)
            <p class="business-line">Tel: {{ $businessPhone }}</p>
        @endif
    </div>

    <table class="report-title-row">
        <tr>
            <td>
                <p class="report-title">Profit &amp; Loss Statement</p>
                <p style="font-size: 10px; color: #555; margin: 4px 0 0 0;">{{ $report['period_label'] }}</p>
            </td>
            <td style="width: 42%;">
                <p class="generated-at"><strong>Generated:</strong> {{ format_datetime($generatedAt) }}</p>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td class="label">Period</td>
            <td>{{ format_date($report['period_start']) }} – {{ format_date($report['period_end']) }}</td>
            <td class="label">Branch</td>
            <td>{{ $branchLabel }}</td>
        </tr>
        <tr>
            <td class="label">Orders</td>
            <td>{{ number_format($report['revenue']['order_count']) }}</td>
            <td class="label">Net profit</td>
            <td class="{{ $report['net_profit'] >= 0 ? 'positive' : 'negative' }}" style="font-weight: bold;">
                {{ format_currency($report['net_profit']) }}
                @if($report['net_margin_percent'] !== null)
                    ({{ number_format($report['net_margin_percent'], 1) }}%)
                @endif
            </td>
        </tr>
    </table>

    <table class="pl">
        <tr class="section">
            <td colspan="2">Revenue</td>
        </tr>
        <tr class="indent">
            <td>Gross sales</td>
            <td class="amount">{{ format_currency($report['revenue']['gross_sales']) }}</td>
        </tr>
        <tr class="indent">
            <td>Less: Discounts</td>
            <td class="amount negative">({{ format_currency($report['revenue']['discounts']) }})</td>
        </tr>
        <tr class="indent">
            <td>Less: Refunds</td>
            <td class="amount negative">({{ format_currency($report['revenue']['refunds']) }})</td>
        </tr>
        <tr class="subtotal">
            <td>Net sales</td>
            <td class="amount">{{ format_currency($report['revenue']['net_sales']) }}</td>
        </tr>

        <tr class="section">
            <td colspan="2">Cost of goods sold</td>
        </tr>
        <tr class="indent">
            <td>Recipe / item cost (sold)</td>
            <td class="amount">{{ format_currency($report['cogs']['sold_cost']) }}</td>
        </tr>
        @if($report['cogs']['refund_cost'] > 0)
            <tr class="indent">
                <td>Less: Cost reversed (refunds)</td>
                <td class="amount positive">({{ format_currency($report['cogs']['refund_cost']) }})</td>
            </tr>
        @endif
        <tr class="subtotal">
            <td>Total COGS</td>
            <td class="amount">{{ format_currency($report['cogs']['total']) }}</td>
        </tr>
        <tr class="subtotal">
            <td>Gross profit</td>
            <td class="amount {{ $report['cogs']['gross_profit'] >= 0 ? 'positive' : 'negative' }}">
                {{ format_currency($report['cogs']['gross_profit']) }}
                @if($report['cogs']['gross_margin_percent'] !== null)
                    ({{ number_format($report['cogs']['gross_margin_percent'], 1) }}%)
                @endif
            </td>
        </tr>

        <tr class="section">
            <td colspan="2">Operating expenses</td>
        </tr>
        @forelse($report['operating_expenses']['categories'] as $row)
            <tr class="indent">
                <td>{{ $row['label'] }}</td>
                <td class="amount">{{ format_currency($row['amount']) }}</td>
            </tr>
        @empty
            <tr class="indent">
                <td colspan="2" style="color: #666;">No operating expenses recorded for this period.</td>
            </tr>
        @endforelse
        <tr class="subtotal">
            <td>Total operating expenses</td>
            <td class="amount">{{ format_currency($report['operating_expenses']['total']) }}</td>
        </tr>

        <tr class="total">
            <td>Net profit (loss)</td>
            <td class="amount {{ $report['net_profit'] >= 0 ? 'positive' : 'negative' }}">{{ format_currency($report['net_profit']) }}</td>
        </tr>
    </table>

    @if($report['cogs']['lines_without_cost'] > 0)
        <p class="note">
            Note: {{ number_format($report['cogs']['lines_without_cost']) }} sold line(s) had no recipe/item cost — COGS may be understated.
        </p>
    @endif

    <p class="note">
        Revenue is net of discounts and refunds (tax excluded). COGS uses recipe/item costs at report time.
        Purchases and supplier payments are not included as operating expenses.
    </p>

    @include('partials.pdf-export-footer')
</body>
</html>
