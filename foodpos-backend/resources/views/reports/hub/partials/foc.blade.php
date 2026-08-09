<div class="report-hub-panel space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">FOC orders</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['order_count']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Total FOC value</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['total_value'], 2) }}</p>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($rows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $row['date'] }}</td>
                            <td class="px-4 py-3 text-sm font-medium"><a href="{{ route('order-management.show', $row['id']) }}" class="text-indigo-600 hover:text-indigo-800">{{ $row['order_number'] }}</a></td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row['branch'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row['type_label'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row['customer'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">{{ number_format($row['total_amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No FOC orders in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
