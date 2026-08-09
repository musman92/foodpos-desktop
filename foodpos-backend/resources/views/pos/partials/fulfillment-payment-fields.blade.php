@php
    $placement = $placement ?? 'bar';
@endphp

@if ($placement === 'sidebar')
    <div class="min-w-0 w-full max-w-full space-y-1">
        <div class="flex items-end gap-2 min-w-0 w-full">
            <div
                class="min-w-0 shrink-0"
                :class="(isCreditPaymentSelected() || paymentMethod === 'cash') && !isSplitPaymentSelected() && !isFocPaymentSelected() ? 'w-1/4' : 'w-full'">
                <label class="block text-[10px] leading-tight uppercase tracking-wide text-gray-500 mb-0.5">Payment</label>
                <select x-model="paymentSelection" @change="handlePaymentSelectionChange()" class="w-full h-9 px-1.5 border border-gray-300 rounded-md focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-xs">
                    <option value="">Select…</option>
                    <option x-show="allowPosCreditSales" value="credit">Credit</option>
                    <option x-show="canPosFoc" value="foc">FOC</option>
                    <option value="split">Split</option>
                    <template x-for="source in moneySources" :key="source.id">
                        <option :value="String(source.id)" x-text="source.name"></option>
                    </template>
                </select>
            </div>
            <div
                class="w-3/4 min-w-0 shrink-0"
                x-show="(isCreditPaymentSelected() || paymentMethod === 'cash') && !isSplitPaymentSelected() && !isFocPaymentSelected()"
                x-cloak>
                <div class="flex items-center justify-between gap-2 mb-0.5 min-h-[15px]">
                    <label class="text-[10px] leading-tight uppercase tracking-wide text-gray-500 shrink-0" x-text="isCreditPaymentSelected() ? 'Received' : 'Paid'"></label>
                    <span
                        x-show="isCreditPaymentSelected() && creditDueAmount(paidAmount) > 0"
                        class="text-[10px] text-amber-700 font-semibold tabular-nums leading-tight text-right truncate pr-0.5"
                        x-text="'On credit: ' + formatCurrency(creditDueAmount(paidAmount))"></span>
                    <span
                        x-show="!isCreditPaymentSelected() && changeAmount > 0"
                        class="text-[10px] text-green-700 font-semibold tabular-nums leading-tight text-right truncate pr-0.5"
                        x-text="'Change ' + formatCurrency(changeAmount)"></span>
                </div>
                <input
                    type="number"
                    x-model="paidAmount"
                    @input="calculateChange()"
                    @focus="if(paidAmount == 0) paidAmount = ''"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    :class="!isCreditPaymentSelected() && paidAmount > 0 && paidAmount < totalAmount ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-indigo-500 focus:border-indigo-500'"
                    class="w-full h-9 px-2 pr-2.5 border rounded-md focus:ring-1 text-sm font-semibold tabular-nums">
            </div>
        </div>
        <p x-show="moneySources.length === 0" class="text-[10px] text-red-600 leading-tight">No payment sources.</p>
    </div>
@else
    @php
        $fieldWrapClass = 'flex-1 min-w-[min(100%,10rem)] sm:min-w-[6rem] max-w-full lg:max-w-md';
        $paymentWrapClass = 'shrink-0 w-[min(100%,10rem)] sm:w-40';
        $paidWrapClass = 'shrink-0 w-28';
    @endphp
    <div class="{{ $fieldWrapClass }}">
        <label class="block text-[10px] leading-tight uppercase tracking-wide text-gray-500 mb-0.5">Notes</label>
        <input
            type="text"
            x-model="notes"
            class="w-full h-9 px-2.5 border border-gray-300 rounded-md focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            placeholder="Instructions…">
    </div>
    <div class="{{ $paymentWrapClass }}">
        <label class="block text-[10px] leading-tight uppercase tracking-wide text-gray-500 mb-0.5">Payment</label>
        <select x-model="paymentSelection" @change="handlePaymentSelectionChange()" class="w-full h-9 px-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
            <option value="">Select…</option>
            <option x-show="allowPosCreditSales" value="credit">Credit</option>
            <option x-show="canPosFoc" value="foc">FOC</option>
            <option value="split">Split payment</option>
            <template x-for="source in moneySources" :key="source.id">
                <option :value="String(source.id)" x-text="source.name + ' (' + source.type + ')'"></option>
            </template>
        </select>
        <p x-show="moneySources.length === 0 && !canPosFoc && !allowPosCreditSales" class="mt-0.5 text-[10px] text-red-600 whitespace-nowrap leading-tight">No payment sources.</p>
        <p x-show="isFocPaymentSelected()" class="mt-0.5 text-[10px] text-amber-700 whitespace-nowrap leading-tight">Complimentary — posted to FOC expense</p>
    </div>
    <div class="{{ $paidWrapClass }}" x-show="(isCreditPaymentSelected() || paymentMethod === 'cash') && !isSplitPaymentSelected() && !isFocPaymentSelected()" x-cloak>
        <label class="block text-[10px] leading-tight uppercase tracking-wide text-gray-500 mb-0.5" x-text="isCreditPaymentSelected() ? 'Received' : 'Paid'"></label>
        <input
            type="number"
            x-model="paidAmount"
            @input="calculateChange()"
            @focus="if(paidAmount == 0) paidAmount = ''"
            step="0.01"
            min="0"
            placeholder="0.00"
            :class="!isCreditPaymentSelected() && paidAmount > 0 && paidAmount < totalAmount ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-gray-300 focus:ring-indigo-500 focus:border-indigo-500'"
            class="w-full h-9 px-2 border rounded-md focus:ring-1 text-sm font-semibold tabular-nums">
        <div x-show="isCreditPaymentSelected() && creditDueAmount(paidAmount) > 0" class="mt-0.5 text-[10px] text-amber-700 font-semibold tabular-nums whitespace-nowrap leading-tight" x-text="'On credit: ' + formatCurrency(creditDueAmount(paidAmount))"></div>
        <div x-show="!isCreditPaymentSelected() && changeAmount > 0" class="mt-0.5 text-[10px] text-green-700 font-semibold tabular-nums whitespace-nowrap leading-tight" x-text="'Change ' + formatCurrency(changeAmount)"></div>
    </div>
@endif
