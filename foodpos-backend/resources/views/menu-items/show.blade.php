@extends('layouts.app')

@section('title', 'Menu Item Details')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Menu Item Details</h1>
            <p class="mt-1 text-sm text-gray-500">View complete information about this menu item</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('menu-items.edit', $menuItem) }}" 
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-edit mr-2"></i>
                Edit Menu Item
            </a>
            <a href="{{ route('menu-items.index') }}" 
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
        </div>
    </div>

    <!-- Menu Item Information Card -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
            <div class="flex items-center">
                @if($menuItem->image)
                    <div class="flex-shrink-0 h-24 w-24">
                        <img src="{{ $menuItem->resolvedImageUrl() }}" 
                             alt="{{ $menuItem->name }}" 
                             class="h-24 w-24 rounded-lg object-cover">
                    </div>
                @else
                    <div class="flex-shrink-0 h-24 w-24">
                        <div class="h-24 w-24 rounded-lg bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center">
                            <i class="fas fa-utensils text-white text-3xl"></i>
                        </div>
                    </div>
                @endif
                <div class="ml-6 flex-1">
                    <h2 class="text-2xl font-bold text-gray-900">{{ $menuItem->name }}</h2>
                    <div class="mt-2 flex items-center space-x-3">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $menuItem->type === 'recipe' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                            {{ ucfirst($menuItem->type) }}
                        </span>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $menuItem->is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $menuItem->is_available ? 'Available' : 'Unavailable' }}
                        </span>
                        <span class="text-2xl font-bold text-gray-900">
                            {{ format_currency($menuItem->price) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">
            <!-- Basic Information -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Basic Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $menuItem->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Category</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $menuItem->category?->displayLabel() ?? '—' }}</dd>
                    </div>
                    @if($menuItem->cuisine)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Cuisine</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $menuItem->cuisine->name }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Type</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $menuItem->type === 'recipe' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ ucfirst($menuItem->type) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Price</dt>
                        <dd class="mt-1 text-2xl font-bold text-gray-900">{{ format_currency($menuItem->price) }}</dd>
                    </div>
                    @if($menuItem->sku)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">SKU</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $menuItem->sku }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $menuItem->is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $menuItem->is_available ? 'Available' : 'Unavailable' }}
                            </span>
                        </dd>
                    </div>
                </dl>
                @if($menuItem->description)
                <div class="mt-4">
                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $menuItem->description }}</dd>
                </div>
                @endif
            </div>

            <!-- Recipe & profitability by variant -->
            @if($menuItem->type === 'recipe' && count($variantCosting) > 0)
            @php
                $storedCost = (float) $menuItem->cost;
                $defaultCost = $menuItem->calculateCost();
                $costIsStale = abs($defaultCost - $storedCost) > 0.01;
                $hasMultipleVariants = count($variantCosting) > 1 || $menuItem->variants->isNotEmpty();
            @endphp
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                    Recipe, Cost &amp; Margin
                </h3>

                @if($hasMultipleVariants)
                <div class="mb-6 overflow-x-auto">
                    <table class="listing-table min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Variant</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sale Price</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Food Cost</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gross Profit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Margin %</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($variantCosting as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        {{ $row['label'] }}
                                        @if($row['uses_fallback_recipe'])
                                            <span class="ml-1 text-xs font-normal text-amber-600">(uses Default recipe)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ format_currency($row['selling_price']) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ format_currency($row['recipe_cost']) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-semibold {{ $row['gross_margin'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ format_currency($row['gross_margin']) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right {{ ($row['margin_percent'] ?? 0) >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        @if($row['margin_percent'] !== null)
                                            {{ number_format($row['margin_percent'], 1) }}%
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                @foreach($variantCosting as $row)
                    <div class="{{ !$loop->first ? 'mt-8' : '' }}">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">{{ $row['label'] }}</h4>
                                @if($row['uses_fallback_recipe'])
                                    <p class="text-xs text-amber-600 mt-0.5">No variant-specific recipe — showing Default ingredients.</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                                <div>
                                    <span class="text-gray-500">Sale:</span>
                                    <span class="font-semibold text-gray-900 ml-1">{{ format_currency($row['selling_price']) }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Cost:</span>
                                    <span class="font-semibold text-gray-900 ml-1">{{ format_currency($row['recipe_cost']) }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Profit:</span>
                                    <span class="font-semibold ml-1 {{ $row['gross_margin'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ format_currency($row['gross_margin']) }}
                                        @if($row['margin_percent'] !== null)
                                            <span class="text-gray-500 font-normal">({{ number_format($row['margin_percent'], 1) }}%)</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>

                        @if($row['recipes']->isEmpty())
                            <p class="text-sm text-gray-500 italic px-1">No ingredients defined for this option.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="listing-table min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ingredient</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waste %</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Line Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($row['recipes'] as $recipe)
                                            <tr>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    {{ $recipe->ingredient?->displayLabel() ?? '—' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    {{ number_format($recipe->quantity, 2) }}
                                                    @if($recipe->stockEquivalentLabel())
                                                        <div class="text-xs text-gray-500 mt-0.5">{{ $recipe->stockEquivalentLabel() }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">
                                                    {{ $recipe->unit_name ?? $recipe->ingredient?->unit_name ?? '—' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($recipe->waste_percentage, 2) }}%</td>
                                                <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ format_currency($recipe->lineCost()) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="4" class="px-4 py-3 text-sm font-medium text-gray-700 text-right">Total food cost</td>
                                            <td class="px-4 py-3 text-sm font-bold text-gray-900 text-right">{{ format_currency($row['recipe_cost']) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach

                @if($costIsStale)
                    <p class="mt-4 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                        Stored cost ({{ format_currency($storedCost) }}) differs from current ingredient prices.
                        Re-save this menu item to update inventory valuation.
                    </p>
                @endif
                <p class="mt-3 text-xs text-gray-500">
                    Costs use live ingredient purchase prices with unit conversion and waste. Sale prices come from variant option pricing on this item.
                </p>
            </div>
            @endif

            <!-- Product Addons -->
            @if($menuItem->productAddons->count() > 0)
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Product Addons</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($menuItem->productAddons as $addon)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm font-medium text-gray-900">{{ $addon->name }}</span>
                            <span class="text-sm font-semibold text-gray-700">{{ format_currency($addon->price) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Timestamps -->
            <div class="pt-4 border-t border-gray-200">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Created At</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($menuItem->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($menuItem->updated_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

