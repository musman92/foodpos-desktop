@props([
    'label' => null,
    'required' => false,
    'compact' => false,
    'useButtonOptions' => false,
])

@php
    $inputClass = $compact
        ? 'block w-full filter-control text-gray-900 placeholder-gray-500'
        : 'block w-full h-12 px-4 pr-10 rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:cursor-not-allowed';
@endphp

<div @class(['space-y-1' => $label !== null])>
    @if($label)
        <label {{ $labelAttributes ?? '' }} class="block text-sm font-medium text-gray-700 mb-2 min-h-[1.25rem]">
            {!! $label !!}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <div class="relative" @if(!$useButtonOptions) @click.outside="isOpen = false" @endif>
        {{ $hiddenInput ?? '' }}

        <input type="text"
               x-model="searchQuery"
               @input="onSearchInput()"
               @focus="if (!isDisabled) { isOpen = true }"
               @blur="onBlur()"
               @keydown.escape="isOpen = false"
               @keydown.arrow-down.prevent="highlightNext()"
               @keydown.arrow-up.prevent="highlightPrevious()"
               @keydown.enter.prevent="selectHighlighted()"
               :placeholder="placeholder"
               :disabled="isDisabled"
               autocomplete="off"
               {{ $attributes->except(['label', 'required', 'compact', 'useButtonOptions'])->merge(['class' => $inputClass]) }}>

        @unless($compact)
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <i class="fas fa-chevron-down text-gray-400"></i>
            </div>
        @endunless

        <div x-show="isOpen && !isDisabled"
             x-cloak
             x-transition
             class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto py-1">
            <template x-if="filteredOptions.length === 0">
                <div class="px-4 py-3 text-sm text-gray-500" x-text="emptyMessage"></div>
            </template>

            @if($useButtonOptions)
                <template x-for="option in filteredOptions" :key="option.id">
                    <button type="button"
                            class="flex w-full min-h-[2.75rem] items-center px-3 text-left text-sm text-gray-900 hover:bg-indigo-50"
                            @mousedown.prevent="selectRow(option)"
                            x-text="optionLabel(option)"></button>
                </template>
            @else
                <template x-for="(option, optIndex) in filteredOptions" :key="option.id">
                    <div @click="selectRow(option)"
                         @mouseenter="highlightedIndex = optIndex"
                         :class="{
                             'bg-indigo-50 text-indigo-900': highlightedIndex === optIndex,
                             'bg-white text-gray-900': highlightedIndex !== optIndex
                         }"
                         class="px-4 py-2 cursor-pointer hover:bg-indigo-50">
                        <span x-text="optionLabel(option)"></span>
                    </div>
                </template>
            @endif
        </div>
    </div>

    @if(isset($footer))
        {{ $footer }}
    @endif
</div>
