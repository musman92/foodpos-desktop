@php
    $receiptLayout = $receiptLayout ?? receipt_layout_settings();
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
        font-family: Arial, Helvetica, sans-serif !important;
        font-weight: 700;
    }

    html {
        width: {{ $receiptPaperMm }}mm;
        max-width: {{ $receiptPaperMm }}mm;
        margin: 0 auto;
    }

    body {
        font-family: Arial, Helvetica, sans-serif !important;
        font-size: {{ $receiptBasePx }}px;
        font-weight: 700;
        line-height: 1.35;
        color: #000;
        background: #fff;
        width: {{ $receiptPaperMm }}mm;
        max-width: {{ $receiptPaperMm }}mm;
        margin: 0 auto;
        padding: 1.5mm {{ $receiptPadRightMm }}mm 1.5mm {{ $receiptPadLeftMm }}mm;
    }

    .receipt {
        width: 100%;
        max-width: {{ $receiptPaperMm }}mm;
    }

    .invoice-header {
        text-align: center;
        margin-bottom: 8px;
        border-bottom: 1px dashed #000;
        padding-bottom: 8px;
    }

    .invoice-logo {
        text-align: center;
        margin-bottom: 6px;
    }

    .invoice-logo img {
        max-height: 3.5em;
        max-width: 11em;
        width: auto;
        height: auto;
        object-fit: contain;
        vertical-align: middle;
    }

    .invoice-logo img.invoice-logo--filtered {
        filter: grayscale(100%) contrast(180%) brightness(0.85);
    }

    .company-name {
        font-size: 1.15em;
        margin-bottom: 0.2em;
        text-transform: uppercase;
    }

    .company-info {
        font-size: 0.9em;
        margin: 0.2em 0;
        line-height: 1.35;
    }

    .item-meta {
        font-size: 0.88em;
        color: #000;
        margin-top: 2px;
        line-height: 1.3;
    }

    .item-meta--note {
        font-style: normal;
    }

    .invoice-title {
        font-size: 1.05em;
        margin: 0.5em 0 0.35em 0;
        text-align: center;
    }

    .invoice-draft {
        font-size: 0.85em;
        text-align: center;
        margin-bottom: 6px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .invoice-meta {
        text-align: center;
        font-size: 0.9em;
        line-height: 1.35;
        margin-bottom: 8px;
        border-bottom: 1px dashed #000;
        padding-bottom: 8px;
    }

    .invoice-meta p {
        margin: 2px 0;
    }

    .meta-sep {
        margin: 0 0.25em;
    }

    .section {
        margin: 0.5em 0;
        font-size: 0.9em;
        line-height: 1.35;
    }

    .section-title {
        margin-bottom: 2px;
        text-transform: uppercase;
        border-bottom: 1px solid #000;
        padding-bottom: 2px;
    }

    .section-content {
        margin: 2px 0;
    }

    .section-content p {
        margin: 2px 0;
        line-height: 1.35;
    }

    .items-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        margin: 0.5em 0;
        font-size: 0.9em;
        line-height: 1.3;
    }

    .items-table th {
        text-align: left;
        padding: 3px 0;
        border-bottom: 1px dashed #000;
    }

    .items-table td {
        padding: 3px 0;
        border-bottom: 1px dotted #000;
        vertical-align: top;
    }

    .items-table th:first-child,
    .items-table td:first-child {
        width: {{ $receiptColItemPct }}%;
        padding-right: 3px;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .items-table tr:last-child td {
        border-bottom: 1px solid #000;
    }

    .item-name {
        line-height: 1.3;
    }

    .item-qty {
        text-align: center;
        width: {{ $receiptColQtyPct }}%;
        white-space: nowrap;
        padding-left: 2px;
        padding-right: 2px;
    }

    .item-price {
        text-align: right;
        width: {{ $receiptColPricePct }}%;
        white-space: nowrap;
        overflow: visible;
        font-variant-numeric: tabular-nums;
        padding-right: {{ max(2, $receiptAmountPadRightMm) }}mm;
    }

    .amount-cell {
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }

    .totals {
        margin-top: 6px;
        border-top: 1px dashed #000;
        padding-top: 4px;
        padding-right: {{ max(2, $receiptAmountPadRightMm) }}mm;
        box-sizing: border-box;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 0.35em;
        margin: 0.2em 0;
        font-size: 0.9em;
        line-height: 1.35;
    }

    .total-row span:last-child {
        flex-shrink: 0;
        white-space: nowrap;
        text-align: right;
        overflow: visible;
    }

    .total-row.grand-total {
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        padding: 0.35em 0;
        margin: 0.35em 0;
        font-size: 1.1em;
        line-height: 1.3;
    }

    .separator {
        border-top: 1px dashed #000;
        margin: 6px 0;
    }

    .footer {
        text-align: center;
        margin-top: 0.75em;
        padding-top: 0.5em;
        border-top: 1px dashed #000;
        font-size: 0.88em;
        line-height: 1.35;
    }

    .footer p {
        margin: 0.25em 0;
    }

    .footer-brand-block {
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px dashed #ccc;
    }

    .footer-brand {
        font-size: 0.85em;
        color: #000;
        margin-top: 2px;
        line-height: 1.3;
    }

    .payment-info {
        margin-top: 6px;
        padding: 4px 0;
        line-height: 1.35;
        font-size: 0.9em;
        text-align: center;
    }

    .payment-info p {
        margin: 2px 0;
    }

    .payment-split-line {
        font-size: 0.88em;
    }

    @media print {
        @page {
            size: {{ $receiptPaperMm }}mm auto;
            margin: 0;
        }

        html, body {
            width: {{ $receiptPaperMm }}mm !important;
            max-width: {{ $receiptPaperMm }}mm !important;
            min-width: {{ $receiptPaperMm }}mm !important;
            height: auto !important;
            margin: 0 !important;
            padding: 1.5mm {{ $receiptPadRightMm }}mm 1.5mm {{ $receiptPadLeftMm }}mm !important;
            font-size: {{ $receiptBasePx }}px !important;
            font-weight: 700 !important;
            line-height: 1.35 !important;
            color: #000 !important;
            background: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .receipt {
            width: 100% !important;
            max-width: {{ $receiptPaperMm }}mm !important;
        }

        *, *::before, *::after {
            font-family: Arial, Helvetica, sans-serif !important;
            font-weight: 700 !important;
            line-height: 1.35 !important;
            color: #000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .no-print {
            display: none !important;
        }
    }
</style>
