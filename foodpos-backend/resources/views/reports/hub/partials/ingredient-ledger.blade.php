@php
    $ingredient = $ledger['ingredient'] ?? null;
    $summary = $ledger['summary'] ?? null;
    $opening = $ledger['opening'] ?? null;
@endphp
<div class="report-hub-panel space-y-6">
    @if(! $ledger)
        @include('reports.hub.partials._empty', ['message' => 'Select an ingredient and date range to view the ledger.'])
    @else
        <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
            <h2 class="text-xl font-semibold text-gray-900">{{ $ingredient['name'] }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ format_date($from, $branchId) }} – {{ format_date($to, $branchId) }}</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase text-gray-500">Opening</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($opening['qty'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase text-gray-500">Purchased</p>
                <p class="mt-1 text-2xl font-bold text-green-700 tabular-nums">+{{ number_format($summary['purchased_qty'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase text-gray-500">Returned</p>
                <p class="mt-1 text-2xl font-bold text-orange-700 tabular-nums">−{{ number_format($summary['returned_qty'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase text-gray-500">Sold</p>
                <p class="mt-1 text-2xl font-bold text-red-700 tabular-nums">−{{ number_format($summary['sold_qty'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase text-gray-500">Current stock</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700 tabular-nums">{{ number_format($summary['current_qty'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase text-gray-500">Events</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($summary['event_count']) }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">When</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty change</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($ledger['rows'] as $row)
                            <tr>
                                <td class="px-4 py-3 text-sm">{{ $row['occurred_at'] ? format_datetime($row['occurred_at'], $branchId) : '—' }}</td>
                                <td class="px-4 py-3 text-sm">{{ $row['kind_label'] }}</td>
                                <td class="px-4 py-3 text-sm">{{ $row['reference_label'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-sm text-right tabular-nums">{{ ($row['signed_qty'] >= 0 ? '+' : '').number_format($row['signed_qty'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right tabular-nums font-semibold">{{ number_format($row['balance_qty'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
