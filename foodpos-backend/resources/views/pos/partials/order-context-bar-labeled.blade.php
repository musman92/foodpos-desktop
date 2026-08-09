{{-- Order context: labeled rows (classic) --}}
<div class="shrink-0 border-b border-gray-200 bg-gray-50/80 px-2 py-2 space-y-1.5 text-xs">
    <div class="flex items-center gap-2 min-w-0">
        <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide shrink-0 w-[4.5rem]">Type</span>
        <template x-if="canChangeOrderType()">
            <select
                x-model="orderType"
                @change="handleOrderTypeChange()"
                class="flex-1 min-w-0 h-7 px-2 text-xs font-medium border border-gray-300 rounded-md bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="dine_in">Dine in</option>
                <option value="takeaway">Takeaway</option>
                <option value="delivery">Delivery</option>
            </select>
        </template>
        <template x-if="!canChangeOrderType()">
            <span class="flex-1 min-w-0 text-xs font-semibold text-gray-900 truncate" x-text="orderTypeLabel()"></span>
        </template>
    </div>

    <div class="flex items-center gap-2 min-w-0">
        <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide shrink-0 w-[4.5rem]">Customer</span>
        <template x-if="hasCustomerAssigned()">
            <span class="flex-1 min-w-0 text-xs font-medium text-gray-900 truncate" x-text="customerSummaryLine()"></span>
            <button type="button" @click="openCustomerPickerModal()" class="shrink-0 text-[10px] text-indigo-600 hover:text-indigo-800 font-semibold">Change</button>
        </template>
        <template x-if="!hasCustomerAssigned()">
            <button type="button" @click="openCustomerPickerModal()"
                class="flex-1 min-w-0 h-7 inline-flex items-center justify-center gap-1 px-2 text-[11px] font-medium text-indigo-700 bg-white border border-indigo-200 rounded-md hover:bg-indigo-50 touch-manipulation truncate">
                <i class="fas fa-user-plus text-[10px] shrink-0"></i>
                <span class="truncate" x-text="orderType === 'delivery' ? 'Select customer' : 'Walk-in'"></span>
            </button>
        </template>
    </div>
    <p x-show="hasCustomerAssigned() && customerAddressSummary()" class="pl-[4.5rem] text-[10px] text-gray-500 truncate -mt-1" x-text="customerAddressSummary()"></p>

    <div x-show="orderType === 'dine_in'" x-cloak class="space-y-1.5">
        <div class="flex items-center gap-2 min-w-0">
            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide shrink-0 w-[4.5rem]">Table</span>
            <span class="flex-1 min-w-0 text-xs truncate"
                :class="selectedTableId ? 'font-medium text-gray-900' : 'text-gray-400'"
                x-text="selectedTableId ? selectedTableLabel() : '—'"></span>
            <button type="button" @click="openTableViewModal()" class="shrink-0 h-7 px-2 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 whitespace-nowrap">
                Pick
            </button>
        </div>
        <div class="flex items-center gap-2 min-w-0">
            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide shrink-0 w-[4.5rem]">Waiter</span>
            <select
                x-model.number="waiterId"
                class="flex-1 min-w-0 h-7 px-2 text-xs border rounded-md bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                :class="orderType === 'dine_in' && !hasWaiterSelected() ? 'border-amber-400' : 'border-gray-300'">
                <option value="">Select waiter</option>
                <template x-for="staff in waiterStaffOptions()" :key="staff.id">
                    <option :value="staff.id" x-text="staff.name"></option>
                </template>
            </select>
        </div>
    </div>

    <div x-show="orderType === 'delivery'" x-cloak class="flex items-center gap-2 min-w-0">
        <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide shrink-0 w-[4.5rem]">Rider</span>
        <select
            x-model.number="deliveryRiderId"
            class="flex-1 min-w-0 h-7 px-2 text-xs border rounded-md bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
            :class="orderType === 'delivery' && !hasRiderSelected() ? 'border-amber-400' : 'border-gray-300'">
            <option value="">Select rider</option>
            <template x-for="staff in riderStaffOptions()" :key="'rider-' + staff.id">
                <option :value="staff.id" x-text="staff.name"></option>
            </template>
        </select>
    </div>
</div>
