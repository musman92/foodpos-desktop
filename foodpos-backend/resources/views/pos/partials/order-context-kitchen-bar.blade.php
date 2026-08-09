{{-- Kitchen status entry (shared by order context layouts) --}}
<button x-show="addonKitchenTracking && activeOrderId"
        type="button"
        @click="openKitchenStatusModal()"
        class="w-full flex items-center justify-between gap-2 px-2 py-1.5 rounded-md border text-left transition-colors touch-manipulation"
        :class="kitchenStatusPulse ? 'border-orange-300 bg-orange-50/80 ring-1 ring-orange-200' : 'border-gray-200 bg-white hover:bg-gray-50'">
    <span class="flex items-center gap-1.5 min-w-0 text-xs font-medium text-gray-800">
        <i class="fas fa-utensils text-orange-600 shrink-0"></i>
        <span class="truncate">Kitchen status</span>
        <span x-show="orderKitchenKots.length" class="text-[10px] text-orange-700 truncate hidden sm:inline" x-text="'· ' + kitchenSlipSummary()"></span>
    </span>
    <span class="shrink-0 text-[10px] font-semibold rounded-full px-2 py-0.5"
          :class="orderStatusBadgeClass(orderWorkflowStatus)"
          x-text="orderStatusLabel(orderWorkflowStatus, orderType)"></span>
</button>
