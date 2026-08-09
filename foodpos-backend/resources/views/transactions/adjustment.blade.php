@extends('layouts.app')

@section('title', 'Create Adjustment Transaction')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Original Transaction Info -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2 text-yellow-600"></i>
            Original Transaction
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Date</p>
                <p class="font-medium text-gray-900">{{ format_date($transaction->date) }}</p>
            </div>
            <div>
                <p class="text-gray-500">Account</p>
                <p class="font-medium text-gray-900">{{ $transaction->account->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Type</p>
                <p class="font-medium">
                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $transaction->type === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ strtoupper($transaction->type) }}
                    </span>
                </p>
            </div>
            <div>
                <p class="text-gray-500">Amount</p>
                <p class="font-semibold {{ $transaction->type === 'in' ? 'text-green-600' : 'text-red-600' }}">
                    {{ $transaction->type === 'in' ? '+' : '-' }}{{ format_currency($transaction->amount) }}
                </p>
            </div>
        </div>
    </div>

    <!-- Adjustment Form -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Create Adjustment Transaction</h1>
            <p class="mt-1 text-sm text-gray-500">Create a new transaction to adjust the original transaction. This will create a separate transaction entry.</p>
        </div>

        <form action="{{ route('transactions.adjustment.store', $transaction) }}" method="POST" class="p-6 space-y-6" x-data="transactionForm()">
            @csrf

            <!-- Transaction Information -->
            <div class="space-y-6">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Adjustment Details</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Account -->
                    <div>
                        <label for="account_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Account <span class="text-red-500">*</span>
                        </label>
                        <select name="account_id" 
                                id="account_id" 
                                x-model="formData.account_id"
                                required
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('account_id') border-red-500 @enderror">
                            <option value="">Select Account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" 
                                        {{ $transaction->account_id == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }} ({{ ucfirst($account->type) }})
                                </option>
                            @endforeach
                        </select>
                        @error('account_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Branch -->
                    @if(count($branches) > 1)
                    <div>
                        <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Branch
                        </label>
                        <select name="branch_id" 
                                id="branch_id" 
                                x-model="formData.branch_id"
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('branch_id') border-red-500 @enderror">
                            <option value="">All Branches</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" 
                                        {{ $transaction->branch_id == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    <!-- Type -->
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                            Type <span class="text-red-500">*</span>
                        </label>
                        <select name="type" 
                                id="type" 
                                x-model="formData.type"
                                required
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('type') border-red-500 @enderror">
                            <option value="">Select Type</option>
                            <option value="in">In (Income)</option>
                            <option value="out">Out (Expense)</option>
                        </select>
                        @error('type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                            Amount <span class="text-red-500">*</span>
                        </label>
                        <input type="number" 
                               name="amount" 
                               id="amount" 
                               x-model="formData.amount"
                               step="0.01"
                               min="0.01"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-500 @enderror"
                               placeholder="0.00">
                        @error('amount')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                            Payment Method <span class="text-red-500">*</span>
                            <i class="fas fa-question-circle text-gray-400 ml-1" 
                               title="The method used to pay (cash, card, etc.)"></i>
                        </label>
                        <select name="payment_method" 
                                id="payment_method" 
                                x-model="formData.payment_method"
                                required
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('payment_method') border-red-500 @enderror">
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer</option>
                            <option value="card">Card</option>
                            <option value="online">Online</option>
                        </select>
                        @error('payment_method')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Money Source (Manual Selection) -->
                    @if(isset($moneySources) && $moneySources->count() > 0)
                    <div>
                        <label for="money_source_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Money Source
                            <i class="fas fa-question-circle text-gray-400 ml-1" 
                               title="Which physical location (cash register, bank account) is affected? Leave blank to auto-select based on payment method."></i>
                        </label>
                        <select name="money_source_id" 
                                id="money_source_id" 
                                x-model="formData.money_source_id"
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('money_source_id') border-red-500 @enderror">
                            <option value="">Auto-select (based on payment method)</option>
                            @foreach($moneySources as $ms)
                                <option value="{{ $ms->id }}" 
                                        {{ (isset($transaction) && $transaction->money_source_id == $ms->id) ? 'selected' : '' }}>
                                    {{ $ms->name }} ({{ $ms->type }})
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Leave blank to automatically select based on payment method, or choose a specific money source.
                        </p>
                        @error('money_source_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    <!-- Date -->
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-2">
                            Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               name="date" 
                               id="date" 
                               x-model="formData.date"
                               required
                               value="{{ date('Y-m-d') }}"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('date') border-red-500 @enderror">
                        @error('date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Notes
                    </label>
                    <textarea name="notes" 
                              id="notes" 
                              x-model="formData.notes"
                              rows="3"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror"
                              placeholder="Reason for adjustment...">Adjustment for transaction #{{ $transaction->id }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('transactions.index') }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                    <i class="fas fa-adjust mr-2"></i>
                    Create Adjustment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function transactionForm() {
    return {
        formData: {
            account_id: '{{ $transaction->account_id }}',
            branch_id: '{{ $transaction->branch_id ?? '' }}',
            type: '',
            amount: '',
            payment_method: '',
            money_source_id: '',
            date: new Date().toISOString().split('T')[0],
            notes: 'Adjustment for transaction #{{ $transaction->id }}',
        },
    }
}
</script>
@endsection

