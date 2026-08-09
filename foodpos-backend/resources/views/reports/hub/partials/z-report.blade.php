<div class="report-hub-panel">
    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shift Date</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cashier</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Opened</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Closed</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cash Diff.</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($shifts as $shift)
                        <tr>
                            <td class="px-3 py-3 text-gray-500">{{ $shifts->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 font-medium text-gray-900">{{ $shift->branch->name }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ format_date($shift->shift_date) }}</td>
                            <td class="px-3 py-3 text-gray-900">{{ $shift->openedBy->name }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $shift->opened_at->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $shift->closed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-3 py-3">
                                @if($shift->status === 'active')
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Active</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Closed</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right tabular-nums">
                                @if($shift->status === 'closed')
                                    <span class="font-medium {{ $shift->cash_difference >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ format_currency($shift->cash_difference) }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('shifts.z-report', $shift) }}" class="text-indigo-600 hover:text-indigo-800 mr-2" title="View"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('shifts.z-report.pdf', $shift) }}" class="text-indigo-600 hover:text-indigo-800" title="PDF"><i class="fas fa-file-pdf"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-12 text-center text-gray-500">No shifts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($shifts->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 report-hub-pagination">{{ $shifts->links() }}</div>
        @endif
    </div>
</div>
