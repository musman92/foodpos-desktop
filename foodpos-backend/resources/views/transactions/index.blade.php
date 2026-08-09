@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Transactions</h1>
            <p class="mt-1 text-sm text-gray-500">View and manage all financial transactions</p>
        </div>
        <a href="{{ route('transactions.create') }}"
           class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
            <i class="fas fa-plus mr-2"></i>
            Add Transaction
        </a>
    </div>

    <div class="bg-white shadow rounded-lg p-4 sm:p-6">
        <form method="GET" action="{{ route('transactions.index') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label for="from" class="block text-xs font-medium text-gray-500 mb-1">Start date</label>
                <input type="date"
                       name="from"
                       id="from"
                       value="{{ request('from') }}"
                       class="block w-full filter-control min-w-[150px]">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-gray-500 mb-1">End date</label>
                <input type="date"
                       name="to"
                       id="to"
                       value="{{ request('to') }}"
                       class="block w-full filter-control min-w-[150px]">
            </div>
            <div>
                <label for="type" class="block text-xs font-medium text-gray-500 mb-1">Type</label>
                <select name="type" id="type" class="block w-full filter-control min-w-[120px]">
                    <option value="">All</option>
                    <option value="in" {{ request('type') === 'in' ? 'selected' : '' }}>In</option>
                    <option value="out" {{ request('type') === 'out' ? 'selected' : '' }}>Out</option>
                </select>
            </div>
            <div>
                <label for="account_id" class="block text-xs font-medium text-gray-500 mb-1">Account</label>
                <select name="account_id" id="account_id" class="block w-full filter-control min-w-[180px]">
                    <option value="">All accounts</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>
                            {{ $account->name }} ({{ $account->type }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="reference" class="block text-xs font-medium text-gray-500 mb-1">Reference</label>
                <select name="reference" id="reference" class="block w-full filter-control min-w-[140px]">
                    <option value="">All</option>
                    @foreach($referenceTypes as $ref)
                        <option value="{{ $ref }}" {{ request('reference') === $ref ? 'selected' : '' }}>
                            {{ ucfirst($ref) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 border border-transparent rounded-lg font-medium text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <i class="fas fa-filter mr-2"></i>
                    Filter
                </button>
                <a href="{{ route('transactions.index') }}" class="inline-flex items-center justify-center h-11 px-4 bg-gray-100 border border-gray-300 rounded-lg font-medium text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('transactions.index'),
            'paginator' => $transactions,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Account</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Type</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Amount</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Payment method</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Reference</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($transactions as $transaction)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $transactions->firstItem() + $loop->index }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ format_date($transaction->date) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                <span class="font-medium">{{ $transaction->account->name ?? '—' }}</span>
                                @if($transaction->account?->type)
                                    <span class="text-gray-500 text-xs ml-1">({{ $transaction->account->type }})</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $transaction->type === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ strtoupper($transaction->type) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right tabular-nums {{ $transaction->type === 'in' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $transaction->type === 'in' ? '+' : '-' }}{{ format_currency($transaction->amount) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ ucfirst($transaction->payment_method) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                @if($transaction->reference_type)
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                        {{ ucfirst($transaction->reference_type) }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('transactions.show', $transaction) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if($transaction->canBeModifiedBy(auth()->user()) && auth()->user()->hasAppPermission('transactions.update'))
                                        <a href="{{ route('transactions.edit', $transaction) }}"
                                           class="text-gray-600 hover:text-gray-900"
                                           title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    @endif
                                    @if($transaction->canBeModifiedBy(auth()->user()) && auth()->user()->hasAppPermission('transactions.destroy'))
                                        <form action="{{ route('transactions.destroy', $transaction) }}"
                                              method="POST"
                                              class="inline"
                                              onsubmit="return confirm('Delete this manually entered transaction? This will change the related account and money-source balances.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                                        <a href="{{ route('transactions.adjustment.create', $transaction) }}"
                                           class="text-orange-600 hover:text-orange-800"
                                           title="Create Adjustment">
                                            <i class="fas fa-adjust"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-exchange-alt text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No transactions found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new transaction.</p>
                                    <a href="{{ route('transactions.create') }}"
                                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Transaction
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $transactions])
    </div>
</div>
@endsection
