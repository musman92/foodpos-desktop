@php
    $isAdvance = ($kind ?? \App\Models\CustomerPayment::KIND_COLLECTION) === \App\Models\CustomerPayment::KIND_ADVANCE;
    $pageTitle = $title ?? ($isAdvance ? 'Receive advance' : 'Receive payment');
@endphp
@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
<div class="max-w-3xl mx-auto" x-data="customerPaymentForm()">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">{{ $pageTitle }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                @if($isAdvance)
                    Record cash or bank received as customer advance (creates customer credit for future sales).
                @else
                    Record cash or bank received; optional discount/write-off. Overpayment creates customer credit.
                @endif
            </p>
        </div>

        <form action="{{ $isAdvance ? route('customer-payments.advance.store') : route('customer-payments.store') }}" method="POST" class="p-6 space-y-6" id="customerPaymentForm">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="customer_search" class="block text-sm font-medium text-gray-700 mb-2">
                        Customer <span class="text-red-500">*</span>
                    </label>
                    <input type="hidden" name="customer_id" id="customer_id" x-model="selectedCustomerId" required>
                    <div class="relative">
                        <input type="text"
                               id="customer_search"
                               x-model="searchQuery"
                               @input="customerDropdownOpen = true"
                               @focus="customerDropdownOpen = true"
                               @blur="setTimeout(() => customerDropdownOpen = false, 200)"
                               @keydown.escape="customerDropdownOpen = false"
                               @keydown.arrow-down.prevent="highlightNextCustomer()"
                               @keydown.arrow-up.prevent="highlightPreviousCustomer()"
                               @keydown.enter.prevent="selectHighlightedCustomer()"
                               placeholder="Search by name, phone, or email…"
                               autocomplete="off"
                               class="block w-full h-12 px-4 pr-10 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('customer_id') border-red-500 @enderror">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </div>
                        <div x-show="customerDropdownOpen"
                             x-cloak
                             @click.away="customerDropdownOpen = false"
                             class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto">
                            <template x-if="filteredCustomers.length === 0">
                                <div class="px-4 py-3 text-sm text-gray-500">No customers found</div>
                            </template>
                            <template x-for="(option, idx) in filteredCustomers" :key="option.id">
                                <button type="button"
                                        @mousedown.prevent="selectCustomer(option)"
                                        :class="idx === highlightedCustomerIndex ? 'bg-indigo-50 text-indigo-900' : 'text-gray-900 hover:bg-gray-50'"
                                        class="w-full text-left px-4 py-2.5 text-sm border-b border-gray-100 last:border-0">
                                    <span x-text="option.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    @error('customer_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-600" x-show="selectedBalance > 0" x-cloak>
                        Balance owed: <span class="font-semibold text-amber-700" x-text="formatMoney(selectedBalance)"></span>
                    </p>
                    <p class="mt-1 text-sm text-emerald-700" x-show="selectedCustomerId && selectedBalance < -0.001" x-cloak>
                        Customer credit available: <span class="font-semibold" x-text="formatMoney(Math.abs(selectedBalance))"></span>
                    </p>
                    @unless($isAdvance)
                    <p class="mt-1 text-sm text-amber-700" x-show="selectedCustomerId && selectedBalance > -0.001 && selectedBalance <= 0" x-cloak>
                        This customer has no outstanding balance.
                    </p>
                    @endunless
                </div>

                @unless($isAdvance)
                <div class="md:col-span-2 rounded-lg border border-amber-200 bg-amber-50 p-4"
                     x-show="partialOrders.length > 0"
                     x-cloak>
                    <h3 class="text-sm font-semibold text-amber-900 mb-2">Unpaid orders (will be updated)</h3>
                    <ul class="text-sm text-amber-800 space-y-1">
                        <template x-for="order in partialOrders" :key="order.order_number">
                            <li>
                                #<span x-text="order.order_number"></span>
                                — owed <span x-text="order.owed"></span>
                            </li>
                        </template>
                    </ul>
                </div>
                @endunless

                @if(show_branch_ui())
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Branch <span class="text-xs text-gray-500">(optional)</span>
                    </label>
                    <select name="branch_id"
                            id="branch_id"
                            onchange="window.location.href = buildCustomerPaymentUrl()"
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('branch_id') border-red-500 @enderror">
                        <option value="">Company-wide</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ (string) old('branch_id', $selectedBranchId) === (string) $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('branch_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                @else
                    <input type="hidden" name="branch_id" value="{{ old('branch_id', $selectedBranchId) }}">
                @endif

                <div>
                    <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Payment date <span class="text-red-500">*</span>
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

                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                        Amount received <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                           name="amount"
                           id="amount"
                           step="0.01"
                           min="0.01"
                           x-model="amountReceived"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('amount')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <button type="button"
                            x-show="selectedBalance > 0"
                            x-cloak
                            @click="fillFullSettlement()"
                            class="mt-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                        Pay full balance (<span x-text="formatMoney(selectedBalance)"></span>)
                    </button>
                </div>

                @unless($isAdvance)
                <div>
                    <label for="discount_amount" class="block text-sm font-medium text-gray-700 mb-2">
                        Discount / write-off
                        <span class="text-xs font-normal text-gray-500">(optional)</span>
                    </label>
                    <input type="number"
                           name="discount_amount"
                           id="discount_amount"
                           step="0.01"
                           min="0"
                           x-model="discountAmount"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('discount_amount') border-red-500 @enderror"
                           placeholder="0.00">
                    @error('discount_amount')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">
                        e.g. customer owes 120, pays 100 — enter 20 here to clear the account.
                    </p>
                </div>

                <div class="md:col-span-2 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700" x-show="selectedBalance > 0" x-cloak>
                    <span class="font-medium">Total applied to balance:</span>
                    <span class="font-semibold text-gray-900" x-text="formatMoney(totalApplied())"></span>
                    <span class="text-emerald-700" x-show="totalApplied() > selectedBalance + 0.001" x-cloak>
                        — creates credit of <span x-text="formatMoney(totalApplied() - selectedBalance)"></span>
                    </span>
                    <span class="text-gray-500" x-show="totalApplied() <= selectedBalance && totalApplied() > 0" x-cloak>
                        — remaining after: <span x-text="formatMoney(Math.max(0, selectedBalance - totalApplied()))"></span>
                    </span>
                </div>
                @endunless

                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes"
                              id="notes"
                              rows="3"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror"
                              placeholder="Optional reference or remark">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('customer-payments.index') }}" class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 h-12 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
                    <i class="fas fa-check mr-2"></i>
                    {{ $isAdvance ? 'Record advance' : 'Record payment' }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function customerPaymentForm() {
    const customerOptions = @js($customerOptions);
    const currencySymbol = @json(get_currency_symbol(get_company_config()['currency'] ?? 'USD'));
    const customerContextUrl = @json(route('customer-payments.customer-context'));
    const initialCustomerId = @json((string) old('customer_id', $selectedCustomerId ?? ''));
    const initialBalance = @json($selectedCustomer ? round((float) $selectedCustomer->balance, 2) : 0);
    const initialPartialOrders = @js($initialPartialOrders);

    return {
        customerOptions,
        customerDropdownOpen: false,
        searchQuery: '',
        selectedCustomerId: initialCustomerId || '',
        selectedBalance: initialBalance,
        partialOrders: initialPartialOrders,
        highlightedCustomerIndex: -1,
        amountReceived: @json(old('amount', '')),
        discountAmount: @json(old('discount_amount', '0')),

        init() {
            if (this.selectedCustomerId) {
                const selected = this.customerOptions.find(
                    opt => String(opt.id) === String(this.selectedCustomerId)
                );
                if (selected) {
                    this.searchQuery = selected.label;
                }
            }
        },

        get filteredCustomers() {
            const query = (this.searchQuery || '').toLowerCase().trim();
            if (!query) {
                return this.customerOptions;
            }
            return this.customerOptions.filter(option => {
                if (!option) {
                    return false;
                }
                const haystack = [
                    option.name,
                    option.label,
                    option.phone,
                    option.email,
                ].filter(Boolean).join(' ').toLowerCase();

                return haystack.includes(query);
            });
        },

        async selectCustomer(option) {
            this.selectedCustomerId = String(option.id);
            this.searchQuery = option.label;
            this.selectedBalance = parseFloat(option.balance) || 0;
            this.customerDropdownOpen = false;
            this.highlightedCustomerIndex = -1;
            await this.loadCustomerContext(option.id);
        },

        async loadCustomerContext(customerId) {
            if (!customerId) {
                this.partialOrders = [];
                this.selectedBalance = 0;
                return;
            }
            try {
                const url = customerContextUrl + '?customer_id=' + encodeURIComponent(customerId);
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                this.selectedBalance = parseFloat(data.balance) || 0;
                this.partialOrders = Array.isArray(data.partial_orders) ? data.partial_orders : [];
                const selected = this.customerOptions.find(opt => String(opt.id) === String(customerId));
                if (selected) {
                    selected.balance = this.selectedBalance;
                    selected.label = selected.name + (this.selectedBalance > 0
                        ? ' — owes ' + this.formatMoney(this.selectedBalance)
                        : '');
                }
            } catch (e) {
                // keep last known values
            }
        },

        highlightNextCustomer() {
            if (this.highlightedCustomerIndex < this.filteredCustomers.length - 1) {
                this.highlightedCustomerIndex++;
            }
        },

        highlightPreviousCustomer() {
            if (this.highlightedCustomerIndex > 0) {
                this.highlightedCustomerIndex--;
            }
        },

        selectHighlightedCustomer() {
            if (this.highlightedCustomerIndex >= 0 && this.filteredCustomers[this.highlightedCustomerIndex]) {
                this.selectCustomer(this.filteredCustomers[this.highlightedCustomerIndex]);
            }
        },

        totalApplied() {
            const amount = parseFloat(this.amountReceived) || 0;
            const discount = parseFloat(this.discountAmount) || 0;
            return Math.round((amount + discount) * 100) / 100;
        },

        fillFullSettlement() {
            const discount = parseFloat(this.discountAmount) || 0;
            const pay = Math.max(0, Math.round((this.selectedBalance - discount) * 100) / 100);
            this.amountReceived = pay > 0 ? pay.toFixed(2) : '';
        },

        formatMoney(value) {
            const n = parseFloat(value);
            if (isNaN(n)) {
                return currencySymbol + '0.00';
            }
            return currencySymbol + n.toFixed(2);
        },
    };
}

function buildCustomerPaymentUrl() {
    const customerId = document.getElementById('customer_id')?.value;
    const branchId = document.getElementById('branch_id')?.value;
    let url = '{{ route('customer-payments.create') }}';
    const params = new URLSearchParams();
    if (customerId) {
        params.set('customer_id', customerId);
    }
    if (branchId) {
        params.set('branch_id', branchId);
    }
    const qs = params.toString();
    return qs ? url + '?' + qs : url;
}
</script>
@endsection
