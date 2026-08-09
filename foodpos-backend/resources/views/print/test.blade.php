<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Print — {{ $printer->title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body {
            font-size: 13px;
            line-height: 1.45;
            color: #000;
            background: #fff;
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto;
            padding: 4mm;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .title { font-size: 18px; font-weight: 700; }
        .sub { font-size: 12px; margin-top: 4px; }
        .row { margin-bottom: 6px; }
        .label { font-size: 11px; color: #444; text-transform: uppercase; letter-spacing: 0.04em; }
        .value { font-size: 14px; font-weight: 700; word-break: break-word; }
        .ok-box {
            margin-top: 12px;
            padding: 10px;
            border: 2px solid #000;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
        }
        .footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px dashed #000;
            font-size: 11px;
            text-align: center;
        }
        .no-print {
            margin: 16px auto;
            max-width: 80mm;
            text-align: center;
        }
        .no-print button {
            padding: 10px 18px;
            font-size: 14px;
            cursor: pointer;
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">TEST PRINT</div>
        <div class="sub">{{ $companyName }}</div>
    </div>

    <div class="row">
        <div class="label">Branch</div>
        <div class="value">{{ $branchName }}</div>
    </div>
    <div class="row">
        <div class="label">Printer</div>
        <div class="value">{{ $printer->title }}</div>
    </div>
    <div class="row">
        <div class="label">Role</div>
        <div class="value">{{ ucfirst($printer->role) }}</div>
    </div>
    @if($printer->device_name)
        <div class="row">
            <div class="label">OS printer</div>
            <div class="value">{{ $printer->device_name }}</div>
        </div>
    @endif
    <div class="row">
        <div class="label">Printed at</div>
        <div class="value">{{ format_datetime(now()) }}</div>
    </div>

    <div class="ok-box">If you can read this, printing works.</div>

    <div class="footer">FoodPOS printer connectivity test</div>

    <div class="no-print">
        <button type="button" onclick="window.print()">Print again</button>
    </div>

    @if($autoPrint)
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 400);
        });
    </script>
    @endif
</body>
</html>
