@extends('layouts.app')

@section('title', 'New Purchase Return')

@section('content')
@php
    $supplierOptions = $suppliers->map(fn ($supplier) => [
        'id' => (string) $supplier->id,
        'label' => $supplier->displayLabel(),
    ])->values();

    if ($purchases->contains(fn ($purchase) => ! $purchase->supplier_id)) {
        $supplierOptions = $supplierOptions->prepend([
            'id' => '0',
            'label' => 'No supplier',
        ])->values();
    }

    $purchaseOptions = $purchases->map(function ($purchase) {
        return [
            'id' => $purchase->id,
            'supplier_id' => $purchase->supplier_id ? (string) $purchase->supplier_id : '0',
            'label' => $purchase->purchase_number
                .' · '.format_date($purchase->purchase_date)
                .' · '.format_currency($purchase->total_amount),
            'items' => $purchase->items->map(function ($item) use ($purchase) {
                $item->setRelation('purchase', $purchase);
                $returnable = $item->returnableQuantity();
                $name = $item->item->name ?? (ucfirst(str_replace('_', ' ', (string) $item->item_type)).' #'.$item->item_id);

                return [
                    'id' => $item->id,
                    'name' => $name,
                    // Same unit + unit price the user entered on this purchase line.
                    'unit' => $item->unit_name ?? (string) ($item->unit_id ?? ''),
                    'unit_price' => round((float) $item->unit_price, 2),
                    'quantity' => (float) $item->quantity,
                    'quantity_returned' => (float) ($item->quantity_returned ?? 0),
                    'returnable' => $returnable,
                ];
            })->values(),
        ];
    })->values();

    $selectedPurchaseId = old('purchase_id', $selectedPurchase?->id);
    $selectedSupplierId = old(
        'supplier_id',
        $selectedSupplierId
            ?? ($selectedPurchase?->supplier_id ?: ($selectedPurchase ? '0' : null))
    );
    if ($selectedSupplierId !== null && $selectedSupplierId !== '') {
        $selectedSupplierId = (string) $selectedSupplierId;
    } else {
        $selectedSupplierId = '';
    }
@endphp

<div class="max-w-4xl mx-auto space-y-6"
     x-data="purchaseReturnForm(@js($supplierOptions), @js($purchaseOptions), @js($selectedSupplierId), @js($selectedPurchaseId ? (string) $selectedPurchaseId : ''))">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">New Purchase Return</h1>
            <p class="mt-1 text-sm text-gray-500">Choose a supplier, then return goods against one of their purchases</p>
        </div>
        <a href="{{ route('purchase-returns.index') }}"
           class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i>
            Back
        </a>
    </div>

    <form method="POST" action="{{ route('purchase-returns.store') }}" class="bg-white shadow rounded-lg overflow-hidden">
        @csrf
        <div class="px-6 py-4 border-b border-gray-200 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div x-data="searchableSelect({
                        options: suppliers,
                        value: supplierId,
                        maxResults: 200,
                        placeholder: 'Search supplier…',
                        emptyMessage: 'No suppliers found',
                        onChange: (value) => { onSupplierChange(value); },
                    })"
                     x-init="init(); $watch('selectedValue', (value) => { onSupplierChange(value); })">
                    <x-searchable-select
                        label="Supplier"
                        required
                        id="purchase_return_supplier"
                    >
                        <x-slot:hiddenInput>
                            <input type="hidden" name="supplier_id" x-model="selectedValue">
                        </x-slot:hiddenInput>
                    </x-searchable-select>
                    @error('supplier_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Return date</label>
                    <input type="date"
                           name="return_date"
                           value="{{ old('return_date', local_today()) }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('return_date') border-red-500 @enderror">
                    @error('return_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div :key="'purchase-select-' + (supplierId || 'none')"
                 x-data="searchableSelect({
                     getOptions: () => supplierPurchases,
                     value: purchaseId,
                     maxResults: 200,
                     placeholder: supplierId ? 'Search purchase…' : 'Select a supplier first…',
                     emptyMessage: supplierId ? 'No returnable purchases for this supplier' : 'Select a supplier first',
                     getDisabled: () => !supplierId,
                     onChange: (value) => { setPurchase(value); },
                 })"
                 x-init="init(); $watch('selectedValue', (value) => { setPurchase(value); })">
                <x-searchable-select
                    label="Purchase"
                    required
                    id="purchase_return_purchase"
                >
                    <x-slot:hiddenInput>
                        <input type="hidden" name="purchase_id" x-model="selectedValue" :required="!!supplierId">
                    </x-slot:hiddenInput>
                </x-searchable-select>
                @error('purchase_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes"
                          rows="2"
                          class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror"
                          placeholder="e.g. 2 kg chicken not in good condition">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('items')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Items to return</h2>
            <template x-if="!selectedPurchase">
                <p class="text-sm text-gray-500" x-text="supplierId ? 'Select a purchase to see returnable items.' : 'Select a supplier and purchase to see returnable items.'"></p>
            </template>
            <template x-if="selectedPurchase">
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Purchased</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Purchase price</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Already returned</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Returnable</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Return qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <template x-for="(item, index) in selectedPurchase.items" :key="item.id">
                                <tr>
                                    <td class="px-3 py-3">
                                        <div class="font-medium text-gray-900" x-text="item.name"></div>
                                        <div class="text-xs text-gray-500" x-text="item.unit || ''"></div>
                                        <input type="hidden" :name="'items[' + index + '][purchase_item_id]'" :value="item.id">
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-gray-700" x-text="formatQty(item.quantity)"></td>
                                    <td class="px-3 py-3 text-right tabular-nums text-gray-900 font-medium" x-text="formatMoney(item.unit_price)"></td>
                                    <td class="px-3 py-3 text-right tabular-nums text-gray-700" x-text="formatQty(item.quantity_returned)"></td>
                                    <td class="px-3 py-3 text-right tabular-nums text-gray-900" x-text="formatQty(item.returnable)"></td>
                                    <td class="px-3 py-3 text-right">
                                        <input type="number"
                                               step="0.0001"
                                               min="0"
                                               :max="item.returnable"
                                               :name="'items[' + index + '][quantity]'"
                                               x-model="quantities[item.id]"
                                               class="w-28 h-10 px-2 text-right rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                               :disabled="item.returnable <= 0">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
            <div class="mt-4 flex justify-end text-sm font-semibold text-gray-900" x-show="selectedPurchase">
                Return total: <span class="ml-2 tabular-nums" x-text="formatMoney(returnTotal)"></span>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-3">
            <a href="{{ route('purchase-returns.index') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                    :disabled="!selectedPurchase || returnTotal <= 0">
                <i class="fas fa-save mr-2"></i>
                Save return
            </button>
        </div>
    </form>
</div>

<script>
function purchaseReturnForm(suppliers, purchases, selectedSupplierId, selectedPurchaseId) {
    return {
        suppliers,
        purchases,
        supplierId: selectedSupplierId ? String(selectedSupplierId) : '',
        purchaseId: selectedPurchaseId ? String(selectedPurchaseId) : '',
        quantities: {},
        get supplierPurchases() {
            if (!this.supplierId) return [];
            return this.purchases.filter(p => String(p.supplier_id) === String(this.supplierId));
        },
        get selectedPurchase() {
            if (!this.purchaseId) return null;
            return this.supplierPurchases.find(p => String(p.id) === String(this.purchaseId)) || null;
        },
        get returnTotal() {
            if (!this.selectedPurchase) return 0;
            return this.selectedPurchase.items.reduce((sum, item) => {
                const qty = parseFloat(this.quantities[item.id] || 0) || 0;
                return sum + (qty * (parseFloat(item.unit_price) || 0));
            }, 0);
        },
        onSupplierChange(value) {
            const next = value != null && value !== '' ? String(value) : '';
            if (String(this.supplierId || '') === next) {
                return;
            }
            this.supplierId = next;
            this.purchaseId = '';
            this.quantities = {};
        },
        setPurchase(value) {
            const next = value != null && value !== '' ? String(value) : '';
            if (String(this.purchaseId || '') === next) {
                return;
            }
            this.purchaseId = next;
            this.quantities = {};
            if (!this.selectedPurchase) return;
            this.selectedPurchase.items.forEach((item) => {
                this.quantities[item.id] = '';
            });
        },
        formatQty(value) {
            const n = parseFloat(value || 0);
            return Number.isInteger(n) ? String(n) : n.toFixed(4).replace(/\.?0+$/, '');
        },
        formatMoney(value) {
            return @json(currency_symbol()) + Number(value || 0).toFixed(2);
        },
        init() {
            if (this.selectedPurchase) {
                this.selectedPurchase.items.forEach((item) => {
                    if (this.quantities[item.id] === undefined) {
                        this.quantities[item.id] = '';
                    }
                });
            }
        }
    };
}
</script>
@endsection
