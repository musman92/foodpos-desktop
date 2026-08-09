@php
    $placement = $placement ?? 'bar';
    $actions = \App\Support\PosLayout::visibleFulfillmentActions();
    $btnBaseClass = $placement === 'sidebar'
        ? 'h-9 w-full px-1.5 whitespace-nowrap rounded-md font-semibold transition-colors text-[11px] touch-manipulation active:opacity-90 disabled:opacity-50'
        : 'h-10 sm:h-9 min-h-[44px] sm:min-h-0 flex-1 sm:flex-none px-2.5 sm:px-3 whitespace-nowrap rounded-md font-semibold transition-colors text-[11px] sm:text-xs touch-manipulation active:opacity-90';
@endphp

@if ($placement === 'sidebar')
    @php
        $compactActions = array_values(array_filter($actions, fn (array $action) => empty($action['sidebar_full_width'])));
        $fullWidthActions = array_values(array_filter($actions, fn (array $action) => ! empty($action['sidebar_full_width'])));
    @endphp
    <div class="flex flex-col gap-1.5 w-full min-w-0">
        @if (count($compactActions) > 0)
            <div class="grid grid-cols-3 gap-1.5 w-full min-w-0">
                @foreach ($compactActions as $action)
                    <button
                        type="button"
                        @click="runPosFulfillment('{{ $action['mode'] }}')"
                        :disabled="!canSubmitFulfillmentAction('{{ $action['mode'] }}')"
                        :class="fulfillmentButtonClass('{{ $action['mode'] }}', '{{ $action['enabled_class'] }}')"
                        class="{{ $btnBaseClass }}">
                        <i class="fas {{ $action['icon'] }} mr-0.5 text-[10px]"></i>{{ $action['short_label'] }}
                    </button>
                @endforeach
            </div>
        @endif
        @foreach ($fullWidthActions as $action)
            <button
                type="button"
                @click="runPosFulfillment('{{ $action['mode'] }}')"
                :disabled="!canSubmitFulfillmentAction('{{ $action['mode'] }}')"
                :class="fulfillmentButtonClass('{{ $action['mode'] }}', '{{ $action['enabled_class'] }}')"
                class="h-10 w-full px-3 whitespace-nowrap rounded-md font-semibold transition-colors text-sm touch-manipulation active:opacity-90 disabled:opacity-50">
                <i class="fas {{ $action['icon'] }} mr-1 text-[10px]"></i>{{ $action['short_label'] }}
            </button>
        @endforeach
    </div>
@else
    <div class="flex shrink-0 flex-wrap gap-1.5 sm:gap-2 w-full sm:w-auto justify-stretch sm:justify-start">
        @foreach ($actions as $action)
            <button
                type="button"
                @click="runPosFulfillment('{{ $action['mode'] }}')"
                :disabled="!canSubmitFulfillmentAction('{{ $action['mode'] }}')"
                :class="fulfillmentButtonClass('{{ $action['mode'] }}', '{{ $action['enabled_class'] }}')"
                class="{{ $btnBaseClass }}">
                <i class="fas {{ $action['icon'] }} mr-1 text-[10px]"></i>
                @if ($action['mode'] === 'kot_bill')
                    <span class="hidden sm:inline">KOT+</span>Print
                @elseif ($action['mode'] === 'kot_bill_pay')
                    <span class="hidden min-[400px]:inline">KOT+Print+</span>Pay
                @else
                    {{ $action['label'] }}
                @endif
            </button>
        @endforeach
    </div>
@endif
