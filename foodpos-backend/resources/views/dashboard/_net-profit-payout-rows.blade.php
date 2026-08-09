@php
    $rows = $rows ?? [];
    $subtotal = (float) ($subtotal ?? 0);
    $subtotalLabel = $subtotalLabel ?? 'Total';
@endphp
<div class="rounded-xl border border-gray-200 overflow-hidden">
    <div class="divide-y divide-gray-100">
        @foreach($rows as $row)
            <div class="px-4 py-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ $row['label'] }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $row['date'] }}
                        @if(!empty($row['account']))
                            · {{ $row['account'] }}
                        @endif
                        @if(!empty($row['detail']))
                            · {{ $row['detail'] }}
                        @endif
                    </p>
                </div>
                <span class="shrink-0 text-sm font-semibold text-gray-900 tabular-nums">{{ format_currency((float) $row['amount']) }}</span>
            </div>
        @endforeach
    </div>
    <div class="flex items-center justify-between gap-3 px-4 py-3 bg-gray-50 border-t border-gray-200">
        <span class="text-sm font-medium text-gray-700">{{ $subtotalLabel }}</span>
        <span class="text-sm font-semibold text-gray-900 tabular-nums">{{ format_currency($subtotal) }}</span>
    </div>
</div>
