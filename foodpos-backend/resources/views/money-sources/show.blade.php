@extends('money-sources._layout', [
    'activeNav' => 'sources',
    'layoutHeading' => $moneySource->name,
    'layoutSubtitle' => $moneySource->isOwnerWithdrawalBucket() ? 'Owner withdrawal bucket' : 'Money source details & history',
])

@section('money_sources_content')
@php
    $isOwnerBucket = $moneySource->isOwnerWithdrawalBucket();
    $historyCount = $isOwnerBucket
        ? (method_exists($fundMovements, 'total') ? $fundMovements->total() : $fundMovements->count())
        : (method_exists($transactions, 'total') ? $transactions->total() : $transactions->count());
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-end flex-wrap gap-3">
        @unless($isOwnerBucket)
            <a href="{{ route('money-sources.transfer.create', ['from' => $moneySource->id]) }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                <i class="fas fa-exchange-alt mr-2"></i>
                Transfer
            </a>
            <a href="{{ route('money-sources.reconcile', $moneySource) }}"
               class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700">
                <i class="fas fa-balance-scale mr-2"></i>
                Reconcile
            </a>
            <a href="{{ route('money-sources.edit', $moneySource) }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-edit mr-2"></i>
                Edit
            </a>
        @endunless
        <a href="{{ route('money-sources.index') }}"
           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i>
            Back
        </a>
    </div>

    @if(show_branch_ui() && $availableBranches && $availableBranches->count() > 0)
        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <label class="text-sm font-medium text-gray-700">View balance for branch:</label>
                    <select onchange="window.location.href='{{ route('money-sources.show', $moneySource) }}?branch_id=' + this.value"
                            class="rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All branches</option>
                        @foreach($availableBranches as $branch)
                            <option value="{{ $branch->id }}" {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if($currentBalance !== null)
                    <div class="text-right">
                        <p class="text-sm text-gray-500">{{ $isOwnerBucket ? 'Total withdrawn' : 'Current balance' }}</p>
                        <p class="text-3xl font-bold {{ $isOwnerBucket ? 'text-amber-600' : ($currentBalance >= 0 ? 'text-green-600' : 'text-red-600') }}">
                            {{ format_currency($currentBalance) }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r
            @if($isOwnerBucket) from-amber-50 to-orange-50
            @elseif($moneySource->type === 'CASH') from-green-50 to-emerald-50
            @elseif($moneySource->type === 'BANK') from-blue-50 to-indigo-50
            @else from-purple-50 to-pink-50
            @endif">
            <div class="flex items-center">
                <div class="h-14 w-14 rounded-full bg-gradient-to-br
                    @if($isOwnerBucket) from-amber-400 to-orange-500
                    @elseif($moneySource->type === 'CASH') from-green-400 to-emerald-500
                    @elseif($moneySource->type === 'BANK') from-blue-400 to-indigo-500
                    @else from-purple-400 to-pink-500
                    @endif flex items-center justify-center">
                    <i class="fas
                        @if($isOwnerBucket) fa-user-tie
                        @elseif($moneySource->type === 'CASH') fa-money-bill-wave
                        @elseif($moneySource->type === 'BANK') fa-university
                        @else fa-mobile-alt
                        @endif text-white text-xl"></i>
                </div>
                <div class="ml-4">
                    <h2 class="text-xl font-bold text-gray-900">{{ $moneySource->name }}</h2>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                            @if($isOwnerBucket) bg-amber-100 text-amber-800
                            @elseif($moneySource->type === 'CASH') bg-green-100 text-green-800
                            @elseif($moneySource->type === 'BANK') bg-blue-100 text-blue-800
                            @else bg-purple-100 text-purple-800
                            @endif">
                            {{ $moneySource->type }}
                        </span>
                        @unless($isOwnerBucket)
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $moneySource->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $moneySource->active ? 'Active' : 'Inactive' }}
                            </span>
                        @endunless
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-6">
            <dl class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @unless($isOwnerBucket)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Opening balance</dt>
                        <dd class="mt-1 text-lg font-semibold text-gray-900">{{ format_currency($moneySource->opening_balance) }}</dd>
                    </div>
                @endunless
                @if($currentBalance !== null)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ $isOwnerBucket ? 'Total withdrawn' : 'Current balance' }}</dt>
                        <dd class="mt-1 text-lg font-semibold {{ $isOwnerBucket ? 'text-amber-600' : ($currentBalance >= 0 ? 'text-green-600' : 'text-red-600') }}">
                            {{ format_currency($currentBalance) }}
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ $isOwnerBucket ? 'Withdrawals' : 'Transactions' }}</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $historyCount }}</dd>
                </div>
            </dl>

            @if(count($branchBalances) > 0)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Balances by branch</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($branchBalances as $branchBalance)
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">{{ $branchBalance['branch']->name }}</span>
                                    <span class="text-sm font-semibold {{ $isOwnerBucket ? 'text-amber-600' : ($branchBalance['balance'] >= 0 ? 'text-green-600' : 'text-red-600') }}">
                                        {{ format_currency($branchBalance['balance']) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($isOwnerBucket)
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Withdrawal history</h2>
            </div>
            <div class="overflow-x-auto">
                @if($fundMovements->count() > 0)
                    <table class="listing-table min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($fundMovements as $movement)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $movement->movement_date->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $movement->fromMoneySource?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right text-amber-700">+{{ format_currency($movement->amount) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $movement->branch?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $movement->notes ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $movement->creator?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if(method_exists($fundMovements, 'hasPages') && $fundMovements->hasPages())
                        <div class="bg-white px-4 py-3 border-t border-gray-200">{{ $fundMovements->links() }}</div>
                    @endif
                @else
                    <div class="text-center py-12 text-gray-500">No owner withdrawals recorded yet.</div>
                @endif
            </div>
        </div>
    @else
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Transaction history</h2>
            </div>
            <div class="overflow-x-auto">
                @if($transactions->count() > 0)
                    <table class="listing-table min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @php $runningBalance = $moneySource->opening_balance; @endphp
                            @foreach($transactions as $transaction)
                                @php
                                    if ($transaction->type === 'in') {
                                        $runningBalance += $transaction->amount;
                                    } else {
                                        $runningBalance -= $transaction->amount;
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $transaction->date->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $transaction->account->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $transaction->type === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ strtoupper($transaction->type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ ucfirst(str_replace('_', ' ', $transaction->payment_method)) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($transaction->reference_type)
                                            {{ ucfirst($transaction->reference_type) }}@if($transaction->ref_id) #{{ $transaction->ref_id }}@endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right {{ $transaction->type === 'in' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $transaction->type === 'in' ? '+' : '-' }}{{ format_currency($transaction->amount) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $transaction->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if(method_exists($transactions, 'hasPages') && $transactions->hasPages())
                        <div class="bg-white px-4 py-3 border-t border-gray-200">{{ $transactions->links() }}</div>
                    @endif
                @else
                    <div class="text-center py-12 text-gray-500">No transactions found for this money source.</div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
