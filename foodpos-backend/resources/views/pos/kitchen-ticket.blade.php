<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOT #{{ $kot->kot_number }} — {{ $order->order_number }}</title>
    @php
        $receiptLayout = receipt_layout_settings();
        $receiptBasePx = (int) ($receiptLayout['font_size_px'] ?? 14);
        $receiptPaperMm = (int) ($receiptLayout['paper_width_mm'] ?? 80);
        $receiptPadLeftMm = (int) ($receiptLayout['pad_left_mm'] ?? 2);
        $receiptPadRightMm = (int) ($receiptLayout['pad_right_mm'] ?? 5);
        $receiptAmountPadRightMm = (int) ($receiptLayout['amount_pad_right_mm'] ?? 2);
        $receiptColItemPct = (int) ($receiptLayout['col_item_pct'] ?? 54);
        $receiptColQtyPct = (int) ($receiptLayout['col_qty_pct'] ?? 14);
        $receiptColPricePct = (int) ($receiptLayout['col_price_pct'] ?? 32);
    @endphp
    <style>
        @page {
            size: {{ $receiptPaperMm }}mm auto;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 700;
        }

        html {
            width: {{ $receiptPaperMm }}mm;
            max-width: {{ $receiptPaperMm }}mm;
            margin: 0 auto;
        }

        body {
            font-size: {{ $receiptBasePx }}px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            width: {{ $receiptPaperMm }}mm;
            max-width: {{ $receiptPaperMm }}mm;
            margin: 0 auto;
            padding: 2mm {{ $receiptPadRightMm }}mm 2mm {{ $receiptPadLeftMm }}mm;
        }

        .kot-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 0.571em;
            margin-bottom: 0.571em;
        }

        .kot-title {
            font-size: 1.286em;
            letter-spacing: 0.5px;
        }

        .kot-type {
            font-size: 1.5em;
            letter-spacing: 1px;
            margin-top: 0.357em;
            font-weight: 900;
        }

        .kot-type--void {
            border: 3px solid #000;
            padding: 0.286em 0.714em;
            display: inline-block;
            margin-top: 0.357em;
        }

        .kot-type--updated {
            border: 3px solid #000;
            padding: 0.286em 0.714em;
            display: inline-block;
            margin-top: 0.357em;
            font-size: 1.714em;
            letter-spacing: 1.5px;
        }

        .reprint-banner {
            text-align: center;
            font-size: 1.143em;
            letter-spacing: 1px;
            border: 3px solid #000;
            padding: 0.429em 0.286em;
            margin-bottom: 0.571em;
            background: #000;
            color: #fff;
        }

        .reprint-note {
            text-align: center;
            font-size: 0.786em;
            border: 2px dashed #000;
            padding: 0.286em;
            margin-bottom: 0.571em;
        }

        .meta {
            margin-bottom: 0.571em;
            font-size: 0.929em;
        }

        .meta p {
            margin: 0.143em 0;
        }

        table.items {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 0.429em;
        }

        table.items th {
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 0.286em 0.143em;
            font-size: 0.857em;
        }

        table.items td {
            padding: 0.357em 0.143em;
            vertical-align: top;
            border-bottom: 1px dashed #ccc;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table.items td.qty {
            width: {{ $receiptColQtyPct }}%;
            text-align: center;
            font-size: 1.071em;
            white-space: nowrap;
            padding-right: {{ $receiptAmountPadRightMm }}mm;
        }

        .item-note {
            font-size: 0.786em;
            font-weight: 600;
            margin-top: 0.143em;
        }

        .item-variant {
            font-size: 0.786em;
            font-weight: 600;
        }

        .footer {
            margin-top: 0.714em;
            text-align: center;
            font-size: 0.786em;
            border-top: 1px dashed #000;
            padding-top: 0.429em;
        }

        @media print {
            @page {
                size: {{ $receiptPaperMm }}mm auto;
                margin: 0;
            }

            html, body {
                width: {{ $receiptPaperMm }}mm !important;
                max-width: {{ $receiptPaperMm }}mm !important;
                font-size: {{ $receiptBasePx }}px !important;
                padding: 2mm {{ $receiptPadRightMm }}mm 2mm {{ $receiptPadLeftMm }}mm !important;
            }

            .no-print {
                display: none !important;
            }
        }

        .print-actions {
            text-align: center;
            margin-bottom: 0.857em;
        }

        .print-actions button {
            padding: 0.571em 1.143em;
            font-size: 1em;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="print-actions no-print">
        <button type="button" onclick="window.print()">Print KOT</button>
    </div>

    @php
        $isReprintSlip = ($showReprint ?? false) || $kot->is_reprint;
        $isOrderCancelSlip = ($showOrderCancel ?? false) || ($kot->type === 'void' && request()->boolean('cancel'));
    @endphp

    @if($isOrderCancelSlip)
        <div class="reprint-banner">*** CANCELLED ***</div>
        <div class="reprint-note">Order cancelled — do not prepare / stop preparation</div>
    @elseif($isReprintSlip)
        <div class="reprint-banner">*** REPRINT ***</div>
        <div class="reprint-note">Duplicate slip — items already sent to kitchen</div>
    @endif

    <div class="kot-header">
        <div class="kot-title">KOT #{{ $kot->kot_number }}</div>
        <div style="font-size: 1.071em; margin-top: 0.286em;">Token #: {{ $kot->token_number }}</div>
        @if($isOrderCancelSlip)
            <div class="kot-type kot-type--updated">CANCELLED</div>
        @elseif($isReprintSlip)
            <div class="kot-type kot-type--void">REPRINT</div>
        @elseif($kot->type === 'add')
            <div class="kot-type kot-type--updated">UPDATED</div>
        @elseif($kot->type !== 'full')
            <div class="kot-type kot-type--void">{{ $kot->typeLabel() }}</div>
        @endif
    </div>

    <div class="meta">
        @if($isOrderCancelSlip)
            <p><strong>CANCELLED</strong> — void all items for this order</p>
        @elseif($isReprintSlip)
            <p><strong>REPRINT</strong> — same ticket printed again</p>
        @elseif($kot->type === 'add')
            <p><strong>UPDATED</strong> — new / changed items for this order</p>
        @endif
        <p><strong>{{ $order->order_number }}</strong></p>
        <p>Table: {{ $order->table?->name ?? '-' }}</p>
        <p>Date: {{ format_date($kot->created_at) }} {{ format_time($kot->created_at) }}</p>
        @if($order->waiter)
            <p>Waiter: {{ $order->waiter->name }}</p>
        @endif
        <p>Order Type: {{ ucwords(str_replace('_', ' ', $order->type)) }}</p>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Item Name</th>
                <th class="qty">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($kot->lines ?? [] as $line)
                @php
                    $line = is_array($line) ? $line : (array) $line;
                    $itemName = trim((string) ($line['item_name'] ?? $line['name'] ?? 'Item'));
                    $qty = (float) ($line['quantity'] ?? 0);
                @endphp
                <tr>
                    <td>
                        {{ $itemName !== '' ? $itemName : 'Item' }}
                        @if(!empty($line['variants_label']))
                            <div class="item-variant">{{ $line['variants_label'] }}</div>
                        @endif
                        @if(!empty($line['addons_label']))
                            <div class="item-variant">{{ $line['addons_label'] }}</div>
                        @endif
                        @if(!empty($line['special_instructions']))
                            <div class="item-note">Note: {{ $line['special_instructions'] }}</div>
                        @endif
                    </td>
                    <td class="qty">{{ format_quantity($qty) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($order->notes)
        <div class="meta" style="margin-top: 0.571em;">
            <p><strong>Order note:</strong> {{ $order->notes }}</p>
        </div>
    @endif

    <div class="footer">
        @if($isOrderCancelSlip)
            <p style="font-size: 0.929em; margin-bottom: 0.286em;">*** CANCELLED ***</p>
        @elseif($isReprintSlip)
            <p style="font-size: 0.929em; margin-bottom: 0.286em;">*** REPRINT ***</p>
        @endif
        {{ $company?->name ?? 'Kitchen' }}
    </div>

    @if(request()->boolean('print'))
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 400);
            });
        </script>
    @endif
</body>
</html>
