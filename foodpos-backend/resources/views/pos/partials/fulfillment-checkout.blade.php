@php
    $placement = $placement ?? 'bar';
@endphp
@if ($placement === 'sidebar')
    <div class="flex flex-col gap-2.5 py-2.5 pl-2.5 pr-3 border-t border-gray-200 bg-white shrink-0 min-w-0 w-full max-w-full shadow-[0_-4px_12px_-6px_rgba(0,0,0,0.08)]">
        <div class="flex items-center justify-between gap-2 min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 truncate">Payment &amp; actions</p>
            <button
                type="button"
                @click="toggleOrderNotes()"
                :class="hasOrderNotes() ? 'border-amber-300 bg-amber-50 text-amber-700' : (orderNotesOpen ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700')"
                class="shrink-0 inline-flex items-center justify-center h-6 w-6 rounded-md border transition-colors touch-manipulation"
                :title="hasOrderNotes() ? 'Edit order notes' : 'Add order notes'"
                :aria-expanded="orderNotesOpen">
                <i class="fas fa-sticky-note text-xs"></i>
            </button>
        </div>
        @include('pos.partials.fulfillment-order-notes')
        <div class="space-y-2 min-w-0 w-full max-w-full">
            @include('pos.partials.fulfillment-payment-fields', ['placement' => 'sidebar'])
        </div>
        @include('pos.partials.fulfillment-action-buttons', ['placement' => 'sidebar'])
    </div>
@else
    <div class="py-1.5 px-2 sm:px-2.5 overflow-x-auto lg:overflow-x-auto">
        <div class="flex flex-wrap lg:flex-nowrap items-end gap-2 min-w-0">
            @include('pos.partials.fulfillment-payment-fields', ['placement' => 'bar'])
            @include('pos.partials.fulfillment-action-buttons', ['placement' => 'bar'])
        </div>
    </div>
@endif
