@php
    $exportParams = $exportParams ?? array_filter([
        'branch_id' => optional($selectedBranch)->id,
        'from' => $from,
        'to' => $to,
        'search' => ($search ?? '') !== '' ? $search : null,
        'category_id' => ! empty($categoryId) ? $categoryId : null,
        'menu_item_id' => ! empty($menuItemId) ? $menuItemId : null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<div class="report-hub-panel space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Total consumption value</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($summary['total_cost'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">From sales</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['sales_cost'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">From adjustments</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['adjustment_cost'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Items</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['item_count']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Consumption detail</h2>
        </div>
        @if($rows->isEmpty())
            <p class="p-6 text-sm text-gray-500">No inventory consumption recorded for this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty used</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Remaining stock</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg unit cost</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total cost</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sales cost</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Adjustment cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($rows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $row['item_type_label'] }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <a href="{{ route('reports.consumption.detail', array_merge($exportParams, [
                                        'itemType' => $row['item_type'],
                                        'itemId' => $row['item_id'],
                                    ])) }}"
                                       class="text-indigo-700 hover:text-indigo-900 hover:underline">
                                        {{ $row['name'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $row['code'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $row['category'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right tabular-nums whitespace-nowrap">
                                    {{ number_format($row['quantity'], 2) }}
                                    @if(!empty($row['quantity_unit']))
                                        <span class="text-gray-500 font-normal">{{ $row['quantity_unit'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right tabular-nums whitespace-nowrap">
                                    {{ number_format($row['remaining_stock'] ?? 0, 2) }}
                                    @if(!empty($row['remaining_stock_unit']))
                                        <span class="text-gray-500 font-normal">{{ $row['remaining_stock_unit'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ number_format($row['avg_unit_cost'], 2) }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">{{ number_format($row['total_cost'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format($row['sales_cost'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format($row['adjustment_cost'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="7" class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-indigo-600 text-right">{{ number_format($summary['total_cost'], 2) }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">{{ number_format($summary['sales_cost'], 2) }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">{{ number_format($summary['adjustment_cost'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
