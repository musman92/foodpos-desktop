@extends('layouts.app')

@section('title', 'Reconcile Money Source')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Reconcile Money Source</h1>
            <p class="mt-1 text-sm text-gray-500">Compare expected balance with actual count</p>
        </div>

        <form action="{{ route('money-sources.reconcile.process', $moneySource) }}" method="POST" class="p-6 space-y-6" id="reconcile-form">
            @csrf

            <!-- Money Source Info -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Money Source</label>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex items-center">
                        <i class="fas 
                            @if($moneySource->type === 'CASH') fa-money-bill-wave text-green-600
                            @elseif($moneySource->type === 'BANK') fa-university text-blue-600
                            @else fa-mobile-alt text-purple-600
                            @endif mr-3"></i>
                        <div>
                            <div class="font-medium text-gray-900">{{ $moneySource->name }}</div>
                            <div class="text-sm text-gray-500">{{ $moneySource->type }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Branch -->
            <div>
                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Branch <span class="text-red-500">*</span>
                </label>
                <select name="branch_id" 
                        id="branch_id" 
                        required
                        onchange="updateExpectedBalance()"
                        class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('branch_id') border-red-500 @enderror">
                    <option value="">Select branch</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ old('branch_id', auth()->user()->branch_id) == $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
                @error('branch_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Reconciliation Date -->
            <div>
                <label for="reconciliation_date" class="block text-sm font-medium text-gray-700 mb-2">
                    Reconciliation Date <span class="text-red-500">*</span>
                </label>
                <input type="date" 
                       name="reconciliation_date" 
                       id="reconciliation_date" 
                       required
                       onchange="updateExpectedBalance()"
                       value="{{ old('reconciliation_date', now()->toDateString()) }}"
                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('reconciliation_date') border-red-500 @enderror">
                @error('reconciliation_date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Expected Balance (Calculated) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Expected Balance</label>
                <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Based on transactions:</span>
                        <span class="text-xl font-bold text-blue-900" id="expected-balance">
                            {{ format_currency($moneySource->opening_balance) }}
                        </span>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    This is calculated from opening balance + all transactions up to the selected date.
                </p>
            </div>

            <!-- Actual Balance -->
            <div>
                <label for="actual_balance" class="block text-sm font-medium text-gray-700 mb-2">
                    Actual Balance <span class="text-red-500">*</span>
                </label>
                <input type="number" 
                       name="actual_balance" 
                       id="actual_balance" 
                       step="0.01"
                       required
                       value="{{ old('actual_balance') }}"
                       oninput="calculateDifference()"
                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('actual_balance') border-red-500 @enderror"
                       placeholder="0.00">
                @error('actual_balance')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Enter the actual physical count or bank statement balance.
                </p>
            </div>

            <!-- Difference (Calculated) -->
            <div id="difference-section" style="display: none;">
                <label class="block text-sm font-medium text-gray-700 mb-2">Difference</label>
                <div class="rounded-lg p-4 border-2" id="difference-box">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">Difference:</span>
                        <span class="text-xl font-bold" id="difference-amount">0.00</span>
                    </div>
                    <p class="mt-2 text-xs" id="difference-note"></p>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                    Notes (Optional)
                </label>
                <textarea name="notes" 
                          id="notes" 
                          rows="3"
                          class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror"
                          placeholder="Add any notes about discrepancies or adjustments">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                <a href="{{ route('money-sources.show', $moneySource) }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Cancel
                </a>
                <button type="submit" 
                        class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700">
                    <i class="fas fa-balance-scale mr-2"></i>
                    Complete Reconciliation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function updateExpectedBalance() {
    const branchId = document.getElementById('branch_id').value;
    const date = document.getElementById('reconciliation_date').value;
    
    if (!branchId || !date) {
        return;
    }
    
    // Fetch expected balance via AJAX
    const url = new URL('{{ route("money-sources.show", $moneySource) }}', window.location.origin);
    url.searchParams.set('branch_id', branchId);
    url.searchParams.set('as_of_date', date);
    url.searchParams.set('ajax', '1');
    
    fetch(url.toString())
        .then(response => response.json())
        .then(data => {
            if (data.expected_balance !== undefined) {
                document.getElementById('expected-balance').textContent = data.formatted_balance;
                calculateDifference();
            }
        })
        .catch(error => console.error('Error:', error));
}

function calculateDifference() {
    const expectedText = document.getElementById('expected-balance').textContent;
    const expected = parseFloat(expectedText.replace(/[^0-9.-]+/g, '')) || 0;
    const actual = parseFloat(document.getElementById('actual_balance').value) || 0;
    const difference = actual - expected;
    
    const diffSection = document.getElementById('difference-section');
    const diffBox = document.getElementById('difference-box');
    const diffAmount = document.getElementById('difference-amount');
    const diffNote = document.getElementById('difference-note');
    
    if (document.getElementById('actual_balance').value) {
        diffSection.style.display = 'block';
        
        if (Math.abs(difference) < 0.01) {
            diffBox.className = 'rounded-lg p-4 border-2 border-green-500 bg-green-50';
            diffAmount.className = 'text-xl font-bold text-green-600';
            diffAmount.textContent = '{{ get_currency_symbol(get_company_config()["currency"] ?? "USD") }}' + Math.abs(difference).toFixed(2);
            diffNote.textContent = 'Balances match perfectly!';
            diffNote.className = 'mt-2 text-xs text-green-600';
        } else if (difference > 0) {
            diffBox.className = 'rounded-lg p-4 border-2 border-blue-500 bg-blue-50';
            diffAmount.className = 'text-xl font-bold text-blue-600';
            diffAmount.textContent = '+{{ get_currency_symbol(get_company_config()["currency"] ?? "USD") }}' + Math.abs(difference).toFixed(2);
            diffNote.textContent = 'Surplus: Actual balance is higher than expected.';
            diffNote.className = 'mt-2 text-xs text-blue-600';
        } else {
            diffBox.className = 'rounded-lg p-4 border-2 border-red-500 bg-red-50';
            diffAmount.className = 'text-xl font-bold text-red-600';
            diffAmount.textContent = '-{{ get_currency_symbol(get_company_config()["currency"] ?? "USD") }}' + Math.abs(difference).toFixed(2);
            diffNote.textContent = 'Shortage: Actual balance is lower than expected.';
            diffNote.className = 'mt-2 text-xs text-red-600';
        }
    } else {
        diffSection.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateExpectedBalance();
});
</script>
@endsection
