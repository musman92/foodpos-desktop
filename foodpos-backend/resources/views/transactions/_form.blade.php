@php
    $isEdit = isset($transaction) && $transaction->exists;
    $formAction = $isEdit ? route('transactions.update', $transaction) : route('transactions.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $transactionData = $isEdit ? $transaction->toArray() : [];
    $title = $isEdit ? 'Edit Transaction' : 'Create New Transaction';
    $subtitle = $isEdit ? 'Update transaction information' : 'Record a new financial transaction';
    $buttonText = $isEdit ? 'Update Transaction' : 'Create Transaction';
@endphp

<div class="max-w-4xl mx-auto" x-data="transactionForm({{ json_encode($transactionData) }}, {{ $isEdit ? 'true' : 'false' }})">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        </div>

        <form action="{{ $formAction }}" method="POST" class="p-6 space-y-6">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <!-- Transaction Information -->
            <div class="space-y-6">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Transaction Details</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Account -->
                    <div>
                        <label for="account_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Account <span class="text-red-500">*</span>
                            <i class="fas fa-question-circle text-gray-400 ml-1" 
                               title="What category is this transaction? (e.g., Purchase, Sales, Salary)"></i>
                        </label>
                        <select name="account_id" 
                                id="account_id" 
                                x-model="formData.account_id"
                                required
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('account_id') border-red-500 @enderror">
                            <option value="">Select Account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" 
                                        data-type="{{ $account->type }}"
                                        {{ ($isEdit && isset($transaction) && $transaction->account_id == $account->id) ? 'selected' : '' }}>
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
                                        {{ ($isEdit && isset($transaction) && $transaction->branch_id == $branch->id) ? 'selected' : '' }}>
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
                            <option value="in" {{ ($isEdit && isset($transaction) && $transaction->type == 'in') ? 'selected' : '' }}>In (Income)</option>
                            <option value="out" {{ ($isEdit && isset($transaction) && $transaction->type == 'out') ? 'selected' : '' }}>Out (Expense)</option>
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
                            <option value="cash" {{ ($isEdit && isset($transaction) && $transaction->payment_method == 'cash') ? 'selected' : '' }}>Cash</option>
                            <option value="transfer" {{ ($isEdit && isset($transaction) && $transaction->payment_method == 'transfer') ? 'selected' : '' }}>Transfer</option>
                            <option value="card" {{ ($isEdit && isset($transaction) && $transaction->payment_method == 'card') ? 'selected' : '' }}>Card</option>
                            <option value="online" {{ ($isEdit && isset($transaction) && $transaction->payment_method == 'online') ? 'selected' : '' }}>Online</option>
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
                                        {{ ($isEdit && isset($transaction) && $transaction->money_source_id == $ms->id) ? 'selected' : '' }}>
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
                               value="{{ ($isEdit && isset($transaction)) ? $transaction->date->format('Y-m-d') : date('Y-m-d') }}"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('date') border-red-500 @enderror">
                        @error('date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Reference Type -->
                    <div>
                        <label for="reference_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Reference Type
                        </label>
                        <select name="reference_type" 
                                id="reference_type" 
                                x-model="formData.reference_type"
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('reference_type') border-red-500 @enderror">
                            <option value="">None</option>
                            <option value="sale" {{ ($isEdit && isset($transaction) && $transaction->reference_type == 'sale') ? 'selected' : '' }}>Sale</option>
                            <option value="purchase" {{ ($isEdit && isset($transaction) && $transaction->reference_type == 'purchase') ? 'selected' : '' }}>Purchase</option>
                            <option value="refund" {{ ($isEdit && isset($transaction) && $transaction->reference_type == 'refund') ? 'selected' : '' }}>Refund</option>
                            <option value="expense" {{ ($isEdit && isset($transaction) && $transaction->reference_type == 'expense') ? 'selected' : '' }}>Expense</option>
                        </select>
                        @error('reference_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Reference ID -->
                    <div>
                        <label for="ref_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Reference ID
                        </label>
                        <input type="number" 
                               name="ref_id" 
                               id="ref_id" 
                               x-model="formData.ref_id"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('ref_id') border-red-500 @enderror"
                               placeholder="Optional reference ID">
                        @error('ref_id')
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
                              placeholder="Additional notes about this transaction..."></textarea>
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
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-save mr-2"></i>
                    {{ $buttonText }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function transactionForm(transactionData = null, isEdit = false) {
    return {
        formData: {
            account_id: transactionData?.account_id || '',
            branch_id: transactionData?.branch_id || '',
            type: transactionData?.type || '',
            amount: transactionData?.amount || '',
            payment_method: transactionData?.payment_method || '',
            money_source_id: transactionData?.money_source_id || '',
            reference_type: transactionData?.reference_type || '',
            date: transactionData?.date || new Date().toISOString().split('T')[0],
            ref_id: transactionData?.ref_id || '',
            notes: transactionData?.notes || '',
        },
    }
}
</script>

