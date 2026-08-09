<!-- Kitchen status modal (kitchen tracking addon) -->
<div x-show="showKitchenStatusModal"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[115] overflow-y-auto"
     style="background-color: rgba(0, 0, 0, 0.5);"
     @click.self="closeKitchenStatusModal()"
     @keydown.escape.window="showKitchenStatusModal && closeKitchenStatusModal()">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden"
             @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-start justify-between gap-3 px-4 py-3 border-b border-gray-200 bg-gray-50/80">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-utensils text-orange-600"></i>
                        Kitchen status
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5 truncate" x-show="activeOrderNumber" x-text="'Order ' + activeOrderNumber"></p>
                </div>
                <button type="button" @click="closeKitchenStatusModal()" class="shrink-0 p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-4 space-y-4 max-h-[min(70vh,32rem)] overflow-y-auto">
                <!-- Kitchen tickets -->
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-2">
                        Kitchen tickets
                        <span x-show="orderKitchenKots.length > 0" class="text-gray-400 font-normal normal-case" x-text="'(' + orderKitchenKots.length + ')'"></span>
                    </p>
                    <template x-if="orderKitchenKots.length > 0">
                        <div class="grid gap-1.5 sm:grid-cols-2">
                            <template x-for="(kot, kotIndex) in orderKitchenKots" :key="'ks-kot-' + (kot.id || kotIndex)">
                                <div class="rounded-lg border border-orange-200 bg-orange-50 px-2.5 py-2 text-xs">
                                    <p class="font-semibold text-orange-900 leading-tight">
                                        KOT #<span x-text="kot.kot_number"></span>
                                        · Token #<span x-text="kot.token_number"></span>
                                        <span x-show="kot.type_label" class="text-orange-700"> · <span x-text="kot.type_label"></span></span>
                                        <span x-show="kot.is_reprint" class="text-orange-600">(reprint)</span>
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-orange-950/80 leading-snug" x-text="orderDetailsKotLines(kot)"></p>
                                </div>
                            </template>
                        </div>
                    </template>
                    <div x-show="orderKitchenKots.length === 0" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-500">
                        Not sent yet — use KOT to send items to kitchen
                    </div>
                </div>

                <!-- Current status -->
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Current status</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold"
                              :class="orderStatusBadgeClass(orderWorkflowStatus)"
                              x-text="orderStatusLabel(orderWorkflowStatus, orderType)"></span>
                        <span x-show="orderWorkflowStatus === 'open'" class="text-xs text-gray-500">Send KOT to start kitchen workflow</span>
                    </div>
                </div>

                <!-- Progress -->
                <div x-show="orderWorkflowStatus !== 'open' && orderWorkflowStatus !== 'cancelled'">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Progress</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="step in kitchenStatusSequence()" :key="step.key">
                            <span class="inline-flex items-center text-[10px] font-medium px-2 py-0.5 rounded-full border"
                                  :class="isKitchenStepActive(step.key)
                                    ? 'border-indigo-400 bg-indigo-50 text-indigo-900'
                                    : (isKitchenStepComplete(step.key)
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                                        : 'border-gray-200 bg-white text-gray-400')"
                                  x-text="step.label"></span>
                        </template>
                    </div>
                </div>

                <!-- Expected ready -->
                <div x-show="orderExpectedReadyAt" class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2.5">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-800 mb-0.5">Expected ready</p>
                    <p class="text-sm font-semibold text-sky-950" x-text="formatExpectedReady(orderExpectedReadyAt)"></p>
                </div>

                <!-- Actions -->
                <div x-show="orderAllowedNextStatuses.length > 0">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Update status</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <template x-for="nextStatus in orderAllowedNextStatuses" :key="nextStatus">
                            <button type="button"
                                    @click="advanceOrderStatus(nextStatus)"
                                    :disabled="orderStatusUpdating"
                                    class="h-10 px-3 rounded-lg text-sm font-semibold border transition-colors disabled:opacity-50"
                                    :class="kitchenActionButtonClass(nextStatus)"
                                    x-text="'Mark ' + orderStatusLabel(nextStatus, orderType)"></button>
                        </template>
                    </div>
                </div>

                <!-- Recent activity -->
                <div x-show="orderStatusLogs.length > 0">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Recent activity</p>
                    <ul class="space-y-1.5 max-h-32 overflow-y-auto">
                        <template x-for="(log, logIndex) in orderStatusLogs.slice().reverse().slice(0, 5)" :key="logIndex">
                            <li class="text-xs text-gray-600 flex items-start gap-2">
                                <i class="fas fa-circle text-[5px] text-gray-400 mt-1.5 shrink-0"></i>
                                <span>
                                    <span class="font-medium text-gray-800" x-text="orderStatusLabel(log.to_status, orderType)"></span>
                                    <span class="text-gray-400" x-show="log.changed_at"> · </span>
                                    <span x-text="formatStatusLogTime(log.changed_at)"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50/80 flex justify-end">
                <button type="button" @click="closeKitchenStatusModal()" class="px-4 py-2 h-10 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
