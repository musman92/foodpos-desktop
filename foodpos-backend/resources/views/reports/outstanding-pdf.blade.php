@php
    $meta = match ($reportType) {
        'receivable' => ['title' => 'Accounts Receivable', 'partyLabel' => 'Customer', 'amountLabel' => 'Outstanding'],
        'payable' => ['title' => 'Accounts Payable', 'partyLabel' => 'Supplier', 'amountLabel' => 'Outstanding'],
        'customer-credit' => ['title' => 'Customer Credits', 'partyLabel' => 'Customer', 'amountLabel' => 'Credit available'],
        'supplier-prepayment' => ['title' => 'Supplier Prepayments', 'partyLabel' => 'Supplier', 'amountLabel' => 'Prepaid'],
        default => ['title' => 'Outstanding Report', 'partyLabel' => 'Party', 'amountLabel' => 'Amount'],
    };
    $title = $meta['title'];
    $partyLabel = $meta['partyLabel'];
    $amountLabel = $meta['amountLabel'];
    $isReceivable = in_array($reportType, ['receivable', 'customer-credit'], true);
    $branchLabel = $selectedBranch?->name ?? ($availableBranches->count() > 1 ? 'All branches (company total)' : ($availableBranches->first()?->name ?? '—'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — {{ format_date($report['as_of']) }}</title>
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
            width: 22%;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.data th,
        table.data td {
            padding: 7px 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.data th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            color: #555;
            font-weight: bold;
            border-bottom: 2px solid #111;
        }
        table.data td.amount {
            text-align: right;
            white-space: nowrap;
            font-weight: bold;
        }
        table.data tfoot td {
            border-top: 2px solid #111;
            font-weight: bold;
        }
        .total-highlight {
            color: {{ $isReceivable ? '#b45309' : '#b91c1c' }};
        }
        .note {
            margin-top: 14px;
            font-size: 9px;
            color: #666;
            line-height: 1.5;
        }
        .footer {
            margin-top: 16px;
            font-size: 9px;
            color: #666;
            text-align: center;
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
                <p style="font-size: 10px; color: #555; margin: 4px 0 0 0;">As of {{ format_date($report['as_of']) }}</p>
            </td>
            <td style="width: 42%;">
                <p class="generated-at"><strong>Generated:</strong> {{ format_datetime($generatedAt) }}</p>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td class="label">Branch</td>
            <td>{{ $branchLabel }}</td>
            <td class="label">{{ Str::plural($partyLabel) }}</td>
            <td>{{ number_format($report['party_count']) }}</td>
        </tr>
        <tr>
            <td class="label">Total outstanding</td>
            <td colspan="3" class="total-highlight" style="font-weight: bold;">{{ format_currency($report['total']) }}</td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 32%;">{{ $partyLabel }}</th>
                <th>Contact</th>
                <th style="width: 18%; text-align: right;">{{ $amountLabel }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['contact'] ?? '—' }}</td>
                    <td class="amount">{{ format_currency($row['balance']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center; color: #666; padding: 16px;">
                        No outstanding {{ strtolower(Str::plural($partyLabel)) }} for this selection.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($report['party_count'] > 0)
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align: right;">Total</td>
                    <td class="amount total-highlight">{{ format_currency($report['total']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <p class="note">
        Amounts reflect current ledger balances on {{ strtolower($partyLabel) }} accounts (same as the {{ strtolower($partyLabel) }} list).
    </p>

    @include('partials.pdf-export-footer')
</body>
</html>
