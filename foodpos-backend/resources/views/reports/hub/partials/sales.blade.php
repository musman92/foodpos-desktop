<div class="report-hub-panel space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Total Revenue</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ format_currency($totalRevenue) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Orders</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($orderCount) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Avg Order Value</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ format_currency($avgOrderValue) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Discounts (total)</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ format_currency($discountAmount) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="font-semibold text-gray-900">Sales by category</h2>
            <p class="mt-0.5 text-sm text-gray-500">
                {{ format_date($from) }} – {{ format_date($to) }}
                @if($selectedBranch)
                    · {{ $selectedBranch->name }}
                @elseif($availableBranches->count() > 1)
                    · All branches
                @endif
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty sold</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sales</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($categoryRows as $row)
                        <tr class="{{ $selectedCategoryId && $row['category_id'] === $selectedCategoryId ? 'bg-indigo-50' : '' }}">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['category_label'] }}</td>
                            <td class="px-4 py-3 text-sm text-right tabular-nums">{{ number_format($row['order_count']) }}</td>
                            <td class="px-4 py-3 text-sm text-right tabular-nums">{{ number_format($row['quantity'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right tabular-nums font-medium">{{ format_currency($row['sales']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">No category sales for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($selectedCategory && $categorySummary)
        @include('reports.partials.sales-by-item-results', [
            'error' => null,
            'summary' => $categorySummary,
            'orders' => $categoryOrders,
            'selectionLabel' => $selectedCategory->displayLabel().' (all items)',
            'from' => $from,
            'to' => $to,
            'selectedBranch' => $selectedBranch,
            'availableBranches' => $availableBranches,
        ])
    @endif
</div>
