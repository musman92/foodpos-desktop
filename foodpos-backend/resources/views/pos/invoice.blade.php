<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - {{ $order->order_number }}</title>
    @php
        $receiptLayout = receipt_layout_settings();
    @endphp
    @include('pos._receipt-styles', ['receiptLayout' => $receiptLayout])
    <style>
        .print-actions {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: #f5f5f5;
            font-weight: 400;
            line-height: 1.4;
        }

        .print-actions * {
            font-weight: 400;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #333;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            margin: 0 5px;
        }

        .btn:hover {
            background-color: #555;
        }
    </style>
</head>
<body>
    <div class="print-actions no-print">
        <button onclick="window.print()" class="btn">Print Invoice</button>
        <button onclick="window.close()" class="btn" style="background-color: #666;">Close</button>
    </div>

    <div class="receipt">
        @include('pos._receipt-body', [
            'order' => $order,
            'receiptLayout' => $receiptLayout,
            'sections' => $receiptLayout['sections'],
        ])
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
