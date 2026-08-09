<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order History — {{ $period['period_label'] }}</title>
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
            margin: 0 0 4px 0;
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
            margin: 12px 0 10px 0;
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
        .type-stats {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }
        .type-stats td {
            width: 25%;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            vertical-align: top;
            background: #fff;
        }
        .type-stats td.total {
            border: 2px solid #c7d2fe;
            background: #eef2ff;
        }
        .type-stats .stat-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            margin: 0 0 4px 0;
        }
        .type-stats td.total .stat-label {
            color: #4338ca;
        }
        .type-stats .stat-count {
            font-size: 10px;
            font-weight: bold;
            color: #111;
            margin: 0 0 2px 0;
        }
        .type-stats .stat-amount {
            font-size: 10px;
            font-weight: bold;
            color: #4338ca;
            margin: 0;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.data th,
        table.data td {
            padding: 5px 6px;
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
        table.data td.amount,
        table.data td.num {
            text-align: right;
            white-space: nowrap;
        }
        table.data tfoot td {
            border-top: 2px solid #111;
            font-weight: bold;
        }
        .filters {
            margin-bottom: 10px;
            font-size: 8px;
            color: #555;
            line-height: 1.5;
        }
        .note {
            margin-top: 10px;
            font-size: 8px;
            color: #666;
            line-height: 1.4;
        }
    </style>
    @include('partials.pdf-export-styles')
</head>
<body>
@php
    $pdfReportName = 'Order History';
    $branchLabel = $selectedBranch?->name ?? ($availableBranches->count() > 1 ? 'All branches' : ($availableBranches->first()?->name ?? '—'));
    $filterLabels = [];
    if (request('customer_id')) {
        $customer = $customers->firstWhere('id', (int) request('customer_id'));
        $filterLabels[] = 'Customer: '.($customer?->name ?? request('customer_id'));
    }
    if (request('waiter_id')) {
        $waiter = $staff->firstWhere('id', (int) request('waiter_id'));
        $filterLabels[] = 'Waiter: '.($waiter?->name ?? request('waiter_id'));
    }
    if (request('delivery_rider_id')) {
        $rider = $staff->firstWhere('id', (int) request('delivery_rider_id'));
        $filterLabels[] = 'Rider: '.($rider?->name ?? request('delivery_rider_id'));
    }
    if (request('type')) {
        $filterLabels[] = 'Type: '.\App\Support\OrderHistoryReport::typeLabel(request('type'));
    }
    if (request('order_number')) {
        $filterLabels[] = 'Order #: '.request('order_number');
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
                <p class="report-title">Order History</p>
                <p style="font-size: 9px; color: #555; margin: 3px 0 0 0;">{{ $period['period_label'] }}</p>
            </td>
            <td style="width: 40%;">
                <p class="generated-at"><strong>Generated:</strong> {{ format_datetime($generatedAt) }}</p>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td class="label">Period</td>
            <td>{{ format_date($period['period_start']) }} – {{ format_date($period['period_end']) }}</td>
            <td class="label">Branch</td>
            <td>{{ $branchLabel }}</td>
        </tr>
    </table>

    @php
        $typeRows = \App\Support\OrderHistoryReport::typeRowsForDisplay($summary);
        $totalCountLabel = \App\Support\OrderHistoryReport::orderCountLabel((int) $summary['order_count']);
    @endphp

    <table class="type-stats">
        <tr>
            @foreach($typeRows as $typeRow)
                <td>
                    <p class="stat-label">{{ $typeRow['label'] }}</p>
                    <p class="stat-count">{{ $typeRow['count_label'] }}</p>
                    <p class="stat-amount">{{ format_currency($typeRow['amount']) }}</p>
                </td>
            @endforeach
            <td class="total">
                <p class="stat-label">Total</p>
                <p class="stat-count">{{ $totalCountLabel }}</p>
                <p class="stat-amount">{{ format_currency($summary['total_amount']) }}</p>
            </td>
        </tr>
    </table>

    @if(!empty($filterLabels))
        <p class="filters"><strong>Filters:</strong> {{ implode(' · ', $filterLabels) }}</p>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Type</th>
                <th>Customer</th>
                <th>Waiter</th>
                <th>Rider</th>
                <th>Table</th>
                <th class="num">Items</th>
                <th class="amount">Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ordersForPdf as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ \App\Support\OrderHistoryReport::formatOrderDate($order) }}</td>
                    <td>{{ \App\Support\OrderHistoryReport::typeLabel($order->type) }}</td>
                    <td>{{ \App\Support\OrderHistoryReport::customerDisplayName($order) }}</td>
                    <td>{{ $order->waiter?->name ?? '—' }}</td>
                    <td>{{ $order->deliveryRider?->name ?? '—' }}</td>
                    <td>{{ $order->table?->name ?? '—' }}</td>
                    <td class="num">{{ $order->items_count }}</td>
                    <td class="amount">{{ format_currency($order->total_amount) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center; color: #666;">No orders match the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
        @if($summary['order_count'] > 0)
            <tfoot>
                <tr>
                    <td colspan="8">Total ({{ number_format($summary['order_count']) }} orders)</td>
                    <td class="amount">{{ format_currency($summary['total_amount']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if($ordersForPdf->count() >= \App\Support\OrderHistoryReport::PDF_LIMIT)
        <p class="note">Showing the first {{ number_format(\App\Support\OrderHistoryReport::PDF_LIMIT) }} orders. Narrow your filters for a complete export.</p>
    @endif

    @include('partials.pdf-export-footer')
</body>
</html>
