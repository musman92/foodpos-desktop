<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }} — Invoice</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 0; padding: 24px; font-size: 14px; }
        .toolbar { margin-bottom: 24px; }
        .toolbar button { background: #4f46e5; color: #fff; border: 0; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .header { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 32px; }
        .title { font-size: 28px; font-weight: 700; letter-spacing: 0.04em; margin: 24px 0; }
        .meta { color: #6b7280; line-height: 1.6; }
        .party { width: 48%; }
        .party h3 { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { font-size: 12px; text-transform: uppercase; color: #6b7280; }
        td.num, th.num { text-align: right; }
        .totals { margin-top: 16px; margin-left: auto; width: 280px; }
        .totals div { display: flex; justify-content: space-between; padding: 6px 0; }
        .totals .grand { border-top: 2px solid #111827; margin-top: 8px; padding-top: 10px; font-size: 18px; font-weight: 700; }
        .notes { margin-top: 32px; padding: 16px; background: #f9fafb; border-radius: 8px; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print invoice</button>
    </div>

    <div class="header">
        <div class="party">
            <h3>From</h3>
            <strong>{{ $vendor['name'] ?? config('app.name') }}</strong><br>
            @if(! empty($vendor['email'])){{ $vendor['email'] }}<br>@endif
            @if(! empty($vendor['phone'])){{ $vendor['phone'] }}<br>@endif
            @if(! empty($vendor['address'])){!! nl2br(e($vendor['address'])) !!}<br>@endif
            @if(! empty($vendor['tax_id']))Tax ID: {{ $vendor['tax_id'] }}@endif
        </div>
        <div class="party">
            <h3>Bill to</h3>
            <strong>{{ $invoice->company->name }}</strong><br>
            @if($invoice->company->email){{ $invoice->company->email }}<br>@endif
            @if($invoice->company->phone){{ $invoice->company->phone }}<br>@endif
            @if($invoice->company->address){!! nl2br(e($invoice->company->address)) !!}@endif
        </div>
    </div>

    <div class="title">INVOICE</div>

    <div class="meta">
        Invoice #: <strong>{{ $invoice->invoice_number }}</strong><br>
        Issue date: {{ $invoice->issue_date->format('M j, Y') }}<br>
        Due date: {{ $invoice->due_date->format('M j, Y') }}<br>
        Status: {{ $invoice->statusLabel() }} · {{ $invoice->currency }} · {{ $invoice->billingIntervalLabel() }}
        @if($invoice->period_start && $invoice->period_end)
            <br>Billing period: {{ $invoice->period_start->format('M j, Y') }} – {{ $invoice->period_end->format('M j, Y') }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ format_quantity((float) $item->quantity) }}</td>
                    <td class="num">{{ format_platform_currency((float) $item->unit_price, $invoice->currency) }}</td>
                    <td class="num">{{ format_platform_currency((float) $item->line_total, $invoice->currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span>{{ format_platform_currency((float) $invoice->subtotal, $invoice->currency) }}</span></div>
        <div><span>Tax</span><span>{{ format_platform_currency((float) $invoice->tax_amount, $invoice->currency) }}</span></div>
        <div class="grand"><span>Total due</span><span>{{ $invoice->formattedTotal() }}</span></div>
        @if($invoice->amount_paid > 0)
            <div><span>Paid</span><span>{{ format_platform_currency($invoice->amount_paid, $invoice->currency) }}</span></div>
            <div><strong>Balance</strong><strong>{{ $invoice->formattedBalanceDue() }}</strong></div>
        @endif
    </div>

    @if($invoice->notes)
        <div class="notes">
            <strong>Notes</strong><br>
            {!! nl2br(e($invoice->notes)) !!}
        </div>
    @endif
</body>
</html>
