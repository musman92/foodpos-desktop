<div class="report-hub-panel space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Transactions</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['count']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Total in</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1">{{ format_currency($summary['total_in']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Total out</p>
            <p class="text-2xl font-bold text-rose-700 mt-1">{{ format_currency($summary['total_out']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Net</p>
            <p class="text-2xl font-bold mt-1 {{ $summary['net'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ format_currency($summary['net']) }}</p>
        </div>
    </div>
    @if($bySource->isNotEmpty() && count($moneySourceIds ?? []) !== 1)
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200"><h2 class="text-sm font-semibold text-gray-900">Totals by money source</h2></div>
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Money source</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">In</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Out</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($bySource as $sourceRow)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium">{{ $sourceRow['money_source'] }}</td>
                                <td class="px-4 py-3 text-sm text-right text-emerald-700">{{ format_currency($sourceRow['total_in']) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-rose-700">{{ format_currency($sourceRow['total_out']) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium">{{ format_currency($sourceRow['net']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Money source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($rows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $row['date'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $row['money_source'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $row['type'] === 'in' ? 'In' : 'Out' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">{{ format_currency($row['amount']) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row['reference_label'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row['branch'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No transactions in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 report-hub-pagination">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
