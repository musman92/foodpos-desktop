{{-- Order context: compact chips, max 2 per row, customer full row --}}
<div class="shrink-0 border-b border-gray-200 bg-gray-50/80 px-2 py-2 space-y-1.5 text-xs">
    <div class="grid grid-cols-2 gap-1.5 min-w-0">
        <div class="min-w-0">
            <template x-if="canChangeOrderType()">
                <select
                    x-model="orderType"
                    @change="handleOrderTypeChange()"
                    class="pos-context-chip w-full min-w-0 h-8 pl-2 pr-7 text-xs font-semibold border border-gray-300 rounded-md bg-white text-gray-900 truncate focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="dine_in">Dine in</option>
                    <option value="takeaway">Takeaway</option>
                    <option value="delivery">Delivery</option>
                </select>
            </template>
            <template x-if="!canChangeOrderType()">
                <div class="pos-context-chip w-full h-8 px-2 inline-flex items-center min-w-0 border border-gray-200 rounded-md bg-white text-xs font-semibold text-gray-900 truncate">
                    <span class="truncate" x-text="orderTypeLabel()"></span>
                </div>
            </template>
        </div>

        <div x-show="orderType === 'dine_in'" x-cloak class="min-w-0">
            <button
                type="button"
                @click="openTableViewModal()"
                class="pos-context-chip w-full h-8 px-2 inline-flex items-center justify-between gap-1 min-w-0 border rounded-md text-xs font-semibold touch-manipulation transition-colors truncate"
                :class="selectedTableId
                    ? 'border-gray-300 bg-white text-gray-900 hover:bg-gray-50'
                    : 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100'">
                <span class="truncate" x-text="selectedTableId ? selectedTableLabel() : 'Pick table'"></span>
                <i class="fas fa-chevron-down text-[9px] text-gray-400 shrink-0"></i>
            </button>
        </div>

        <div x-show="orderType === 'delivery'" x-cloak class="min-w-0">
            <select
                x-model.number="deliveryRiderId"
                class="pos-context-chip w-full min-w-0 h-8 pl-2 pr-7 text-xs font-semibold border rounded-md bg-white truncate focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                :class="hasRiderSelected() ? 'border-gray-300 text-gray-900' : 'border-amber-400 text-amber-900'">
                <option value="">Select rider</option>
                <template x-for="staff in riderStaffOptions()" :key="'ctx-rider-' + staff.id">
                    <option :value="staff.id" x-text="staff.name"></option>
                </template>
            </select>
        </div>
    </div>

    <div x-show="orderType === 'dine_in'" x-cloak class="grid grid-cols-2 gap-1.5 min-w-0">
        <div class="min-w-0">
            <select
                x-model.number="waiterId"
                class="pos-context-chip w-full min-w-0 h-8 pl-2 pr-7 text-xs font-semibold border rounded-md bg-white truncate focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                :class="hasWaiterSelected() ? 'border-gray-300 text-gray-900' : 'border-amber-400 text-amber-900'">
                <option value="">Select waiter</option>
                <template x-for="staff in waiterStaffOptions()" :key="'ctx-waiter-' + staff.id">
                    <option :value="staff.id" x-text="staff.name"></option>
                </template>
            </select>
        </div>
    </div>

    <div class="rounded-md border border-gray-200 bg-white px-2 py-1.5 min-w-0">
        <div class="flex items-start justify-between gap-2 min-w-0">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-0.5">Customer</p>
                <template x-if="hasCustomerAssigned()">
                    <p class="text-xs font-medium text-gray-900 truncate leading-snug" x-text="customerSummaryLine()"></p>
                    <p x-show="customerAddressSummary()" class="text-[10px] text-gray-500 truncate leading-snug mt-0.5" x-text="customerAddressSummary()"></p>
                </template>
                <template x-if="!hasCustomerAssigned()">
                    <p class="text-xs text-gray-500 leading-snug" x-text="orderType === 'delivery' ? 'Select customer' : 'Walk-in'"></p>
                </template>
            </div>
            <button
                type="button"
                @click="openCustomerPickerModal()"
                class="shrink-0 self-center text-[10px] font-semibold text-indigo-600 hover:text-indigo-800 touch-manipulation"
                x-text="hasCustomerAssigned() ? 'Change' : (orderType === 'delivery' ? 'Select' : 'Add')"></button>
        </div>
    </div>

    @include('pos.partials.order-context-kitchen-bar')
</div>
