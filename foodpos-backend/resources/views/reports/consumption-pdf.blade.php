@php
    $title = 'Consumption Report';
    $branchLabel = $selectedBranch?->name
        ?? ($availableBranches->count() > 1 ? 'All branches' : ($availableBranches->first()?->name ?? '—'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — {{ format_date($from) }} to {{ format_date($to) }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111;
            margin: 0;
            padding: 14px 16px;
        }
        .report-header {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .business-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 5px 0;
            text-transform: uppercase;
        }
        .business-line {
            font-size: 10px;
            color: #333;
            margin: 0 0 2px 0;
            line-height: 1.4;
        }
        .report-title-row {
            width: 100%;
            margin: 10px 0 10px 0;
            border-collapse: collapse;
        }
        .report-title-row td {
            vertical-align: middle;
            padding: 0;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
        }
        .generated-at {
            font-size: 9px;
            color: #444;
            text-align: right;
            margin: 0;
        }
        .summary {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .summary td {
            padding: 5px 6px;
            border: 1px solid #ccc;
            vertical-align: top;
        }
        .summary .label {
            font-weight: bold;
            width: 18%;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.data th,
        table.data td {
            padding: 4px 5px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.data th {
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            color: #555;
            font-weight: bold;
            border-bottom: 2px solid #111;
        }
        table.data td.num,
        table.data th.num {
            text-align: right;
            white-space: nowrap;
        }
        table.data tfoot td {
            border-top: 2px solid #111;
            font-weight: bold;
        }
        .muted { color: #555; font-weight: normal; }
        .note {
            margin-top: 12px;
            font-size: 8px;
            color: #666;
            line-height: 1.45;
        }
    </style>
    @include('partials.pdf-export-styles')
</head>
<body>
@php
    $pdfReportName = $title;
@endphp
@include('partials.pdf-export-watermark')
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
                <p class="report-title">{{ $title }}</p>
                <p style="font-size: 9px; color: #555; margin: 3px 0 0 0;">
                    {{ format_date($from) }} – {{ format_date($to) }}
                </p>
            </td>
            <td style="width: 40%;">
                <p class="generated-at"><strong>Generated:</strong> {{ format_datetime($generatedAt) }}</p>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td class="label">Branch</td>
            <td>{{ $branchLabel }}</td>
            <td class="label">Items</td>
            <td>{{ number_format($summary['item_count']) }}</td>
        </tr>
        <tr>
            <td class="label">Total value</td>
            <td style="font-weight: bold;">{{ number_format($summary['total_cost'], 2) }}</td>
            <td class="label">Sales / Adjustments</td>
            <td>{{ number_format($summary['sales_cost'], 2) }} / {{ number_format($summary['adjustment_cost'], 2) }}</td>
        </tr>
        @if(!empty($search))
            <tr>
                <td class="label">Search</td>
                <td colspan="3">{{ $search }}</td>
            </tr>
        @endif
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Type</th>
                <th>Item</th>
                <th>Code</th>
                <th>Category</th>
                <th class="num">Qty used</th>
                <th class="num">Remaining</th>
                <th class="num">Avg cost</th>
                <th class="num">Total cost</th>
                <th class="num">Sales</th>
                <th class="num">Adjust</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['item_type_label'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['code'] ?: '—' }}</td>
                    <td>{{ $row['category'] ?: '—' }}</td>
                    <td class="num">
                        {{ number_format($row['quantity'], 2) }}
                        @if(!empty($row['quantity_unit']))
                            <span class="muted">{{ $row['quantity_unit'] }}</span>
                        @endif
                    </td>
                    <td class="num">
                        {{ number_format($row['remaining_stock'] ?? 0, 2) }}
                        @if(!empty($row['remaining_stock_unit']))
                            <span class="muted">{{ $row['remaining_stock_unit'] }}</span>
                        @endif
                    </td>
                    <td class="num">{{ number_format($row['avg_unit_cost'], 2) }}</td>
                    <td class="num">{{ number_format($row['total_cost'], 2) }}</td>
                    <td class="num">{{ number_format($row['sales_cost'], 2) }}</td>
                    <td class="num">{{ number_format($row['adjustment_cost'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center; padding: 16px; color: #666;">
                        No inventory consumption recorded for this period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="7" class="num">Total</td>
                    <td class="num">{{ number_format($summary['total_cost'], 2) }}</td>
                    <td class="num">{{ number_format($summary['sales_cost'], 2) }}</td>
                    <td class="num">{{ number_format($summary['adjustment_cost'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <p class="note">
        Qty used is in consumption units. Remaining stock is shown in purchase units (same as Stock).
    </p>
</body>
</html>
