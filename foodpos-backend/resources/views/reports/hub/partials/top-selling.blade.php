<div class="report-hub-panel">
    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($items as $i => $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-500 align-top">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 align-top">
                                {{ $row->item_name }}
                                @if($row->deal_id)<span class="ml-1 text-xs font-medium text-amber-600">Deal</span>@endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 align-top">{{ number_format($row->total_quantity, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 align-top">{{ number_format($row->total_revenue, 2) }}</td>
                        </tr>
                        @foreach($row->variants ?? [] as $variant)
                            <tr class="hover:bg-gray-50 bg-gray-50/60">
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2 text-sm text-gray-600 pl-10"><span class="text-gray-400 mr-1">└</span>{{ $variant->label }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-600">{{ number_format($variant->total_quantity, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-600">{{ number_format($variant->total_revenue, 2) }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No orders in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
