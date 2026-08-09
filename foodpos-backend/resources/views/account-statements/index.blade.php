@extends('layouts.app')

@section('title', 'Account Statement')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="accountStatementForm(@js([
    'type' => $type,
    'partyId' => $partyId,
    'partyLabel' => $partyLabel,
    'searchUrl' => route('account-statements.search'),
]))">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Account Statement</h1>
        <p class="mt-1 text-sm text-gray-500">
            Transaction history for customers, suppliers, and employees
            @if($branch)
                · <span class="font-medium text-gray-700">{{ $branch->name }}</span>
            @endif
        </p>
    </div>
    @if($branchError)
        <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            {{ $branchError }}
        </div>
    @endif

    <div class="bg-white shadow rounded-lg border border-gray-200 p-6">
        <form method="get" action="{{ route('account-statements.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-4 items-end">
            <div class="lg:col-span-2">
                <label for="type" class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                <select name="type"
                        id="type"
                        x-model="type"
                        @change="onTypeChange()"
                        class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="customer">Customer</option>
                    <option value="supplier">Supplier</option>
                    <option value="employee">Employee</option>
                </select>
            </div>

            <div class="md:col-span-2 lg:col-span-4">
                <label for="party_search" class="block text-sm font-medium text-gray-700 mb-2">
                    <span x-text="typeLabel"></span>
                </label>
                <input type="hidden" name="party_id" id="party_id" :value="partyId || ''">
                <div class="relative">
                    <input type="text"
                           id="party_search"
                           x-model="searchQuery"
                           @input.debounce.300ms="searchParties()"
                           @focus="dropdownOpen = true"
                           @blur="setTimeout(() => dropdownOpen = false, 200)"
                           :placeholder="'Search ' + typeLabel.toLowerCase() + ' by name, phone, or email…'"
                           autocomplete="off"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <div x-show="dropdownOpen && searchResults.length > 0"
                         x-cloak
                         class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto">
                        <template x-for="row in searchResults" :key="row.id">
                            <button type="button"
                                    @mousedown.prevent="selectParty(row)"
                                    class="w-full text-left px-4 py-2.5 text-sm text-gray-900 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                                <span x-text="row.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <label for="from" class="block text-sm font-medium text-gray-700 mb-2">From <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="date"
                       name="from"
                       id="from"
                       value="{{ $from }}"
                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="lg:col-span-2">
                <label for="to" class="block text-sm font-medium text-gray-700 mb-2">To <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="date"
                       name="to"
                       id="to"
                       value="{{ $to }}"
                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="lg:col-span-2">
                <button type="submit"
                        @click="if (!partyId) { $event.preventDefault(); alert('Please select a ' + typeLabel.toLowerCase() + '.'); }"
                        class="w-full inline-flex items-center justify-center h-12 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-file-invoice mr-2"></i>
                    View statement
                </button>
            </div>
        </form>
    </div>

    @if($statement && $party)
        @php
            $balanceColor = match (true) {
                abs((float) $partyBalance) < 0.009 => 'text-green-600',
                $type === 'customer' && (float) $partyBalance > 0 => 'text-amber-700',
                $type === 'employee' && (float) $partyBalance > 0 => 'text-green-700',
                (float) $partyBalance > 0 => 'text-red-600',
                default => 'text-red-700',
            };
        @endphp
        <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900">{{ $party->name }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ $typeLabel }} statement
                        @if($from || $to)
                            ·
                            @if($from && $to)
                                {{ format_date($from) }} – {{ format_date($to) }}
                            @elseif($from)
                                From {{ format_date($from) }}
                            @else
                                Until {{ format_date($to) }}
                            @endif
                        @else
                            · All dates
                        @endif
                    </p>
                </div>
                <div class="flex flex-col sm:items-end gap-2 shrink-0">
                    <a href="{{ route('account-statements.pdf', request()->only(['type', 'party_id', 'from', 'to'])) }}"
                       class="inline-flex items-center justify-center px-4 py-2 h-10 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-file-pdf mr-2 text-red-600"></i>
                        Export PDF
                    </a>
                    <div class="text-right">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ $type === 'employee' ? 'Balance' : 'Outstanding' }}</p>
                    <p class="text-xl font-bold {{ $balanceColor }}">
                        {{ format_currency(abs((float) $partyBalance)) }}
                        @if($type === 'employee' && abs((float) $partyBalance) >= 0.009)
                            <span class="text-sm font-medium">{{ (float) $partyBalance > 0 ? 'payable' : 'advance' }}</span>
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $partyBalanceHint }}
                    </p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment source</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Debit (DR)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit (CR)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($statement['lines'] as $line)
                            <tr @class(['bg-slate-50' => ($line['type'] ?? '') === 'opening_balance'])>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900">{{ format_date($line['date_display']) }}</td>
                                <td class="px-6 py-3 text-sm {{ ($line['type'] ?? '') === 'opening_balance' ? 'font-semibold text-gray-900' : 'text-gray-700' }}">{{ $line['label'] }}</td>
                                <td class="px-6 py-3 text-sm">
                                    @if($line['url'])
                                        <a href="{{ $line['url'] }}" class="text-indigo-600 hover:text-indigo-900 font-medium">{{ $line['reference'] }}</a>
                                    @else
                                        <span class="text-gray-900">{{ $line['reference'] }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">
                                    {{ $line['money_source'] ?? '—' }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right tabular-nums text-gray-900">
                                    {{ $line['debit'] > 0 ? format_currency($line['debit']) : '—' }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right tabular-nums text-gray-900">
                                    {{ $line['credit'] > 0 ? format_currency($line['credit']) : '—' }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-medium tabular-nums text-gray-900">
                                    {{ format_currency($line['balance']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                    No transactions found for this {{ strtolower($typeLabel) }} in the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($statement['lines']) > 0)
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td colspan="6" class="px-6 py-3 text-sm font-semibold text-gray-700 text-right">Closing balance (this branch, selected period)</td>
                                <td class="px-6 py-3 text-sm font-bold text-right tabular-nums text-indigo-700">{{ format_currency($statement['closing_balance']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @elseif($partyId && $branch && !$branchError)
        <div class="rounded-lg bg-gray-50 border border-gray-200 px-6 py-8 text-center text-sm text-gray-500">
            No statement data to display.
        </div>
    @endif
</div>

<script>
function accountStatementForm(initial) {
    return {
        type: initial.type || 'customer',
        partyId: initial.partyId || null,
        partyLabel: initial.partyLabel || '',
        searchQuery: initial.partyLabel || '',
        searchResults: [],
        dropdownOpen: false,
        searchUrl: initial.searchUrl,
        get typeLabel() {
            if (this.type === 'supplier') return 'Supplier';
            if (this.type === 'employee') return 'Employee';
            return 'Customer';
        },

        init() {
            if (this.partyLabel) {
                this.searchQuery = this.partyLabel;
            }
        },

        onTypeChange() {
            this.partyId = null;
            this.partyLabel = '';
            this.searchQuery = '';
            this.searchResults = [];
        },

        async searchParties() {
            const q = (this.searchQuery || '').trim();
            if (q.length < 2) {
                this.searchResults = [];
                return;
            }
            try {
                const params = new URLSearchParams({ type: this.type, q });
                const res = await fetch(this.searchUrl + '?' + params.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                this.searchResults = await res.json();
                this.dropdownOpen = true;
            } catch (e) {
                this.searchResults = [];
            }
        },

        selectParty(row) {
            this.partyId = row.id;
            this.partyLabel = row.name;
            this.searchQuery = row.label;
            this.searchResults = [];
            this.dropdownOpen = false;
        },
    };
}
</script>
@endsection
