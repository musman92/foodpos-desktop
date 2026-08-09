<div class="report-hub-panel">
    @if($showReport && $summary)
        @php
            $typeRows = \App\Support\OrderHistoryReport::typeRowsForDisplay($summary);
            $totalCountLabel = \App\Support\OrderHistoryReport::orderCountLabel((int) $summary['order_count']);
        @endphp
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Orders</h2>
                <p class="text-sm text-gray-600 mt-0.5">{{ $period['period_label'] }}</p>
            </div>
            <div class="flex flex-wrap items-stretch gap-2 px-4 py-2 border-b border-gray-100 bg-gray-50/80">
                @foreach($typeRows as $typeRow)
                    <div class="inline-flex flex-col min-w-[7rem] px-2.5 py-1.5 rounded-lg border border-gray-200 bg-white shadow-sm">
                        <span class="text-[10px] font-semibold uppercase text-gray-500">{{ $typeRow['label'] }}</span>
                        <span class="text-xs font-bold tabular-nums">{{ $typeRow['count_label'] }}</span>
                        <span class="text-xs font-bold text-indigo-700 tabular-nums">{{ format_currency($typeRow['amount']) }}</span>
                    </div>
                @endforeach
                <div class="inline-flex flex-col min-w-[7rem] px-2.5 py-1.5 rounded-lg border-2 border-indigo-200 bg-indigo-50 shadow-sm">
                    <span class="text-[10px] font-semibold uppercase text-indigo-700">Total</span>
                    <span class="text-xs font-bold tabular-nums">{{ $totalCountLabel }}</span>
                    <span class="text-xs font-bold text-indigo-700 tabular-nums">{{ format_currency($summary['total_amount']) }}</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($orders as $order)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium">{{ $order->order_number }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::formatOrderDate($order) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::typeLabel($order->type) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::customerDisplayName($order) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium">{{ format_currency($order->total_amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No orders match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 report-hub-pagination">{{ $orders->links() }}</div>
            @endif
        </div>
    @else
        @include('reports.hub.partials._empty')
    @endif
</div>
