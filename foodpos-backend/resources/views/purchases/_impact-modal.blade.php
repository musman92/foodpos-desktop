{{-- Shared purchase edit/delete impact confirmation modal --}}
<div x-show="impactModal.open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     @keydown.escape.window="impactModal.open = false">
    <div class="absolute inset-0 bg-gray-900/50" @click="impactModal.open = false"></div>
    <div class="relative w-full max-w-lg rounded-xl bg-white shadow-xl border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900" x-text="impactModal.title"></h3>
            <p class="mt-1 text-sm text-gray-600" x-text="impactModal.summary"></p>
        </div>
        <div class="px-5 py-4 max-h-80 overflow-y-auto space-y-3">
            <template x-for="(message, index) in impactModal.messages" :key="index">
                <div class="rounded-lg px-3 py-2 text-sm"
                     :class="{
                        'bg-red-50 text-red-800 border border-red-100': message.level === 'error',
                        'bg-amber-50 text-amber-900 border border-amber-100': message.level === 'warning',
                        'bg-blue-50 text-blue-900 border border-blue-100': message.level === 'info',
                     }">
                    <span x-text="message.text"></span>
                </div>
            </template>
            <template x-if="impactModal.stock_lines.length > 0">
                <div class="rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">Stock</div>
                    <template x-for="(line, index) in impactModal.stock_lines" :key="'stock-' + index">
                        <div class="px-3 py-2 text-sm border-t border-gray-100 flex justify-between gap-3">
                            <span x-text="line.item_name"></span>
                            <span class="shrink-0 tabular-nums"
                                  :class="line.reversible ? 'text-gray-600' : 'text-red-600 font-medium'"
                                  x-text="line.reversible ? ('Qty ' + line.quantity) : 'Consumed'"></span>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="impactModal.supplier_payments.length > 0">
                <div class="rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600"
                         x-text="impactModal.supplier_payments[0]?.kept ? 'Linked supplier payments' : 'Supplier payments to reverse'"></div>
                    <template x-for="(payment, index) in impactModal.supplier_payments" :key="'pay-' + index">
                        <div class="px-3 py-2 text-sm border-t border-gray-100 flex justify-between gap-3">
                            <span x-text="payment.payment_number"></span>
                            <span class="shrink-0 tabular-nums text-gray-700" x-text="payment.allocated_amount"></span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
        <div class="px-5 py-4 border-t border-gray-200 flex justify-end gap-2">
            <button type="button"
                    class="px-4 py-2 h-10 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                    @click="impactModal.open = false">
                Cancel
            </button>
            <button type="button"
                    x-show="impactModal.can_proceed"
                    class="px-4 py-2 h-10 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
                    @click="impactModal.onConfirm && impactModal.onConfirm()">
                Proceed
            </button>
        </div>
    </div>
</div>
