@extends('layouts.app')

@section('title', 'Z Report')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Z Report</h1>
        <p class="mt-1 text-sm text-gray-500">End-of-shift sales, payments, and cash drawer reconciliation. Select a shift below.</p>
    </div>

    @include('reports._filters', ['formUrl' => route('reports.z-report')])

    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('reports.z-report', request()->only(['branch_id', 'from', 'to'])),
            'paginator' => $shifts,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Branch</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Shift Date</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Cashier</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Opened</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Closed</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Cash Diff.</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Actions</th>
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
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ $shift->closed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if($shift->status === 'active')
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Active</span>
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
                                    <a href="{{ route('shifts.z-report', $shift) }}" class="text-indigo-600 hover:text-indigo-800" title="View Z Report">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('shifts.z-report.pdf', $shift) }}" class="text-indigo-600 hover:text-indigo-800" title="Download PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-file-invoice text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No shifts found</h3>
                                    <p class="text-sm text-gray-500">Try a wider date range or a different branch.</p>
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
