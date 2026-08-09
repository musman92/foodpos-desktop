@extends('money-sources._layout', [
    'activeNav' => 'sources',
    'layoutHeading' => 'Money Sources',
    'layoutSubtitle' => 'Manage your payment sources (Cash, Bank, App)',
])

@section('money_sources_content')
@php
    $columnCount = isset($selectedBranchId) ? 8 : 7;
@endphp
<div class="space-y-6 min-w-0">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <p class="text-xs text-gray-400">
                <i class="fas fa-info-circle mr-1"></i>
                Operational sources are used for POS, shifts, and payments. Balances include transactions and fund movements.
            </p>
        </div>
        <a href="{{ route('money-sources.create') }}"
           class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
            <i class="fas fa-plus mr-2"></i>
            Add Money Source
        </a>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200 min-w-0">
        @include('partials.listing-per-page-bar', [
            'action' => route('money-sources.index'),
            'paginator' => $moneySources,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Type</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Opening balance</th>
                        @if(isset($selectedBranchId))
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Current balance</th>
                        @endif
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Branches</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($moneySources as $moneySource)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $moneySources->firstItem() + $loop->index }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                <span class="font-medium">{{ $moneySource->name }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                    @if($moneySource->type === 'CASH') bg-green-100 text-green-800
                                    @elseif($moneySource->type === 'BANK') bg-blue-100 text-blue-800
                                    @else bg-purple-100 text-purple-800
                                    @endif">
                                    {{ $moneySource->type }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">
                                {{ format_currency($moneySource->opening_balance) }}
                            </td>
                            @if(isset($selectedBranchId))
                                <td class="px-3 py-3 whitespace-nowrap text-right tabular-nums {{ isset($balances[$moneySource->id]) && $balances[$moneySource->id] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ isset($balances[$moneySource->id]) ? format_currency($balances[$moneySource->id]) : '—' }}
                                </td>
                            @endif
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $moneySource->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $moneySource->active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $moneySource->branches->count() }} {{ Str::plural('branch', $moneySource->branches->count()) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('money-sources.show', $moneySource) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('money-sources.edit', $moneySource) }}"
                                       class="text-blue-600 hover:text-blue-800"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('money-sources.destroy', $moneySource) }}"
                                          method="POST"
                                          class="inline"
                                          onsubmit="return confirm('Are you sure you want to delete this money source?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-600 hover:text-red-800"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $columnCount }}" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-wallet text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No money sources found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new money source.</p>
                                    <a href="{{ route('money-sources.create') }}"
                                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Money Source
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $moneySources])
    </div>

    @if(isset($systemSources) && $systemSources->isNotEmpty())
        <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200 min-w-0">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">System buckets</h2>
                <p class="mt-1 text-sm text-gray-500">Not used for POS or payments — track cumulative owner withdrawals</p>
            </div>
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            @if(isset($selectedBranchId))
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total withdrawn</th>
                            @endif
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($systemSources as $systemSource)
                            <tr>
                                <td class="px-3 py-3 font-medium text-gray-900">{{ $systemSource->name }}</td>
                                <td class="px-3 py-3">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">{{ $systemSource->type }}</span>
                                </td>
                                @if(isset($selectedBranchId))
                                    <td class="px-3 py-3 text-right font-semibold text-amber-700 tabular-nums">
                                        {{ isset($balances[$systemSource->id]) ? format_currency($balances[$systemSource->id]) : '—' }}
                                    </td>
                                @endif
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('money-sources.show', $systemSource) }}" class="text-indigo-600 hover:text-indigo-800" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
