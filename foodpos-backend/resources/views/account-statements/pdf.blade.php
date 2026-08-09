<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Statement — {{ $party->name }}</title>
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
            margin-bottom: 14px;
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
        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.lines th,
        table.lines td {
            border: 1px solid #ccc;
            padding: 5px 6px;
            text-align: left;
        }
        table.lines th {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: bold;
            border-bottom: 2px solid #111;
        }
        table.lines td.num,
        table.lines th.num {
            text-align: right;
            white-space: nowrap;
        }
        table.lines tfoot td {
            font-weight: bold;
            border-top: 2px solid #111;
        }
        .empty {
            text-align: center;
            padding: 24px;
            color: #666;
        }
    </style>
    @include('partials.pdf-export-styles')
</head>
<body>
@php
    $typeLabel = $typeLabel ?? match ($type) {
        'supplier' => 'Supplier',
        'employee' => 'Employee',
        default => 'Customer',
    };
    $partyBalance = $partyBalance ?? (float) ($party->balance ?? 0);
    $pdfReportName = 'Account Statement';
    $periodLabel = 'All dates';
    if ($from && $to) {
        $periodLabel = format_date($from).' – '.format_date($to);
    } elseif ($from) {
        $periodLabel = 'From '.format_date($from);
    } elseif ($to) {
        $periodLabel = 'Until '.format_date($to);
    }
    $outstandingDisplay = format_currency(abs((float) $partyBalance));
    if ($type === 'employee' && abs((float) $partyBalance) >= 0.009) {
        $outstandingDisplay .= (float) $partyBalance > 0 ? ' payable' : ' advance';
    }
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
                <p class="report-title">Account Statement</p>
                @if($branch)
                    <p style="font-size: 10px; color: #555; margin: 4px 0 0 0;">Branch: {{ $branch->name }}</p>
                @endif
            </td>
            <td style="width: 42%;">
                <p class="generated-at"><strong>Generated:</strong> {{ format_datetime($generatedAt) }}</p>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td class="label">{{ $typeLabel }}</td>
            <td>{{ $party->name }}</td>
            <td class="label">Statement type</td>
            <td>{{ $typeLabel }}</td>
        </tr>
        <tr>
            <td class="label">Period</td>
            <td>{{ $periodLabel }}</td>
            <td class="label">{{ $type === 'employee' ? 'Balance' : 'Outstanding' }}</td>
            <td>{{ $outstandingDisplay }} <span style="font-weight: normal; color: #666;">(company-wide)</span></td>
        </tr>
        @if($party->phone)
        <tr>
            <td class="label">Phone</td>
            <td>{{ $party->phone }}</td>
            <td class="label">Email</td>
            <td>{{ $party->email ?? '—' }}</td>
        </tr>
        @endif
    </table>

    @if(count($statement['lines']) > 0)
        <table class="lines">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th>Payment source</th>
                    <th class="num">Debit (DR)</th>
                    <th class="num">Credit (CR)</th>
                    <th class="num">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($statement['lines'] as $line)
                    <tr @if(($line['type'] ?? '') === 'opening_balance') style="background:#f8fafc;font-weight:600;" @endif>
                        <td>{{ format_date($line['date_display']) }}</td>
                        <td>{{ $line['label'] }}</td>
                        <td>{{ $line['reference'] }}</td>
                        <td>{{ $line['money_source'] ?? '—' }}</td>
                        <td class="num">{{ $line['debit'] > 0 ? format_currency($line['debit']) : '—' }}</td>
                        <td class="num">{{ $line['credit'] > 0 ? format_currency($line['credit']) : '—' }}</td>
                        <td class="num">{{ format_currency($line['balance']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="num">Closing balance (this branch, selected period)</td>
                    <td class="num">{{ format_currency($statement['closing_balance']) }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <p class="empty">No transactions found for the selected period.</p>
    @endif

    @include('partials.pdf-export-footer')
</body>
</html>
