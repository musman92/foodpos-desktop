@extends('layouts.app')

@section('title', 'Order '.$order->order_number)

@section('content')
@php
    $canRefund = auth()->user()->hasAppPermission('order-management.refund');
    $canAppendNote = auth()->user()->hasAppPermission('order-management.append-note');
@endphp
<div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Order {{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $order->branch->name ?? '' }} · {{ $order->created_at->format('Y-m-d H:i') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('order-management.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Back to list</a>
            <a href="{{ route('pos.invoice', $order) }}" class="inline-flex items-center px-4 py-2 bg-gray-100 rounded-lg text-sm font-medium text-gray-800 hover:bg-gray-200">Receipt</a>
        </div>
    </div>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-500">Payment</span>
                <div class="mt-1 space-y-1">
                    <p class="font-medium text-gray-900">
                        {{ ucfirst($order->payment_status) }}
                        ·
                        @if($order->payment_method === 'split')
                            Split payment
                        @else
                            {{ $order->payment_method ? ucfirst(str_replace('_', ' ', $order->payment_method)) : '—' }}
                        @endif
                    </p>
                    @if($order->payment_method === 'split' && $order->payments->isNotEmpty())
                        <ul class="space-y-1 pt-1">
                            @foreach($order->payments as $payment)
                                <li class="flex items-center justify-between gap-3 text-sm text-gray-700 bg-gray-50 rounded-md px-2.5 py-1.5">
                                    <span>{{ $payment->moneySource?->name ?? 'Payment source' }}</span>
                                    <span class="font-semibold text-gray-900 tabular-nums">{{ format_currency($payment->amount) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @elseif($order->moneySource)
                        <p class="text-sm text-gray-600">{{ $order->moneySource->name }}</p>
                    @endif
                    @if((float) $order->paid_amount > 0)
                        <p class="text-sm text-gray-600">Paid {{ format_currency($order->paid_amount) }}</p>
                    @endif
                </div>
            </div>
            <div>
                <span class="text-gray-500">Totals</span>
                <div class="mt-1 space-y-0.5 text-sm text-gray-900">
                    <p>Subtotal <span class="font-medium">{{ format_currency($order->subtotal) }}</span></p>
                    @if((float) $order->discount_amount > 0)
                        <p class="text-emerald-700">
                            Discount
                            @if($order->discount_type === 'percentage' && $order->discount_value)
                                ({{ rtrim(rtrim(number_format((float) $order->discount_value, 2), '0'), '.') }}%)
                            @endif
                            <span class="font-medium">-{{ format_currency($order->discount_amount) }}</span>
                        </p>
                    @endif
                    @if((float) $order->service_charge > 0)
                        <p>Service charge <span class="font-medium">{{ format_currency($order->service_charge) }}</span></p>
                    @endif
                    @if((float) $order->delivery_fee > 0)
                        <p>Delivery <span class="font-medium">{{ format_currency($order->delivery_fee) }}</span></p>
                    @endif
                    <p>Tax <span class="font-medium">{{ format_currency($order->tax_amount) }}</span></p>
                    <p class="font-semibold text-indigo-700">Total {{ format_currency($order->total_amount) }}</p>
                </div>
            </div>
            @if($order->customer_name || $order->customer_phone || $order->customer_email)
                <div>
                    <span class="text-gray-500">Customer</span>
                    <p class="font-medium text-gray-900">{{ $order->customer_name }} @if($order->customer_phone) · {{ $order->customer_phone }} @endif @if($order->customer_email) · {{ $order->customer_email }} @endif</p>
                </div>
            @endif
            <div>
                <span class="text-gray-500">Cashier</span>
                <p class="font-medium text-gray-900">{{ $order->cashier->name ?? '—' }}</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ordered</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Refunded</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remaining</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($order->items as $item)
                        @php
                            $billable = max(0, (float) $item->quantity - (float) $item->quantity_refunded);
                            $variantInfo = \App\Support\TopSellingItemsReport::normalizeVariants($item->variants);
                            $variantName = $variantInfo ? trim((string) ($variantInfo['variant_name'] ?? '')) : '';
                            $optionName = $variantInfo ? trim((string) ($variantInfo['option_name'] ?? '')) : '';
                        @endphp
                        <tr>
                            <td class="px-6 py-3 text-sm text-gray-900">
                                <div>{{ $item->item_name }} @if($item->deal_id)<span class="text-xs text-gray-500">(deal)</span>@endif</div>
                                @if($variantName || $optionName)
                                    <div class="mt-0.5 text-xs text-indigo-600 font-medium">
                                        {{ $variantName ? $variantName.': ' : '' }}{{ $optionName ?: $variantName }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ $item->quantity }}</td>
                            <td class="px-6 py-3 text-sm text-gray-600">{{ $item->quantity_refunded }}</td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ number_format($billable, 2) }}</td>
                            <td class="px-6 py-3 text-sm text-right text-gray-900">{{ format_currency($item->total_price) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($order->notes)
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-2">Order notes (POS)</h2>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $order->notes }}</p>
        </div>
    @endif

    @if($order->management_notes)
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-2">Management notes</h2>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $order->management_notes }}</p>
        </div>
    @endif

    @if($canAppendNote)
    <div class="bg-white shadow rounded-lg border border-gray-200 py-6 px-6 sm:px-10">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Add management note</h2>
        <p class="text-sm text-gray-500 mb-3">Append an internal note (refunds, conversations, adjustments). This does not change money or stock.</p>
        <form method="POST" action="{{ route('order-management.append-note', $order) }}" class="space-y-4">
            @csrf
            <div>
                <label for="management_notes" class="block text-sm font-medium text-gray-700 mb-1">Note <span class="text-red-500">*</span></label>
                <textarea name="management_notes" id="management_notes" rows="3" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">{{ old('management_notes') }}</textarea>
                @error('management_notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-900">Save note</button>
        </form>
    </div>
    @endif

    @if($canRefund)
        <div class="bg-white shadow rounded-lg p-6 border border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Refunds</h2>
                <p class="text-sm text-gray-500 mt-1">Adjustments to a completed sale are done through the refund flow (quantities, restock, notes).</p>
            </div>
            <div class="shrink-0">
                @if($allowsRefund)
                    <a href="{{ route('order-management.refunds.process', $order) }}" class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Refund items</a>
                @else
                    <p class="text-sm text-gray-600 max-w-md">
                        @if($order->status === 'open' && $order->payment_status === 'unpaid')
                            Open unpaid orders cannot be refunded here.
                        @else
                            There is no remaining quantity to refund on this order.
                        @endif
                    </p>
                @endif
            </div>
        </div>
    @endif

    @if($order->refunds->isNotEmpty())
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Refund history</h2>
            <div class="space-y-6">
                @foreach($order->refunds as $refund)
                    <div class="border-l-4 border-indigo-200 pl-4">
                        <p class="text-sm text-gray-500">{{ $refund->created_at->format('Y-m-d H:i') }} · {{ $refund->creator->name ?? 'User' }} · {{ format_currency($refund->total_refund) }} (subtotal {{ format_currency($refund->subtotal_refund) }}, tax {{ format_currency($refund->tax_refund) }})</p>
                        <p class="text-sm text-gray-800 mt-1 whitespace-pre-wrap"><span class="font-medium">Notes:</span> {{ $refund->notes }}</p>
                        <ul class="mt-2 text-sm text-gray-600 list-disc list-inside">
                            @foreach($refund->lines as $line)
                                @php
                                    $refundVariant = \App\Support\TopSellingItemsReport::normalizeVariants($line->orderItem?->variants);
                                    $refundVariantName = $refundVariant ? trim((string) ($refundVariant['variant_name'] ?? '')) : '';
                                    $refundOptionName = $refundVariant ? trim((string) ($refundVariant['option_name'] ?? '')) : '';
                                @endphp
                                <li>
                                    {{ $line->orderItem->item_name ?? 'Item' }}
                                    @if($refundVariantName || $refundOptionName)
                                        <span class="text-indigo-600">({{ $refundVariantName ? $refundVariantName.': ' : '' }}{{ $refundOptionName ?: $refundVariantName }})</span>
                                    @endif
                                    : qty {{ $line->quantity }},
                                    {{ format_currency($line->refund_subtotal) }} + tax {{ format_currency($line->refund_tax) }}
                                    @if($line->restock_inventory)<span class="text-green-700">· restocked</span>@else<span class="text-gray-500">· not restocked</span>@endif
                                    @if($line->line_notes) — {{ $line->line_notes }}@endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
