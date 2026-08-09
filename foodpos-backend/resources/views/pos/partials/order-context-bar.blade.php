@php
    $orderContextStyle = $orderContextStyle
        ?? ($posLayoutConfig['order_context_style'] ?? \App\Support\PosLayout::ORDER_CONTEXT_LABELED);
@endphp

@if ($orderContextStyle === \App\Support\PosLayout::ORDER_CONTEXT_COMPACT)
    @include('pos.partials.order-context-bar-compact')
@else
    @include('pos.partials.order-context-bar-labeled')
@endif
