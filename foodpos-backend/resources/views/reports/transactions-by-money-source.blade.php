@extends('layouts.app')

@section('title', 'Transactions by Money Source')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to Reports</a>
        <h1 class="text-2xl font-bold text-gray-900">Transactions by Money Source</h1>
        <p class="mt-1 text-sm text-gray-500">All money movements (in &amp; out) for the selected period, filtered by source.</p>
    </div>

    <form method="get" action="{{ route('reports.transactions-by-money-source') }}" class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
        @php
            $moneySourceOptions = $moneySources->map(fn ($s) => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
            ])->values();
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 items-end">
            @if($availableBranches->isNotEmpty())
                <div class="min-w-0">
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                    <select name="branch_id" id="branch_id" class="block w-full filter-control pr-8">
                        @if(show_branch_ui() && $availableBranches->count() > 1)
                            <option value="">All branches</option>
                        @endif
                        @foreach($availableBranches as $b)
                            <option value="{{ $b->id }}" {{ (string) request('branch_id', optional($selectedBranch)->id) === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="min-w-0 sm:col-span-2 lg:col-span-1 xl:col-span-1"
                 x-data="moneySourceMultiSelect(@js($moneySourceOptions), @js($moneySourceIds))"
                 @keydown.escape.window="open = false"
                 @click.outside="open = false">
                <label class="block text-sm font-medium text-gray-700 mb-1">Money sources</label>
                <div class="relative">
                    <button type="button"
                            @click="open = !open"
                            class="filter-control w-full flex items-center justify-between gap-2 text-left bg-white">
                        <span class="truncate text-sm" x-text="summaryLabel"></span>
                        <i class="fas fa-chevron-down text-gray-400 text-xs shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                    </button>

                    <div x-show="open"
                         x-cloak
                         x-transition
                         class="absolute z-30 mt-1 w-full min-w-[16rem] rounded-lg border border-gray-200 bg-white shadow-lg overflow-hidden">
                        <div class="p-2 border-b border-gray-100">
                            <input type="search"
                                   x-model="query"
                                   placeholder="Search sources…"
                                   class="block w-full h-9 px-3 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-100 bg-gray-50">
                            <button type="button" @click="selectAll()" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Select all</button>
                            <button type="button" @click="clearAll()" class="text-xs font-medium text-gray-500 hover:text-gray-700">Clear</button>
                        </div>
                        <div class="max-h-56 overflow-y-auto py-1">
                            <template x-for="source in filtered" :key="source.id">
                                <label class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox"
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           :value="source.id"
                                           :checked="selected.includes(source.id)"
                                           @change="toggle(source.id)">
                                    <span x-text="source.name"></span>
                                </label>
                            </template>
                            <p x-show="filtered.length === 0" class="px-3 py-3 text-sm text-gray-500">No sources match.</p>
                        </div>
                    </div>
                </div>
                <template x-for="id in selected" :key="'ms-'+id">
                    <input type="hidden" name="money_source_ids[]" :value="id">
                </template>
            </div>

            <div class="min-w-0">
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type" id="type" class="block w-full filter-control pr-8">
                    <option value="" {{ $type === null ? 'selected' : '' }}>In &amp; Out</option>
                    <option value="in" {{ $type === 'in' ? 'selected' : '' }}>In only</option>
                    <option value="out" {{ $type === 'out' ? 'selected' : '' }}>Out only</option>
                </select>
            </div>
            <div class="min-w-0">
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" name="from" id="from" value="{{ $from }}" class="block w-full filter-control">
            </div>
            <div class="min-w-0">
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" name="to" id="to" value="{{ $to }}" class="block w-full filter-control">
            </div>
            <div class="min-w-0">
                <label class="block text-sm font-medium text-transparent mb-1 select-none" aria-hidden="true">Apply</label>
                <button type="submit" class="inline-flex w-full items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-sync-alt mr-2"></i>Apply
                </button>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

    @if($bySource->isNotEmpty() && count($moneySourceIds) !== 1)
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-900">Totals by money source</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Money source</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">In</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Out</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Count</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($bySource as $sourceRow)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 whitespace-nowrap">{{ $sourceRow['money_source'] }}</td>
                                <td class="px-4 py-3 text-sm text-right text-emerald-700 tabular-nums">{{ format_currency($sourceRow['total_in']) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-rose-700 tabular-nums">{{ format_currency($sourceRow['total_out']) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium tabular-nums {{ $sourceRow['net'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ format_currency($sourceRow['net']) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-700 tabular-nums">{{ number_format($sourceRow['count']) }}</td>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($rows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $row['date'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">{{ $row['money_source'] }}</td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                @if($row['type'] === 'in')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">In</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800">Out</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium tabular-nums {{ $row['type'] === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ format_currency($row['amount']) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $row['reference_label'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $row['account'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $row['branch'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $row['created_by'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate" title="{{ $row['notes'] }}">{{ $row['notes'] !== '' ? $row['notes'] : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">No transactions in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>

<script>
function moneySourceMultiSelect(options, selectedIds) {
    const normalizedOptions = (Array.isArray(options) ? options : []).map((source) => ({
        id: Number(source.id),
        name: String(source.name || ''),
    }));
    const initialSelected = (Array.isArray(selectedIds) ? selectedIds : [])
        .map((id) => Number(id))
        .filter((id) => id > 0);

    return {
        open: false,
        query: '',
        options: normalizedOptions,
        selected: initialSelected,

        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (!q) {
                return this.options;
            }

            return this.options.filter((source) => source.name.toLowerCase().includes(q));
        },

        get summaryLabel() {
            if (this.selected.length === 0) {
                return 'All sources';
            }

            if (this.selected.length === 1) {
                const match = this.options.find((source) => source.id === this.selected[0]);

                return match ? match.name : '1 selected';
            }

            return this.selected.length + ' sources selected';
        },

        toggle(id) {
            const value = Number(id);
            if (this.selected.includes(value)) {
                this.selected = this.selected.filter((item) => item !== value);
            } else {
                this.selected = [...this.selected, value];
            }
        },

        selectAll() {
            this.selected = this.options.map((source) => source.id);
        },

        clearAll() {
            this.selected = [];
        },
    };
}
</script>
@endsection
