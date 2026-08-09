<div
    x-show="orderNotesOpen"
    x-cloak
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 -translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="w-full min-w-0">
    <label for="pos-order-notes-input" class="block text-[10px] leading-tight uppercase tracking-wide text-gray-500 mb-0.5">Notes</label>
    <input
        id="pos-order-notes-input"
        type="text"
        x-ref="orderNotesInput"
        x-model="notes"
        class="w-full h-9 px-2.5 border border-gray-300 rounded-md focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
        placeholder="Special instructions…">
</div>
