@extends('layouts.app')

@section('title', 'Create Supplier Payment')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Create Supplier Payment</h1>
            <p class="mt-1 text-sm text-gray-500">Record a payment to a supplier for unpaid purchases</p>
        </div>

        <form action="{{ route('supplier-payments.store') }}" method="POST" class="p-6 space-y-6" id="paymentForm">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Supplier Selection -->
                <div>
                    <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Supplier <span class="text-red-500">*</span>
                    </label>
                    <select name="supplier_id" 
                            id="supplier_id" 
                            required
                            onchange="loadUnpaidPurchases(this.value)"
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('supplier_id') border-red-500 @enderror">
                        <option value="">Select a supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ old('supplier_id', $selectedSupplierId) == $supplier->id ? 'selected' : '' }}>
                                {{ $supplier->displayLabel() }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Branch (Optional) -->
                @if(show_branch_ui())
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Branch <span class="text-xs text-gray-500">(Optional - for company-wide purchases)</span>
                    </label>
                    <select name="branch_id" 
                            id="branch_id" 
                            onchange="loadUnpaidPurchases()"
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('branch_id') border-red-500 @enderror">
                        <option value="">All Branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ old('branch_id', $selectedBranchId ?? auth()->user()->branch_id) == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('branch_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                @else
                    <input type="hidden" name="branch_id" value="{{ old('branch_id', $selectedBranchId ?? auth()->user()->branch_id) }}">
                @endif

                <!-- Payment Date -->
                <div>
                    <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Payment Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" 
                           name="payment_date" 
                           id="payment_date" 
                           value="{{ old('payment_date', date('Y-m-d')) }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('payment_date') border-red-500 @enderror">
                    @error('payment_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Account -->
                <div>
                    <label for="account_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Account <span class="text-red-500">*</span>
                    </label>
                    <select name="account_id" 
                            id="account_id" 
                            required
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('account_id') border-red-500 @enderror">
                        <option value="">Select an account</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }} ({{ ucfirst($account->type) }})
                            </option>
                        @endforeach
                    </select>
                    @error('account_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Payment source -->
                <div>
                    <label for="money_source_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Payment source <span class="text-red-500">*</span>
                    </label>
                    <select name="money_source_id"
                            id="money_source_id"
                            required
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('money_source_id') border-red-500 @enderror">
                        <option value="">Select…</option>
                        @foreach($moneySources as $source)
                            <option value="{{ $source->id }}" {{ (string) old('money_source_id') === (string) $source->id ? 'selected' : '' }}>
                                {{ $source->name }} ({{ $source->type }})
                            </option>
                        @endforeach
                    </select>
                    @error('money_source_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Unpaid Purchases Section -->
            @if($selectedSupplierId && $unpaidPurchases->count() > 0)
            <div class="border-t border-gray-200 pt-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Unpaid Purchases</h3>
                    <p class="text-sm text-gray-500">Total Pending: <span class="font-semibold text-red-600">{{ format_currency($totalPending) }}</span></p>
                </div>

                <!-- Total Amount Option -->
                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <label for="total_amount" class="block text-sm font-medium text-gray-700 mb-2">
                        Total Payment Amount (will pay oldest purchases first)
                    </label>
                    <input type="number" 
                           name="total_amount" 
                           id="total_amount" 
                           value="{{ old('total_amount', '') }}"
                           step="0.01"
                           min="0"
                           max="{{ $totalPending }}"
                           placeholder="Enter total amount to pay"
                           oninput="autoFillPurchaseAmounts()"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('total_amount') border-red-500 @enderror">
                    @error('total_amount')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Maximum: {{ format_currency($totalPending) }}</p>
                </div>

                <!-- Individual Purchase Amounts -->
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Or enter individual amounts:</h4>
                    <div class="overflow-x-auto">
                        <table class="listing-table min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase #</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pending</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Amount</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($unpaidPurchases as $purchase)
                                    @php
                                        $pendingAmount = max(0, $purchase->total_amount - ($purchase->paid_amount ?? 0));
                                    @endphp
                                    <tr class="hover:bg-gray-50" data-purchase-date="{{ $purchase->purchase_date->format('Y-m-d') }}" data-purchase-id="{{ $purchase->id }}">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $purchase->purchase_number }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            {{ format_date($purchase->purchase_date) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            {{ format_currency($purchase->total_amount) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            {{ format_currency($purchase->paid_amount ?? 0) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-red-600">
                                            {{ format_currency($pendingAmount) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div>
                                                <input type="number" 
                                                       name="purchase_amounts[{{ $purchase->id }}]" 
                                                       id="purchase_amount_{{ $purchase->id }}"
                                                       value="{{ old("purchase_amounts.{$purchase->id}", '') }}"
                                                       step="0.01"
                                                       min="0"
                                                       max="{{ $pendingAmount }}"
                                                       placeholder="0.00"
                                                       data-pending-amount="{{ $pendingAmount }}"
                                                       oninput="clearTotalAmount()"
                                                       class="w-32 h-10 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm purchase-amount-input @error("purchase_amounts.{$purchase->id}") border-red-500 @enderror">
                                                @error("purchase_amounts.{$purchase->id}")
                                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @elseif($selectedSupplierId && $unpaidPurchases->count() == 0)
            <div class="border-t border-gray-200 pt-6">
                <div class="p-4 bg-yellow-50 rounded-lg">
                    <p class="text-sm text-yellow-800">No unpaid purchases found for this supplier.</p>
                </div>
            </div>
            @endif

            @if($errors->has('purchase_amounts') && !$errors->hasAny(['purchase_amounts.*', 'total_amount']))
            <div class="border-t border-gray-200 pt-6">
                <div class="p-4 bg-red-50 rounded-lg">
                    <p class="text-sm text-red-800">{{ $errors->first('purchase_amounts') }}</p>
                </div>
            </div>
            @endif

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                    Notes
                </label>
                <textarea name="notes" 
                          id="notes" 
                          rows="3"
                          class="block w-full px-4 py-2 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                <a href="{{ route('supplier-payments.index') }}" 
                   class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function loadUnpaidPurchases(supplierId = null) {
    const supplierSelect = document.getElementById('supplier_id');
    const branchSelect = document.getElementById('branch_id');
    const selectedSupplierId = supplierId || supplierSelect.value;
    const selectedBranchId = branchSelect.value;
    
    if (selectedSupplierId) {
        let url = '{{ route("supplier-payments.create") }}?supplier_id=' + selectedSupplierId;
        if (selectedBranchId) {
            url += '&branch_id=' + selectedBranchId;
        }
        window.location.href = url;
    }
}

function clearTotalAmount() {
    document.getElementById('total_amount').value = '';
}

function autoFillPurchaseAmounts() {
    const totalAmountInput = document.getElementById('total_amount');
    const totalAmount = parseFloat(totalAmountInput.value) || 0;
    
    if (totalAmount <= 0) {
        // Clear all purchase amounts if total is empty or zero
        clearIndividualAmounts();
        return;
    }
    
    // Get all purchase rows
    const purchaseRows = document.querySelectorAll('tr[data-purchase-date]');
    const purchases = [];
    
    purchaseRows.forEach(row => {
        const input = row.querySelector('.purchase-amount-input');
        if (!input) return;
        
        const purchaseDate = row.getAttribute('data-purchase-date');
        const pendingAmount = parseFloat(input.getAttribute('data-pending-amount')) || 0;
        const purchaseId = row.getAttribute('data-purchase-id');
        
        purchases.push({
            id: purchaseId,
            input: input,
            pendingAmount: pendingAmount,
            date: purchaseDate
        });
    });
    
    // Sort by date (oldest first), then by ID for consistency
    purchases.sort((a, b) => {
        const dateCompare = a.date.localeCompare(b.date);
        if (dateCompare !== 0) return dateCompare;
        return parseInt(a.id) - parseInt(b.id);
    });
    
    // Clear all first
    clearIndividualAmounts();
    
    // Distribute amount starting from oldest purchases
    let remainingAmount = totalAmount;
    let hasDistributed = false;
    
    for (const purchase of purchases) {
        if (remainingAmount <= 0) {
            break;
        }
        
        const paymentAmount = Math.min(remainingAmount, purchase.pendingAmount);
        if (paymentAmount > 0) {
            purchase.input.value = paymentAmount.toFixed(2);
            remainingAmount -= paymentAmount;
            hasDistributed = true;
        }
    }
    
    // If there's remaining amount, show a warning
    if (remainingAmount > 0.01) {
        const distributedAmount = (totalAmount - remainingAmount).toFixed(2);
        alert('Warning: The total amount (' + totalAmount.toFixed(2) + ') exceeds the total pending amount. Only ' + distributedAmount + ' has been distributed to the oldest purchases.');
    } else if (!hasDistributed && totalAmount > 0) {
        alert('Warning: The total amount cannot be distributed. Please check the pending amounts.');
    }
}

function clearIndividualAmounts() {
    const inputs = document.querySelectorAll('input[name^="purchase_amounts"]');
    inputs.forEach(input => input.value = '');
}

// Update supplier select to also reload on change
document.addEventListener('DOMContentLoaded', function() {
    const supplierSelect = document.getElementById('supplier_id');
    if (supplierSelect) {
        supplierSelect.addEventListener('change', function() {
            loadUnpaidPurchases(this.value);
        });
    }
});
</script>
@endsection

