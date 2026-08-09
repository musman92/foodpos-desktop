@php
    $isWeekly = $reportMode === 'weekly';
    $title = $isWeekly ? 'Weekly Closing Report' : 'Monthly Closing Report';
    $branchLabel = $selectedBranch?->name ?? ($availableBranches->count() > 1 ? 'All branches' : ($availableBranches->first()?->name ?? '—'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
            padding: 0;
        }
        body {
            padding: 12px 14px;
        }
        table {
            border-collapse: collapse;
        }
        td, th {
            vertical-align: top;
        }
        .report-header {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 8px;
            margin-bottom: 10px;
            page-break-after: avoid;
        }
        .business-name {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .business-line {
            font-size: 10px;
            color: #333;
            margin: 0 0 2px 0;
            line-height: 1.4;
        }
        .report-title {
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 3px 0;
            page-break-after: avoid;
        }
        .report-meta {
            font-size: 10px;
            color: #555;
            margin: 0 0 10px 0;
            page-break-after: avoid;
        }
        .period-section {
            margin-bottom: 12px;
            border: 1px solid #d1d5db;
            page-break-inside: auto;
        }
        .period-header {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 7px 9px;
            page-break-after: avoid;
        }
        .period-title {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 2px 0;
        }
        .period-meta {
            font-size: 10px;
            color: #555;
            margin: 0;
        }
        .block-section {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            page-break-inside: auto;
        }
        .block-section:last-child {
            border-bottom: none;
        }
        .closing-block {
            background: #f9fafb;
            page-break-inside: avoid;
        }
        .daily-block {
            page-break-inside: auto;
        }
        .day-card {
            width: 100%;
            margin-bottom: 8px;
            border: 1px solid #e5e7eb;
            page-break-inside: auto;
        }
        .day-card th,
        .day-card td {
            padding: 3px 6px;
            vertical-align: top;
            text-align: left;
            font-size: 9px;
            border-bottom: 1px solid #f3f4f6;
        }
        .day-card-head {
            background: #f3f4f6;
            border-bottom: 1px solid #d1d5db;
            padding: 5px 6px !important;
        }
        .day-card-title {
            font-weight: bold;
            font-size: 10px;
            color: #111;
            text-transform: none;
            letter-spacing: 0;
        }
        .day-card-date {
            float: right;
            font-weight: normal;
            font-size: 9px;
            color: #6b7280;
            text-transform: none;
        }
        .day-label {
            color: #374151;
            width: 72%;
        }
        .day-expense-line {
            color: #6b7280;
            font-size: 8px;
            padding-left: 12px !important;
        }
        .day-expense-detail {
            color: #9ca3af;
        }
        .day-cash-row td {
            border-top: 1px solid #e5e7eb;
            border-bottom: none;
            color: #3730a3;
            background: #eef2ff;
        }
        .day-card .amount {
            text-align: right;
            white-space: nowrap;
            width: 28%;
        }
        .col-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            margin: 0 0 6px 0;
            text-align: left;
        }
        .data-table {
            width: 100%;
        }
        .data-table th,
        .data-table td {
            padding: 4px 5px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
            text-align: left;
            font-size: 10px;
        }
        .data-table th {
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #d1d5db;
            background: #f3f4f6;
        }
        .data-table tbody tr.stripe td {
            background: #f9fafb;
        }
        .data-table thead {
            display: table-header-group;
        }
        .data-table tr {
            page-break-inside: avoid;
        }
        .data-table .amount {
            text-align: right;
            white-space: nowrap;
        }
        .data-table .col-sno {
            width: 8%;
            color: #6b7280;
        }
        .data-table .total-row td {
            border-top: 1px solid #d1d5db;
            font-weight: bold;
            background: #f3f4f6;
        }
        .empty-cell {
            text-align: left;
            color: #6b7280;
            padding: 8px 4px;
        }
        .strong {
            font-weight: bold;
        }
        .closing-summary {
            width: 100%;
        }
        .closing-summary td {
            padding: 4px 0;
            vertical-align: top;
            text-align: left;
            font-size: 11px;
        }
        .closing-summary tr.stripe td {
            background: #f3f4f6;
        }
        .closing-label {
            color: #374151;
            padding-right: 8px;
            text-align: left;
        }
        .closing-value {
            text-align: right;
            white-space: nowrap;
            font-weight: 600;
            font-size: 11px;
        }
        .closing-divider td {
            border-top: 1px solid #d1d5db;
            padding-top: 5px;
        }
        .closing-highlight td {
            border-top: 2px solid #c7d2fe;
            background: #eef2ff !important;
            padding-top: 5px;
            color: #312e81;
        }
        .positive { color: #15803d; }
        .negative { color: #b91c1c; }
        .grand-total {
            margin-top: 8px;
            border: 2px solid #c7d2fe;
            background: #eef2ff;
            padding: 8px 10px;
            page-break-inside: auto;
        }
        .grand-total-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #3730a3;
            margin: 0 0 6px 0;
            text-align: left;
        }
        .note {
            margin-top: 8px;
            font-size: 9px;
            color: #666;
            line-height: 1.4;
            text-align: left;
        }
    </style>
    @include('partials.pdf-export-styles')
</head>
<body>
@php $pdfReportName = $title; @endphp

<div class="report-header">
    <p class="business-name">{{ $businessName }}</p>
    @if($businessAddress)
        <p class="business-line">{{ $businessAddress }}</p>
    @endif
    @if($businessPhone)
        <p class="business-line">Tel: {{ $businessPhone }}</p>
    @endif
</div>

<p class="report-title">{{ $title }}</p>
<p class="report-meta">Branch: {{ $branchLabel }} · Generated: {{ format_datetime($generatedAt) }}</p>

@include('partials.pdf-export-watermark')

@foreach($report['periods'] as $section)
    @include('reports._period-closing-section-pdf', [
        'section' => $section,
        'selectedBranch' => $selectedBranch,
        'availableBranches' => $availableBranches,
        'paymentColumns' => $report['payment_columns'],
    ])
@endforeach

@if(count($report['periods']) > 1)
    <div class="grand-total">
        <p class="grand-total-title">Grand total ({{ pdf_currency_symbol() }})</p>
        @include('reports._period-closing-summary-pdf', ['closing' => $report['grand_closing']])
    </div>
@endif

<p class="note">
    Stock in hand uses current inventory value at report time, not a historical snapshot.
    Purchases are item totals; expenses include recorded expenses and manual outgoing transactions.
</p>

@include('partials.pdf-export-footer')
</body>
</html>
