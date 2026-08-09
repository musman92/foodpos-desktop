<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { font-size: 10px; color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $businessName }}</h1>
    <p class="meta"><strong>{{ $title }}</strong></p>
    @if($from && $to)
        <p class="meta">Period: {{ format_date($from) }} – {{ format_date($to) }}</p>
    @endif
    @if($branchLabel)
        <p class="meta">Branch: {{ $branchLabel }}</p>
    @endif
    <p class="meta">Generated: {{ format_datetime($generatedAt) }}</p>

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">No data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
