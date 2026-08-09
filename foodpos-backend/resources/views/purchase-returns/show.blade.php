@extends('layouts.app')

@section('title', 'Purchase Return Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Purchase Return</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $purchaseReturn->return_number }}</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($purchaseReturn->purchase)
                <a href="{{ route('purchases.show', $purchaseReturn->purchase) }}"
                   class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    View purchase
                </a>
            @endif
            @if(auth()->user()->hasAppPermission('purchase-returns.update'))
                <a href="{{ route('purchase-returns.edit', $purchaseReturn) }}"
                   class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-edit mr-2"></i>
                    Edit
                </a>
            @endif
            @if(auth()->user()->hasAppPermission('purchase-returns.destroy'))
                <form action="{{ route('purchase-returns.destroy', $purchaseReturn) }}"
                      method="POST"
                      class="inline"
                      onsubmit="return confirm('Delete this purchase return? Stock and supplier balance will be restored.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 h-12 border border-red-200 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                        <i class="fas fa-trash mr-2"></i>
                        Delete
                    </button>
                </form>
            @endif
            <a href="{{ route('purchase-returns.index') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to list
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
            <h2 class="text-lg font-semibold text-gray-900">Return information</h2>
        </div>
        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-gray-500">Return #</div>
                <div class="font-medium text-gray-900">{{ $purchaseReturn->return_number }}</div>
            </div>
            <div>
                <div class="text-gray-500">Date</div>
                <div class="font-medium text-gray-900">{{ format_date($purchaseReturn->return_date) }}</div>
            </div>
            <div>
                <div class="text-gray-500">Purchase</div>
                <div class="font-medium text-gray-900">
                    @if($purchaseReturn->purchase)
                        <a href="{{ route('purchases.show', $purchaseReturn->purchase) }}" class="text-indigo-600 hover:text-indigo-800">
                            {{ $purchaseReturn->purchase->purchase_number }}
                        </a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div>
                <div class="text-gray-500">Supplier</div>
                <div class="font-medium text-gray-900">{{ $purchaseReturn->supplier->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Created by</div>
                <div class="font-medium text-gray-900">{{ $purchaseReturn->creator->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Settlement</div>
                <div class="font-medium text-gray-900">
                    @if($purchaseReturn->settlement_type === 'supplier_credit')
                        Supplier credit {{ format_currency($purchaseReturn->credit_amount) }}
                    @elseif($purchaseReturn->settlement_type === 'mixed')
                        Payable reduced {{ format_currency($purchaseReturn->payable_reduction_amount) }},
                        credit {{ format_currency($purchaseReturn->credit_amount) }}
                    @else
                        Payable reduced {{ format_currency($purchaseReturn->payable_reduction_amount) }}
                    @endif
                </div>
            </div>
            @if($purchaseReturn->notes)
                <div class="md:col-span-2">
                    <div class="text-gray-500">Notes</div>
                    <div class="font-medium text-gray-900 whitespace-pre-wrap">{{ $purchaseReturn->notes }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Returned items</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Purchase price</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($purchaseReturn->items as $line)
                        @php
                            $purchaseItem = $line->purchaseItem;
                            if ($purchaseItem && $purchaseReturn->purchase) {
                                $purchaseItem->setRelation('purchase', $purchaseReturn->purchase);
                            }
                            $name = $purchaseItem?->item?->name
                                ?? (ucfirst(str_replace('_', ' ', (string) ($purchaseItem->item_type ?? 'item'))).' #'.($purchaseItem->item_id ?? ''));
                            // Always show the purchase-line price the user entered (not catalog / stock cost).
                            $displayUnitPrice = $purchaseItem
                                ? round((float) $purchaseItem->unit_price, 2)
                                : round((float) $line->unit_price, 2);
                            $unitLabel = $purchaseItem?->unit_name ?? '';
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-gray-900">
                                <div>{{ $name }}</div>
                                @if($unitLabel !== '')
                                    <div class="text-xs text-gray-500">{{ $unitLabel }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ format_quantity($line->quantity) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ format_currency($displayUnitPrice) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-900">{{ format_currency($line->total_price) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right font-semibold text-gray-900">Total</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">{{ format_currency($purchaseReturn->total_amount) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
