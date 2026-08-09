@php
    $highlight = $highlight ?? false;
    $clickable = $clickable ?? false;
    $toneClasses = [
        'indigo' => ['border' => 'border-indigo-200', 'icon' => 'text-indigo-500', 'bg' => 'bg-indigo-50'],
        'emerald' => ['border' => 'border-emerald-200', 'icon' => 'text-emerald-500', 'bg' => 'bg-emerald-50'],
        'sky' => ['border' => 'border-sky-200', 'icon' => 'text-sky-500', 'bg' => 'bg-sky-50'],
        'violet' => ['border' => 'border-violet-200', 'icon' => 'text-violet-500', 'bg' => 'bg-violet-50'],
        'amber' => ['border' => 'border-amber-200', 'icon' => 'text-amber-500', 'bg' => 'bg-amber-50'],
        'orange' => ['border' => 'border-orange-200', 'icon' => 'text-orange-500', 'bg' => 'bg-orange-50'],
    ];
    $colors = $toneClasses[$tone] ?? $toneClasses['indigo'];
    $baseClasses = 'relative bg-white rounded-xl border px-3 py-2.5 shadow-sm text-left w-full '
        .($highlight ? 'border-indigo-300 ring-1 ring-indigo-100' : 'border-gray-200')
        .($clickable ? ' cursor-pointer hover:border-emerald-300 hover:ring-1 hover:ring-emerald-100 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500' : '');
@endphp
@if($clickable)
<button type="button"
        class="{{ $baseClasses }}"
        @isset($clickAction) @click="{{ $clickAction }}" @endisset
        @isset($ariaControls) aria-controls="{{ $ariaControls }}" @endisset
        aria-haspopup="dialog">
@else
<div class="{{ $baseClasses }}">
@endif
    <div class="absolute top-2 right-2 h-6 w-6 rounded-md {{ $colors['bg'] }} flex items-center justify-center pointer-events-none">
        <i class="fas {{ $icon }} {{ $colors['icon'] }} text-[10px]"></i>
    </div>
    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 leading-none pr-7">
        {{ $label }}
        @if($clickable)
            <span class="normal-case tracking-normal font-normal text-emerald-600 ml-1">Details</span>
        @endif
    </p>
    <p class="mt-1 text-base sm:text-lg font-bold text-gray-900 tabular-nums leading-tight" title="{{ $value }}">{{ $value }}</p>
@if($clickable)
</button>
@else
</div>
@endif
