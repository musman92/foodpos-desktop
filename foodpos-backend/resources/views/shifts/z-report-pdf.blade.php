@php
    $shift = $report['shift'];
    $title = $report['is_interim'] ? 'Interim Z Report' : 'Z Report';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — Shift #{{ $shift->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
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
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin: 6px 0 0 0;
        }
        .z-report-body .section-title {
            font-size: 12px;
            font-weight: bold;
            margin: 14px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #d1d5db;
        }
        .z-report-body .report-meta p {
            margin: 2px 0;
            font-size: 10px;
            color: #374151;
        }
        .z-report-body .interim-note {
            color: #92400e;
            font-weight: bold;
        }
        .z-report-body .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .z-report-body .data-table th,
        .z-report-body .data-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .z-report-body .data-table th {
            background: #f3f4f6;
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
        }
        .z-report-body .data-table .amount {
            text-align: right;
        }
        .z-report-body .data-table tr.highlight td {
            background: #f9fafb;
            font-weight: bold;
        }
        .z-report-body .positive { color: #047857; }
        .z-report-body .negative { color: #b91c1c; }
        .z-report-body .muted { color: #6b7280; font-size: 9px; }
    </style>
</head>
<body>
    <div class="report-header">
        <p class="business-name">{{ $shift->company?->name ?? config('app.name') }}</p>
        @if($shift->branch)
            <p style="font-size: 10px; margin: 0;">{{ $shift->branch->name }}</p>
        @endif
        <p class="report-title">{{ $title }}</p>
    </div>

    @include('shifts._z-report-body', ['report' => $report, 'isPdf' => true])
</body>
</html>
