@extends('layouts.app')

@section('title', 'Edit Purchase Return')

@section('content')
@php
    $purchaseLabel = $purchase->purchase_number
        .' · '.format_date($purchase->purchase_date)
        .' · '.format_currency($purchase->total_amount);

    $itemRows = $purchase->items->map(function ($item) use ($returnedByItemId, $purchase) {
        $item->setRelation('purchase', $purchase);
        $thisReturnQty = (float) ($returnedByItemId[$item->id] ?? 0);
        $returnable = round($item->returnableQuantity() + $thisReturnQty, 4);
        $name = $item->item->name ?? (ucfirst(str_replace('_', ' ', (string) $item->item_type)).' #'.$item->item_id);

        return [
            'id' => $item->id,
            'name' => $name,
            'unit' => $item->unit_name ?? (string) ($item->unit_id ?? ''),
            'unit_price' => round((float) $item->unit_price, 2),
            'quantity' => (float) $item->quantity,
            'quantity_returned' => max(0, round((float) ($item->quantity_returned ?? 0) - $thisReturnQty, 4)),
            'returnable' => $returnable,
            'current_qty' => $thisReturnQty,
        ];
    })->values();

    $oldQuantities = old('items');
    $initialQuantities = [];
    if (is_array($oldQuantities)) {
        foreach ($oldQuantities as $row) {
            if (! empty($row['purchase_item_id'])) {
                $initialQuantities[(string) $row['purchase_item_id']] = $row['quantity'] ?? '';
            }
        }
    } else {
        foreach ($itemRows as $row) {
            $initialQuantities[(string) $row['id']] = $row['current_qty'] > 0 ? $row['current_qty'] : '';
        }
    }
@endphp

<div class="max-w-4xl mx-auto space-y-6"
     x-data="purchaseReturnEditForm(@js($itemRows), @js($initialQuantities))">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Purchase Return</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $purchaseReturn->return_number }}</p>
        </div>
        <a href="{{ route('purchase-returns.show', $purchaseReturn) }}"
           class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i>
            Back
        </a>
    </div>

    <form method="POST" action="{{ route('purchase-returns.update', $purchaseReturn) }}" class="bg-white shadow rounded-lg overflow-hidden">
        @csrf
        @method('PUT')
        <div class="px-6 py-4 border-b border-gray-200 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                    <div class="flex items-center h-12 px-4 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-900">
                        {{ $purchaseReturn->supplier->name ?? $purchase->supplier->name ?? 'No supplier' }}
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Return date</label>
                    <input type="date"
                           name="return_date"
                           value="{{ old('return_date', $purchaseReturn->return_date?->format('Y-m-d')) }}"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('return_date') border-red-500 @enderror">
                    @error('return_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Purchase</label>
                <div class="flex items-center h-12 px-4 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-900">
                    {{ $purchaseLabel }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes"
                          rows="2"
                          class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror"
                          placeholder="e.g. 2 kg chicken not in good condition">{{ old('notes', $purchaseReturn->notes) }}</textarea>
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
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Purchased</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Purchase price</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Other returns</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Returnable</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Return qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="(item, index) in items" :key="item.id">
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
            <div class="mt-4 flex justify-end text-sm font-semibold text-gray-900">
                Return total: <span class="ml-2 tabular-nums" x-text="formatMoney(returnTotal)"></span>
            </div>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-3">
            <a href="{{ route('purchase-returns.show', $purchaseReturn) }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                    :disabled="returnTotal <= 0">
                <i class="fas fa-save mr-2"></i>
                Update return
            </button>
        </div>
    </form>
</div>

<script>
function purchaseReturnEditForm(items, initialQuantities) {
    return {
        items,
        quantities: { ...initialQuantities },
        get returnTotal() {
            return this.items.reduce((sum, item) => {
                const qty = parseFloat(this.quantities[item.id] || 0) || 0;
                return sum + (qty * (parseFloat(item.unit_price) || 0));
            }, 0);
        },
        formatQty(value) {
            const n = parseFloat(value || 0);
            return Number.isInteger(n) ? String(n) : n.toFixed(4).replace(/\.?0+$/, '');
        },
        formatMoney(value) {
            return @json(currency_symbol()) + Number(value || 0).toFixed(2);
        },
    };
}
</script>
@endsection
