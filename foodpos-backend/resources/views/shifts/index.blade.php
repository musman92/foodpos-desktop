@extends('layouts.app')

@section('title', 'Shifts')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Shifts</h1>
            <p class="mt-1 text-sm text-gray-500">Each cashier has their own shift. Multiple active shifts per branch are supported.</p>
        </div>
        <a href="{{ route('shifts.create') }}"
           class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
            <i class="fas fa-plus mr-2"></i>
            Start New Shift
        </a>
    </div>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('shifts.index'),
            'paginator' => $shifts,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Branch</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Opened By</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Opened At</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Closed By</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Cash Difference</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($shifts as $shift)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $shifts->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">{{ $shift->branch->name }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ format_date($shift->shift_date) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">{{ $shift->openedBy->name }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ $shift->opened_at->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ $shift->closedBy?->name ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if($shift->status === 'active')
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Closed</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right tabular-nums">
                                @if($shift->status === 'closed')
                                    <span class="font-medium {{ $shift->cash_difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ format_currency($shift->cash_difference) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('shifts.show', $shift) }}" class="text-indigo-600 hover:text-indigo-800" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('shifts.z-report', $shift) }}" class="text-indigo-600 hover:text-indigo-800" title="Z Report">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                    @if($shift->status === 'active')
                                        <a href="{{ route('shifts.edit', $shift) }}" class="text-red-600 hover:text-red-800" title="Close shift">
                                            <i class="fas fa-times-circle"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-clock text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No shifts found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Start your first shift to begin tracking.</p>
                                    <a href="{{ route('shifts.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Start New Shift
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $shifts])
    </div>
</div>
@endsection
