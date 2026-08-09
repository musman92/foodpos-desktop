@extends('money-sources._layout', [
    'activeNav' => 'transfer',
    'layoutHeading' => 'Transfer between sources',
    'layoutSubtitle' => 'Move funds between operational money sources (Cash, Bank, App)',
])

@section('money_sources_content')
<div class="max-w-2xl">
    <form action="{{ route('money-sources.transfer.store') }}" method="POST" class="space-y-6" id="transfer-form">
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
                Transfer date <span class="text-red-500">*</span>
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

            <div id="from-balance-panel" class="mt-3 hidden rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-emerald-800">From source balance</p>
                        <p class="text-xs text-emerald-700 mt-1" id="from-balance-context"></p>
                    </div>
                    <p class="text-2xl font-bold text-emerald-900 tabular-nums" id="from-balance-amount">—</p>
                </div>
            </div>
        </div>

        <div>
            <label for="to_money_source_id" class="block text-sm font-medium text-gray-700 mb-2">
                To money source <span class="text-red-500">*</span>
            </label>
            <select name="to_money_source_id"
                    id="to_money_source_id"
                    required
                    class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('to_money_source_id') border-red-500 @enderror">
                <option value="">Select destination</option>
                @foreach($operationalSources as $source)
                    <option value="{{ $source->id }}"
                            data-label="{{ $source->name }} ({{ $source->type }})"
                            {{ (int) old('to_money_source_id') === $source->id ? 'selected' : '' }}>
                        {{ $source->name }} ({{ $source->type }})
                    </option>
                @endforeach
            </select>
            @error('to_money_source_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div id="to-balance-panel" class="mt-3 hidden rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-indigo-800">To source balance</p>
                        <p class="text-xs text-indigo-700 mt-1" id="to-balance-context"></p>
                    </div>
                    <p class="text-2xl font-bold text-indigo-900 tabular-nums" id="to-balance-amount">—</p>
                </div>
            </div>
            <p id="balance-hint" class="mt-2 text-xs text-gray-500">
                Select branch, date, and money sources to see balances.
            </p>
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
                      placeholder="Add any notes about this transfer">{{ old('notes') }}</textarea>
            @error('notes')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
            <p class="font-semibold mb-1">Not an expense</p>
            <p>Internal transfers move money between sources. They do not appear in profit &amp; loss or expense reports.</p>
        </div>

        <div class="flex items-center justify-end pt-4 border-t border-gray-200">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                <i class="fas fa-exchange-alt mr-2"></i>
                Transfer money
            </button>
        </div>
    </form>
</div>

<script>
const sourceBalances = {};
const fromSelect = document.getElementById('from_money_source_id');
const toSelect = document.getElementById('to_money_source_id');
const branchSelect = document.getElementById('branch_id');
const dateInput = document.getElementById('date');
const balanceHint = document.getElementById('balance-hint');

function applyBalancePanel(selectEl, panelId, amountId, contextId, title, positiveClass, negativeClass) {
    const sourceId = selectEl.value;
    const branchId = branchSelect.value;
    const date = dateInput.value;
    const panel = document.getElementById(panelId);
    const amountEl = document.getElementById(amountId);
    const contextEl = document.getElementById(contextId);

    if (!sourceId || !branchId || !date || !sourceBalances[sourceId]) {
        panel.classList.add('hidden');
        return false;
    }

    const balance = sourceBalances[sourceId];
    const option = selectEl.options[selectEl.selectedIndex];
    const sourceName = option?.dataset.label || option?.textContent || 'Selected source';

    amountEl.textContent = balance.formatted;
    contextEl.textContent = sourceName + ' · as of ' + date;
    panel.classList.remove('hidden');

    if (balance.amount <= 0) {
        panel.className = 'mt-3 rounded-lg border p-4 ' + negativeClass;
        amountEl.className = 'text-2xl font-bold text-red-700 tabular-nums';
    } else {
        panel.className = 'mt-3 rounded-lg border p-4 ' + positiveClass;
        amountEl.className = 'text-2xl font-bold tabular-nums ' + (panelId === 'to-balance-panel' ? 'text-indigo-900' : 'text-emerald-900');
    }

    return true;
}

function syncDestinationOptions() {
    const fromId = fromSelect.value;

    Array.from(toSelect.options).forEach((option) => {
        if (!option.value) {
            return;
        }

        option.hidden = option.value === fromId;
        option.disabled = option.value === fromId;

        if (option.value === fromId && toSelect.value === fromId) {
            toSelect.value = '';
        }
    });
}

function updateBalancePanels() {
    const fromVisible = applyBalancePanel(
        fromSelect,
        'from-balance-panel',
        'from-balance-amount',
        'from-balance-context',
        'From source balance',
        'border-emerald-200 bg-emerald-50',
        'border-red-200 bg-red-50'
    );

    const toVisible = applyBalancePanel(
        toSelect,
        'to-balance-panel',
        'to-balance-amount',
        'to-balance-context',
        'To source balance',
        'border-indigo-200 bg-indigo-50',
        'border-red-200 bg-red-50'
    );

    if (fromVisible || toVisible) {
        balanceHint.classList.add('hidden');
    } else {
        balanceHint.classList.remove('hidden');
        if (branchSelect.value && dateInput.value) {
            balanceHint.textContent = 'Select from and to money sources to see balances.';
        } else {
            balanceHint.textContent = 'Select branch, date, and money sources to see balances.';
        }
    }
}

function refreshSourceBalanceOptions() {
    const branchId = branchSelect.value;
    const date = dateInput.value;

    Object.keys(sourceBalances).forEach((key) => delete sourceBalances[key]);

    const resetOptionLabels = (selectEl) => {
        Array.from(selectEl.options).forEach((option) => {
            if (!option.value) {
                return;
            }
            option.textContent = option.dataset.label || option.textContent;
        });
    };

    if (!branchId || !date) {
        resetOptionLabels(fromSelect);
        resetOptionLabels(toSelect);
        syncDestinationOptions();
        updateBalancePanels();
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

            [fromSelect, toSelect].forEach((selectEl) => {
                Array.from(selectEl.options).forEach((option) => {
                    if (!option.value) {
                        return;
                    }

                    const balance = sourceBalances[option.value];
                    option.textContent = balance?.label || option.dataset.label || option.textContent;
                });
            });

            syncDestinationOptions();
            updateBalancePanels();
        })
        .catch(() => {
            balanceHint.textContent = 'Unable to load balances. Please try again.';
            balanceHint.classList.remove('hidden');
        });
}

branchSelect.addEventListener('change', refreshSourceBalanceOptions);
dateInput.addEventListener('change', refreshSourceBalanceOptions);
fromSelect.addEventListener('change', () => {
    syncDestinationOptions();
    updateBalancePanels();
});
toSelect.addEventListener('change', updateBalancePanels);

document.addEventListener('DOMContentLoaded', refreshSourceBalanceOptions);
</script>
@endsection
