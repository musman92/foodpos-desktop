@php use App\Support\GrossMarginReport; @endphp
<div class="report-hub-panel space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Items</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($summary['item_count']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Avg. Margin</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $summary['avg_margin_percent'] !== null ? number_format($summary['avg_margin_percent'], 1).'%' : '—' }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Below Cost</p>
            <p class="mt-1 text-2xl font-bold {{ $summary['negative_margin_count'] > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ number_format($summary['negative_margin_count']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Stale Costs</p>
            <p class="mt-1 text-2xl font-bold {{ $summary['stale_cost_count'] > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ number_format($summary['stale_cost_count']) }}</p>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sale Price</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Margin</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Margin %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($rows as $row)
                        @php $item = $row['menu_item']; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $item->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row['category_name'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ format_currency($row['price']) }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ format_currency($row['cost']) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium {{ $row['margin'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ format_currency($row['margin']) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold">{{ $row['margin_percent'] !== null ? number_format($row['margin_percent'], 1).'%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No menu items match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 report-hub-pagination">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
