@extends('money-sources._layout', [
    'activeNav' => 'owner-withdrawal',
    'layoutHeading' => 'Owner withdrawal',
    'layoutSubtitle' => 'Record profit taken out of the business without affecting P&amp;L',
])

@section('money_sources_content')
<div class="max-w-2xl">
    @if(!$ownerBucket)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Owner Withdrawal source is not configured for this company. Run migrations or contact support.
        </div>
    @else
        <form action="{{ route('money-sources.owner-withdrawal.store') }}" method="POST" class="space-y-6" id="owner-withdrawal-form">
            @csrf

            @if(show_branch_ui())
        <div>
                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Branch <span class="text-red-500">*</span>
                </label>
                <select name="branch_id"
                        id="branch_id"
                        required
                        class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('branch_id') border-red-500 @enderror">
                    <option value="">Select branch</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ (int) old('branch_id', auth()->user()->branch_id) === $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
                @error('branch_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @else
            <input type="hidden" name="branch_id" value="{{ old('branch_id', auth()->user()->branch_id ?? current_branch_id()) }}">
        @endif

            <div>
                <label for="date" class="block text-sm font-medium text-gray-700 mb-2">
                    Withdrawal date <span class="text-red-500">*</span>
                </label>
                <input type="date"
                       name="date"
                       id="date"
                       required
                       value="{{ old('date', now()->toDateString()) }}"
                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('date') border-red-500 @enderror">
                @error('date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="from_money_source_id" class="block text-sm font-medium text-gray-700 mb-2">
                    From money source <span class="text-red-500">*</span>
                </label>
                <select name="from_money_source_id"
                        id="from_money_source_id"
                        required
                        class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('from_money_source_id') border-red-500 @enderror">
                    <option value="">Select source</option>
                    @foreach($operationalSources as $source)
                        <option value="{{ $source->id }}"
                                data-label="{{ $source->name }} ({{ $source->type }})"
                                {{ (int) old('from_money_source_id', $prefillFromId) === $source->id ? 'selected' : '' }}>
                            {{ $source->name }} ({{ $source->type }})
                        </option>
                    @endforeach
                </select>
                @error('from_money_source_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div id="source-balance-panel" class="mt-3 hidden rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm text-emerald-800">Available balance</p>
                            <p class="text-xs text-emerald-700 mt-1" id="source-balance-context"></p>
                        </div>
                        <p class="text-2xl font-bold text-emerald-900 tabular-nums" id="source-balance-amount">—</p>
                    </div>
                </div>
                <p id="source-balance-hint" class="mt-2 text-xs text-gray-500">
                    Select branch and money source to see the available balance.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">To (owner bucket)</label>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex items-center">
                        <i class="fas fa-user-tie text-amber-600 mr-3"></i>
                        <div>
                            <div class="font-medium text-gray-900">{{ $ownerBucket->name }}</div>
                            <div class="text-sm text-gray-500">System bucket — cumulative owner withdrawals</div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                    Amount <span class="text-red-500">*</span>
                </label>
                <input type="number"
                       name="amount"
                       id="amount"
                       step="0.01"
                       min="0.01"
                       required
                       value="{{ old('amount') }}"
                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-500 @enderror"
                       placeholder="0.00">
                @error('amount')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                    Notes (optional)
                </label>
                <textarea name="notes"
                          id="notes"
                          rows="3"
                          class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror"
                          placeholder="e.g. Monthly owner draw">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-900">
                <p class="font-semibold mb-1">Separate from expenses</p>
                <p>Owner withdrawals reduce the operational source balance and increase the Owner Withdrawal bucket. They are not recorded as transactions and do not affect profit &amp; loss.</p>
            </div>

            <div class="flex items-center justify-end pt-4 border-t border-gray-200">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700">
                    <i class="fas fa-hand-holding-usd mr-2"></i>
                    Record withdrawal
                </button>
            </div>
        </form>
    @endif
</div>

@if($ownerBucket)
<script>
const sourceBalances = {};
const sourceSelect = document.getElementById('from_money_source_id');
const branchSelect = document.getElementById('branch_id');
const dateInput = document.getElementById('date');
const balancePanel = document.getElementById('source-balance-panel');
const balanceAmount = document.getElementById('source-balance-amount');
const balanceContext = document.getElementById('source-balance-context');
const balanceHint = document.getElementById('source-balance-hint');

function updateSelectedSourceBalance() {
    const sourceId = sourceSelect.value;
    const branchId = branchSelect.value;
    const date = dateInput.value;

    if (!sourceId || !branchId || !date || !sourceBalances[sourceId]) {
        balancePanel.classList.add('hidden');
        balanceHint.classList.remove('hidden');
        if (branchId && date && !sourceId) {
            balanceHint.textContent = 'Select a money source to see the available balance.';
        } else if (!branchId || !date) {
            balanceHint.textContent = 'Select branch and money source to see the available balance.';
        }
        return;
    }

    const balance = sourceBalances[sourceId];
    const option = sourceSelect.options[sourceSelect.selectedIndex];
    const sourceName = option?.dataset.label || option?.textContent || 'Selected source';

    balanceAmount.textContent = balance.formatted;
    balanceContext.textContent = sourceName + ' · as of ' + date;
    balancePanel.classList.remove('hidden');
    balanceHint.classList.add('hidden');

    if (balance.amount <= 0) {
        balancePanel.className = 'mt-3 rounded-lg border border-red-200 bg-red-50 p-4';
        balanceAmount.className = 'text-2xl font-bold text-red-700 tabular-nums';
    } else {
        balancePanel.className = 'mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4';
        balanceAmount.className = 'text-2xl font-bold text-emerald-900 tabular-nums';
    }
}

function refreshSourceBalanceOptions() {
    const branchId = branchSelect.value;
    const date = dateInput.value;

    Object.keys(sourceBalances).forEach((key) => delete sourceBalances[key]);

    if (!branchId || !date) {
        Array.from(sourceSelect.options).forEach((option) => {
            if (!option.value) {
                return;
            }
            option.textContent = option.dataset.label || option.textContent;
        });
        updateSelectedSourceBalance();
        return;
    }

    const url = new URL(@json(route('money-sources.operational-balances')), window.location.origin);
    url.searchParams.set('branch_id', branchId);
    url.searchParams.set('date', date);

    fetch(url.toString(), {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then((response) => response.json())
        .then((data) => {
            Object.assign(sourceBalances, data.balances || {});

            Array.from(sourceSelect.options).forEach((option) => {
                if (!option.value) {
                    return;
                }

                const balance = sourceBalances[option.value];
                option.textContent = balance?.label || option.dataset.label || option.textContent;
            });

            updateSelectedSourceBalance();
        })
        .catch(() => {
            balanceHint.textContent = 'Unable to load balances. Please try again.';
            balanceHint.classList.remove('hidden');
        });
}

branchSelect.addEventListener('change', refreshSourceBalanceOptions);
dateInput.addEventListener('change', refreshSourceBalanceOptions);
sourceSelect.addEventListener('change', updateSelectedSourceBalance);

document.addEventListener('DOMContentLoaded', refreshSourceBalanceOptions);
</script>
@endif
@endsection
