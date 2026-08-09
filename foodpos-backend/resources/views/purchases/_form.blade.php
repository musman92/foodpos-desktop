@php
    $isEdit = isset($purchase) && $purchase->exists;
    $formAction = $isEdit ? route('purchases.update', $purchase) : route('purchases.store');
    $title = $isEdit ? 'Edit Purchase' : 'Create New Purchase';
    $subtitle = $isEdit ? 'Update purchase information' : 'Record a new inventory purchase';
    $buttonText = $isEdit ? 'Update Purchase' : 'Create Purchase';
    $currency = $currency ?? 'USD';
    $currencySymbol = $currencySymbol ?? '$';
    $purchaseCatalogJson = json_encode($purchaseCatalog ?? []);
    $moneySourcesJson = json_encode($moneySources ?? []);
    $purchaseEditPayloadJson = 'null';
    if ($isEdit) {
        $purchaseEditPayloadJson = json_encode([
            'purchase_id' => $purchase->id,
            'validate_update_url' => route('purchases.validate-update', $purchase),
            'supplier_id' => $purchase->supplier_id ? (string) $purchase->supplier_id : '',
            'purchase_date' => $purchase->purchase_date->format('Y-m-d'),
            'payment_selection' => $purchase->payment_method === 'credit'
                ? 'credit'
                : (string) ($purchase->money_source_id ?? 'credit'),
            'paid_amount' => (float) $purchase->paid_amount > 0
                ? number_format((float) $purchase->paid_amount, 2, '.', '')
                : '',
            'notes' => $purchase->notes ?? '',
            'totals' => [
                'subtotal' => (float) $purchase->subtotal,
                'tax' => (float) $purchase->tax_amount,
                'discount' => (float) $purchase->discount_amount,
                'total' => (float) $purchase->total_amount,
            ],
            'items' => $purchase->items->map(function ($item) {
                $ingredient = $item->item_type === 'ingredient' ? $item->item : null;

                return [
                    'item_type' => $item->item_type,
                    'item_id' => $item->item_id,
                    'display_name' => $item->item->name ?? 'Item',
                    'unit_id' => $item->unit_id ?? '',
                    'purchase_unit_name' => $item->unit_name ?? '',
                    'conversion_rate' => $ingredient ? (float) ($ingredient->conversion_rate ?: 1) : 1,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'expiry_date' => $item->expiry_date?->format('Y-m-d') ?? '',
                    'show_expiry' => (bool) $item->expiry_date,
                    'total_price' => (float) $item->total_price,
                ];
            })->values()->all(),
            'has_additional_supplier_payments' => $purchase->hasAdditionalSupplierPayments(),
            'branch_id' => (string) $purchase->branch_id,
        ]);
    }
@endphp

<div class="max-w-6xl mx-auto" x-data="purchaseForm({{ $purchaseCatalogJson }}, '{{ $currency }}', '{{ $currencySymbol }}', {{ $moneySourcesJson }}, {{ $purchaseEditPayloadJson }})" x-init="init()">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        </div>

        <!-- Error Messages -->
        @if($errors->any())
            <div class="mx-6 mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">There were errors with your submission</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        @if($isEdit && $purchase->hasAdditionalSupplierPayments())
            <div class="mx-6 mt-4 bg-amber-50 border border-amber-200 rounded-lg p-4">
                <p class="text-sm text-amber-900">
                    Supplier payments are linked to this purchase. Saving changes will keep those payments and adjust the supplier balance if the total changes.
                </p>
            </div>
        @endif

        @if($isEdit)
            <div class="mx-6 mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-900">
                    Before saving, we check whether purchased stock is still available and show any payment reversals required.
                </p>
            </div>
        @endif

        <form action="{{ $formAction }}" method="POST" class="p-6 space-y-6" @submit="submitForm" x-ref="purchaseForm">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <!-- Purchase Header -->
            <div class="space-y-6">
                <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                    <h2 class="text-lg font-semibold text-gray-900">Purchase Information</h2>
                    <div class="flex items-center space-x-2 text-sm text-gray-600">
                        <span class="font-medium">Currency:</span>
                        <span class="px-2 py-1 bg-indigo-50 text-indigo-700 rounded font-semibold">{{ $currency }} ({{ $currencySymbol }})</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Branch -->
                    @if(show_branch_ui())
                    <div>
                        <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Branch <span class="text-red-500">*</span>
                        </label>
                        <select name="branch_id" 
                                id="branch_id" 
                                x-model="formData.branch_id"
                                @change="handleBranchChange()"
                                @if($isEdit) disabled @endif
                                required
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @if($isEdit) bg-gray-100 cursor-not-allowed @endif">
                            <option value="">Select Branch</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @if($isEdit)
                            <input type="hidden" name="branch_id" :value="formData.branch_id">
                        @endif
                    </div>
                    @else
                        <input type="hidden" name="branch_id" id="branch_id" x-model="formData.branch_id" :value="formData.branch_id">
                    @endif

                    <!-- Supplier -->
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Supplier
                            <span x-show="supplierRequired()" class="text-red-500">*</span>
                        </label>
                        <select name="supplier_id" 
                                id="supplier_id" 
                                x-model="formData.supplier_id"
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select Supplier</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->displayLabel() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Purchase Date -->
                    <div>
                        <label for="purchase_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Purchase Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               name="purchase_date" 
                               id="purchase_date" 
                               x-model="formData.purchase_date"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Payment source -->
                    <div>
                        <label for="payment_selection" class="block text-sm font-medium text-gray-700 mb-2">
                            Payment source <span class="text-red-500">*</span>
                        </label>
                        <select name="payment_selection"
                                id="payment_selection"
                                x-model="formData.payment_selection"
                                @change="handlePaymentSelectionChange()"
                                required
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="credit">Credit (pay later — pending)</option>
                            <template x-for="source in availableMoneySources()" :key="source.id">
                                <option :value="String(source.id)" x-text="source.name + ' (' + source.type + ')'"></option>
                            </template>
                        </select>
                        <p x-show="formData.branch_id && availableMoneySources().length === 0" class="mt-1 text-xs text-amber-700">
                            No payment sources for this branch. Use Credit or add money sources in settings.
                        </p>
                        <p x-show="isCreditPaymentSelected()" class="mt-1 text-xs text-amber-700 font-medium">
                            Nothing paid now. Full total (<span x-text="formatCurrency(totals.total)"></span>) is recorded as owed to the supplier.
                        </p>
                    </div>

                    <!-- Amount paid -->
                    <div x-show="!isCreditPaymentSelected()" x-cloak>
                        <label for="paid_amount" class="block text-sm font-medium text-gray-700 mb-2">
                            Amount paid
                            <span class="text-xs text-gray-500 ml-1">(max <span x-text="formatCurrency(totals.total)"></span>)</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">{{ $currencySymbol }}</span>
                            <input type="number"
                                   name="paid_amount"
                                   id="paid_amount"
                                   x-model="formData.paid_amount"
                                   @input="clampPaidAmount()"
                                   step="0.01"
                                   min="0"
                                   :max="totals.total"
                                   class="block w-full h-12 pl-8 pr-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   placeholder="0.00">
                        </div>
                        <p x-show="paymentDueAmount() > 0 && !isCreditPaymentSelected()" class="mt-1 text-xs text-amber-700 font-medium">
                            On credit: <span x-text="formatCurrency(paymentDueAmount())"></span>
                        </p>
                    </div>

                    <!-- Payment status (auto) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment status</label>
                        <div class="h-12 px-4 flex items-center rounded-lg border border-gray-200 bg-gray-50">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                  :class="paymentStatusBadgeClass()"
                                  x-text="paymentStatusLabel()"></span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Set automatically from payment source and amount paid.</p>
                    </div>
                </div>
            </div>

            <!-- Purchase Items -->
            <div class="space-y-4">
                <div class="border-b border-gray-200 pb-2">
                    <h2 class="text-lg font-semibold text-gray-900">Purchase Items</h2>
                    <p class="mt-1 text-sm text-gray-500">Search and select items — price is prefilled from the ingredient; enter quantity in the purchase unit shown.</p>
                </div>

                <div x-data="purchaseCatalogSelect($data)" x-init="init()">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Add item <span class="text-red-500">*</span>
                    </label>
                    <x-searchable-select />
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase w-10">#</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase min-w-[200px]">Item</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase w-36">Unit price</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase w-44">Quantity</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase w-28">Total</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase w-14"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="hover:bg-gray-50 align-top">
                                    <td class="px-3 py-3 text-gray-500" x-text="index + 1"></td>
                                    <td class="px-3 py-3">
                                        <input type="hidden" :name="'items[' + index + '][item_type]'" :value="item.item_type">
                                        <input type="hidden" :name="'items[' + index + '][item_id]'" :value="item.item_id">
                                        <input type="hidden" :name="'items[' + index + '][unit_id]'" :value="item.unit_id">
                                        <input type="hidden" :name="'items[' + index + '][total_price]'" :value="item.total_price">
                                        <div class="flex items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-gray-900" x-text="item.display_name"></p>
                                                <div x-show="item.show_expiry" x-cloak class="mt-2">
                                                    <input type="date"
                                                           :name="'items[' + index + '][expiry_date]'"
                                                           x-model="item.expiry_date"
                                                           class="block w-full max-w-[11rem] h-9 px-2 rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                </div>
                                            </div>
                                            <button type="button"
                                                    @click="toggleExpiry(index)"
                                                    class="shrink-0 p-2 rounded-lg border transition touch-manipulation"
                                                    :class="item.show_expiry ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-400 hover:text-indigo-600 hover:border-indigo-300'"
                                                    :title="item.show_expiry ? 'Remove expiry date' : 'Add expiry date'">
                                                <i class="fas fa-calendar-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="relative">
                                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">{{ $currencySymbol }}</span>
                                            <input type="number"
                                                   :name="'items[' + index + '][unit_price]'"
                                                   x-model="item.unit_price"
                                                   @input="calculateItemTotal(index)"
                                                   step="0.01"
                                                   min="0"
                                                   required
                                                   class="block w-full h-10 pl-6 pr-2 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2">
                                            <input type="number"
                                                   :name="'items[' + index + '][quantity]'"
                                                   x-model="item.quantity"
                                                   @input="calculateItemTotal(index)"
                                                   step="0.01"
                                                   min="0.01"
                                                   required
                                                   placeholder="0"
                                                   class="block w-24 h-10 px-2 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <span class="text-sm font-medium text-gray-600 whitespace-nowrap" x-text="item.purchase_unit_name || '—'"></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-right font-medium text-gray-900 tabular-nums whitespace-nowrap" x-text="formatCurrency(item.total_price)"></td>
                                    <td class="px-3 py-3 text-right">
                                        <button type="button"
                                                @click="removeItem(index)"
                                                class="p-2 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg"
                                                title="Remove">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="items.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
                                    No items yet. Use the search box above to add ingredients or menu items.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Totals -->
            <div class="space-y-4 border-t border-gray-200 pt-6">
                <div class="flex justify-end">
                    <div class="w-full md:w-1/2 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium" x-text="formatCurrency(totals.subtotal)"></span>
                            <input type="hidden" name="subtotal" :value="totals.subtotal">
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Tax:</span>
                            <input type="number" 
                                   name="tax_amount" 
                                   x-model="totals.tax"
                                   @input="calculateTotals()"
                                   step="0.01"
                                   min="0"
                                   class="w-32 text-right border-gray-300 rounded"
                                   placeholder="0.00">
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Discount:</span>
                            <input type="number" 
                                   name="discount_amount" 
                                   x-model="totals.discount"
                                   @input="calculateTotals()"
                                   step="0.01"
                                   min="0"
                                   class="w-32 text-right border-gray-300 rounded"
                                   placeholder="0.00">
                        </div>
                        <div class="flex justify-between text-lg font-bold border-t border-gray-200 pt-2">
                            <span>Total:</span>
                            <span x-text="formatCurrency(totals.total)"></span>
                            <input type="hidden" name="total_amount" :value="totals.total">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Purchase Notes</label>
                <textarea name="notes" 
                          id="notes"
                          rows="3"
                          x-model="formData.notes"
                          class="block w-full px-4 py-2 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Additional notes about this purchase..."></textarea>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('purchases.index') }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-save mr-2"></i>
                    {{ $buttonText }}
                </button>
            </div>
        </form>
    </div>

    @include('purchases._impact-modal')
</div>

<script>
function purchaseForm(catalog, currency, currencySymbol, moneySources, purchaseData) {
    return {
        catalog: Array.isArray(catalog) ? catalog : [],
        catalogPicker: null,
        allMoneySources: moneySources || [],
        currency: currency || 'USD',
        currencySymbol: currencySymbol || '$',
        purchaseData: purchaseData || null,
        impactModal: {
            open: false,
            title: '',
            summary: '',
            messages: [],
            stock_lines: [],
            supplier_payments: [],
            can_proceed: false,
            onConfirm: null,
        },
        formData: {
            branch_id: '',
            supplier_id: '',
            purchase_date: new Date().toISOString().split('T')[0],
            payment_selection: 'credit',
            paid_amount: '',
            notes: '',
        },
        items: [],
        totals: {
            subtotal: 0,
            tax: 0,
            discount: 0,
            total: 0,
        },

        init() {
            if (!this.purchaseData) {
                return;
            }

            this.formData.branch_id = String(this.purchaseData.branch_id || '');
            this.formData.supplier_id = String(this.purchaseData.supplier_id || '');
            this.formData.purchase_date = this.purchaseData.purchase_date || this.formData.purchase_date;
            this.formData.payment_selection = String(this.purchaseData.payment_selection || 'credit');
            this.formData.paid_amount = this.purchaseData.paid_amount || '';
            this.formData.notes = this.purchaseData.notes || '';
            this.items = Array.isArray(this.purchaseData.items) ? this.purchaseData.items : [];
            this.totals = {
                subtotal: parseFloat(this.purchaseData.totals?.subtotal) || 0,
                tax: parseFloat(this.purchaseData.totals?.tax) || 0,
                discount: parseFloat(this.purchaseData.totals?.discount) || 0,
                total: parseFloat(this.purchaseData.totals?.total) || 0,
            };
        },

        addFromCatalog(catalogKey) {
            const entry = this.catalog.find((row) => String(row.id) === String(catalogKey));
            if (!entry) {
                return;
            }

            this.items.push({
                item_type: entry.item_type,
                item_id: entry.item_id,
                display_name: entry.label || entry.name,
                unit_id: entry.purchase_unit_key || '',
                purchase_unit_name: entry.purchase_unit_name || '',
                conversion_rate: parseFloat(entry.conversion_rate) || 1,
                unit_price: entry.purchase_price ?? '',
                quantity: '',
                expiry_date: '',
                show_expiry: false,
                total_price: 0,
            });

            this.resetCatalogPicker();
        },

        resetCatalogPicker() {
            if (!this.catalogPicker) {
                return;
            }
            this.catalogPicker.selectedValue = '';
            this.catalogPicker.searchQuery = '';
            this.catalogPicker.isOpen = false;
            this.catalogPicker.highlightedIndex = -1;
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this.calculateTotals();
        },

        toggleExpiry(index) {
            const item = this.items[index];
            item.show_expiry = !item.show_expiry;
            if (!item.show_expiry) {
                item.expiry_date = '';
            }
        },

        calculateItemTotal(index) {
            const item = this.items[index];
            const quantity = parseFloat(item.quantity) || 0;
            const unitPrice = parseFloat(item.unit_price) || 0;
            item.total_price = quantity * unitPrice;
            this.calculateTotals();
        },

        calculateTotals() {
            this.totals.subtotal = this.items.reduce((sum, item) => {
                return sum + (parseFloat(item.total_price) || 0);
            }, 0);

            const tax = parseFloat(this.totals.tax) || 0;
            const discount = parseFloat(this.totals.discount) || 0;
            this.totals.total = this.totals.subtotal + tax - discount;
            this.clampPaidAmount();
        },

        isCreditPaymentSelected() {
            return this.formData.payment_selection === 'credit';
        },

        supplierRequired() {
            if (this.isCreditPaymentSelected()) {
                return true;
            }
            const total = parseFloat(this.totals.total) || 0;
            const paid = parseFloat(this.formData.paid_amount) || 0;
            return paid < total;
        },

        availableMoneySources() {
            const branchId = parseInt(this.formData.branch_id, 10);
            if (!branchId) {
                return this.allMoneySources;
            }

            const forBranch = this.allMoneySources.filter((source) => {
                if (!source.branch_ids || source.branch_ids.length === 0) {
                    return true;
                }
                return source.branch_ids.some((id) => Number(id) === branchId);
            });

            return forBranch.length > 0 ? forBranch : this.allMoneySources;
        },

        handleBranchChange() {
            if (!this.isCreditPaymentSelected()) {
                const available = this.availableMoneySources();
                const selectedId = parseInt(this.formData.payment_selection, 10);
                const stillValid = available.some((s) => Number(s.id) === selectedId);
                if (!stillValid) {
                    this.formData.payment_selection = 'credit';
                    this.formData.paid_amount = '';
                }
            }
            this.clampPaidAmount();
        },

        handlePaymentSelectionChange() {
            if (this.isCreditPaymentSelected()) {
                this.formData.paid_amount = '';
                return;
            }

            const total = parseFloat(this.totals.total) || 0;
            if (total > 0) {
                this.formData.paid_amount = total.toFixed(2);
            }
            this.clampPaidAmount();
        },

        clampPaidAmount() {
            const total = parseFloat(this.totals.total) || 0;
            let paid = parseFloat(this.formData.paid_amount);
            if (isNaN(paid) || paid < 0) {
                paid = 0;
            }
            if (total > 0 && paid > total) {
                paid = total;
            }
            this.formData.paid_amount = paid > 0 ? paid.toFixed(2) : '';
        },

        resolvedPaymentStatus() {
            if (this.isCreditPaymentSelected()) {
                return 'pending';
            }
            const total = parseFloat(this.totals.total) || 0;
            const paid = parseFloat(this.formData.paid_amount) || 0;
            if (paid <= 0) {
                return 'pending';
            }
            if (paid >= total) {
                return 'paid';
            }
            return 'partial';
        },

        paymentStatusLabel() {
            const labels = {
                pending: 'Pending',
                paid: 'Paid',
                partial: 'Partial',
            };
            return labels[this.resolvedPaymentStatus()] || 'Pending';
        },

        paymentStatusBadgeClass() {
            const status = this.resolvedPaymentStatus();
            if (status === 'paid') {
                return 'bg-green-100 text-green-800';
            }
            if (status === 'partial') {
                return 'bg-yellow-100 text-yellow-800';
            }
            return 'bg-red-100 text-red-800';
        },

        paymentDueAmount() {
            const total = parseFloat(this.totals.total) || 0;
            const paid = parseFloat(this.formData.paid_amount) || 0;
            return Math.max(0, total - paid);
        },

        formatCurrency(amount) {
            const num = parseFloat(amount) || 0;
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: this.currency,
                minimumFractionDigits: 2,
            }).format(num);
        },

        submitForm(event) {
            if (this.items.length === 0) {
                alert('Please add at least one item to the purchase.');
                event.preventDefault();
                return false;
            }

            for (let i = 0; i < this.items.length; i++) {
                const item = this.items[i];
                if (!item.item_type || !item.item_id || !item.quantity || item.unit_price === '') {
                    alert(`Row ${i + 1} is missing quantity or price.`);
                    event.preventDefault();
                    return false;
                }
            }

            if (!this.isCreditPaymentSelected()) {
                const paid = parseFloat(this.formData.paid_amount) || 0;
                const total = parseFloat(this.totals.total) || 0;
                if (paid > total) {
                    alert('Paid amount cannot exceed the purchase total.');
                    event.preventDefault();
                    return false;
                }
            }

            if (this.supplierRequired() && !this.formData.supplier_id) {
                alert('Please select a supplier when buying on credit or leaving a balance due.');
                event.preventDefault();
                return false;
            }

            if (!this.purchaseData?.validate_update_url) {
                return true;
            }

            event.preventDefault();
            this.validateAndConfirmUpdate();

            return false;
        },

        buildUpdatePayload() {
            return {
                branch_id: this.formData.branch_id,
                supplier_id: this.formData.supplier_id || null,
                purchase_date: this.formData.purchase_date,
                payment_selection: this.formData.payment_selection,
                paid_amount: this.formData.paid_amount || 0,
                subtotal: this.totals.subtotal,
                tax_amount: this.totals.tax,
                discount_amount: this.totals.discount,
                total_amount: this.totals.total,
                notes: this.formData.notes,
                items: this.items.map((item) => ({
                    item_type: item.item_type,
                    item_id: item.item_id,
                    quantity: item.quantity,
                    unit_id: item.unit_id,
                    unit_price: item.unit_price,
                    expiry_date: item.show_expiry ? item.expiry_date : null,
                    notes: null,
                })),
            };
        },

        openImpactModal(report, title, onConfirm) {
            this.impactModal = {
                open: true,
                title,
                summary: report.summary || '',
                messages: report.messages || [],
                stock_lines: report.stock_lines || [],
                supplier_payments: report.supplier_payments || [],
                can_proceed: !!report.can_proceed,
                onConfirm: report.can_proceed ? onConfirm : null,
            };
        },

        async validateAndConfirmUpdate() {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(this.purchaseData.validate_update_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token || '',
                },
                body: JSON.stringify(this.buildUpdatePayload()),
            });

            if (!response.ok) {
                alert('Could not validate this purchase update. Please try again.');
                return;
            }

            const report = await response.json();
            this.openImpactModal(report, 'Confirm purchase update', () => {
                this.impactModal.open = false;
                this.$refs.purchaseForm.submit();
            });
        },
    }
}
</script>

