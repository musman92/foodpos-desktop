@extends('money-sources._layout', [
    'activeNav' => 'reports',
    'layoutHeading' => 'Fund movement ledger',
    'layoutSubtitle' => 'Internal transfers and owner withdrawals between money sources',
])

@section('money_sources_content')
@php
    $filters = $ledger['filters'];
    $rows = $ledger['rows'];
    $summary = $ledger['summary'];
@endphp

<div class="space-y-6 min-w-0">
    <form method="GET" action="{{ route('money-sources.reports') }}" class="bg-gray-50 rounded-lg p-4 space-y-4 min-w-0">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <div>
                <label for="movement_kind" class="block text-sm font-medium text-gray-700 mb-1">Movement type</label>
                <select name="movement_kind" id="movement_kind" class="block w-full h-10 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="all" {{ $filters['movement_kind'] === 'all' ? 'selected' : '' }}>All</option>
                    <option value="internal_transfer" {{ $filters['movement_kind'] === 'internal_transfer' ? 'selected' : '' }}>Internal transfer</option>
                    <option value="owner_withdrawal" {{ $filters['movement_kind'] === 'owner_withdrawal' ? 'selected' : '' }}>Owner withdrawal</option>
                </select>
            </div>

            @if(show_branch_ui())
            <div>
                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                <select name="branch_id" id="branch_id" class="block w-full h-10 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All branches</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ (int) $filters['branch_id'] === $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <label for="from_money_source_id" class="block text-sm font-medium text-gray-700 mb-1">From source</label>
                <select name="from_money_source_id" id="from_money_source_id" class="block w-full h-10 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Any</option>
                    @foreach($operationalSources as $source)
                        <option value="{{ $source->id }}" {{ (int) $filters['from_money_source_id'] === $source->id ? 'selected' : '' }}>
                            {{ $source->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="to_money_source_id" class="block text-sm font-medium text-gray-700 mb-1">To source</label>
                <select name="to_money_source_id" id="to_money_source_id" class="block w-full h-10 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Any</option>
                    @foreach($operationalSources as $source)
                        <option value="{{ $source->id }}" {{ (int) $filters['to_money_source_id'] === $source->id ? 'selected' : '' }}>
                            {{ $source->name }}
                        </option>
                    @endforeach
                    @if($ownerBucket)
                        <option value="{{ $ownerBucket->id }}" {{ (int) $filters['to_money_source_id'] === $ownerBucket->id ? 'selected' : '' }}>
                            {{ $ownerBucket->name }}
                        </option>
                    @endif
                </select>
            </div>

            <div>
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From date</label>
                <input type="date" name="from" id="from" value="{{ $filters['from'] }}"
                       class="block w-full h-10 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To date</label>
                <input type="date" name="to" id="to" value="{{ $filters['to'] }}"
                       class="block w-full h-10 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                <i class="fas fa-filter mr-2"></i>
                Apply filters
            </button>
            <a href="{{ route('money-sources.reports') }}" class="text-sm text-gray-600 hover:text-gray-900">Clear</a>
        </div>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 min-w-0">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-sm text-gray-500">Internal transfers</p>
            <p class="text-2xl font-bold text-indigo-600 tabular-nums">{{ format_currency($summary['internal_total']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-sm text-gray-500">Owner withdrawals</p>
            <p class="text-2xl font-bold text-amber-600 tabular-nums">{{ format_currency($summary['owner_withdrawal_total']) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-sm text-gray-500">Movements shown</p>
            <p class="text-2xl font-bold text-gray-900 tabular-nums">{{ $summary['total'] }}</p>
        </div>
    </div>

    <div class="min-w-0 overflow-x-auto border border-gray-200 rounded-lg">
        <table class="listing-table w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">To</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $row['date']->format('M d, Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $row['movement_kind'] === 'owner_withdrawal' ? 'bg-amber-100 text-amber-800' : 'bg-indigo-100 text-indigo-800' }}">
                                {{ $row['movement_label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $row['from_name'] }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $row['to_name'] }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-right font-semibold tabular-nums">{{ format_currency($row['amount']) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600 max-w-[10rem] truncate" title="{{ $row['branch_name'] }}">{{ $row['branch_name'] }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-[8rem] truncate" title="{{ $row['notes'] ?? '' }}">{{ $row['notes'] ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $row['created_by'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            No fund movements match your filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
