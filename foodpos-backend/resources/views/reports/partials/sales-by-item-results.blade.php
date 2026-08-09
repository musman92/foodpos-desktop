@if($error)
    <div class="p-4 rounded-lg border border-red-200 bg-red-50 text-sm text-red-700">{{ $error }}</div>
@elseif($summary)
    <div class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Matching orders</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($summary['order_count']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Quantity sold</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($summary['matched_quantity'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Matched sales</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700">{{ format_currency($summary['matched_sales']) }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="font-semibold text-gray-900">{{ $selectionLabel }} orders</h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    {{ format_date($from) }} – {{ format_date($to) }}
                    @if($selectedBranch) · {{ $selectedBranch->name }} @elseif($availableBranches->count() > 1) · All branches @endif
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Matched qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Matched sales</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Order total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($orders as $order)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('order-management.show', $order) }}" class="text-indigo-700 hover:text-indigo-900">{{ $order->order_number }}</a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::formatOrderDate($order) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::typeLabel($order->type) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::customerDisplayName($order) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right tabular-nums">{{ number_format((float) $order->matched_quantity, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-right tabular-nums">{{ format_currency($order->matched_sales) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right tabular-nums">{{ format_currency($order->total_amount) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">No matching orders were found for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 sales-by-item-pagination">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
@endif
