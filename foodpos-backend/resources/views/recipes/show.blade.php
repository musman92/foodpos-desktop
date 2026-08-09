@extends('layouts.app')

@section('title', 'Recipe Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $recipe->name }}</h1>
            <p class="text-sm text-gray-500 mt-1 font-mono">{{ $recipe->code ?? '—' }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('recipes.edit', $recipe) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Edit</a>
            <a href="{{ route('recipes.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Back</a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-6 space-y-4">
        <div>
            <p class="text-sm text-gray-500">Description</p>
            <p class="text-gray-900">{{ $recipe->description ?: '—' }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-2">Estimated cost</p>
            <p class="text-xl font-bold text-indigo-700">{{ format_currency($recipe->calculateCost()) }}</p>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Ingredients</h2>
        </div>
        <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ingredient</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Waste %</th>
                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Line cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($recipe->items as $item)
                    <tr>
                        <td class="px-3 py-3 whitespace-nowrap font-mono text-gray-600">{{ $item->ingredient?->sku ?: '—' }}</td>
                        <td class="px-3 py-3 font-medium text-gray-900">{{ $item->ingredient?->name ?? '—' }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $item->quantity }}</td>
                        <td class="px-3 py-3 text-gray-600">{{ $item->unit_name ?? $item->unit_id ?? '—' }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ $item->waste_percentage }}</td>
                        <td class="px-3 py-3 text-right tabular-nums">{{ format_currency($item->lineCost()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php
        $asDefault = $recipe->menuItemsAsDefault;
        $asOption = $recipe->variantOptionLinks;
    @endphp
    @if($asDefault->isNotEmpty() || $asOption->isNotEmpty())
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Used by</h2>
            <ul class="space-y-2 text-sm text-gray-700">
                @foreach($asDefault as $mi)
                    <li>
                        <a href="{{ route('menu-items.show', $mi) }}" class="text-indigo-600 hover:underline">{{ $mi->name }}</a>
                        <span class="text-gray-400">(default)</span>
                    </li>
                @endforeach
                @foreach($asOption as $link)
                    <li>
                        <a href="{{ route('menu-items.show', $link->menuItem) }}" class="text-indigo-600 hover:underline">{{ $link->menuItem?->name }}</a>
                        <span class="text-gray-400">({{ $link->option_name }})</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
