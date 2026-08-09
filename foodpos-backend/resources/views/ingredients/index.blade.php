@extends('layouts.app')

@section('title', 'Ingredients')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ingredients</h1>
            <p class="mt-1 text-sm text-gray-500">Manage ingredients for your recipes and inventory</p>
        </div>
        <div class="flex items-center gap-3">
            @include('partials.catalog-export-actions', ['routeName' => 'ingredients.export'])
            <a href="{{ route('ingredients.import') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-file-import mr-2"></i>
                Import
            </a>
            <a href="{{ route('ingredients.create') }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-plus mr-2"></i>
                Add Ingredient
            </a>
        </div>
    </div>

    @include('partials.global-tenant-listing-filters', [
        'action' => route('ingredients.index'),
        'search' => $search ?? '',
        'searchPlaceholder' => 'Search by name or code…',
    ])

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('ingredients.index'),
            'paginator' => $ingredients,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Code</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Category</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Purchase unit</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Consumption unit</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Conversion</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Purchase price</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Cost / unit</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Low qty</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Added by</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($ingredients as $ingredient)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $ingredients->firstItem() + $loop->index }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">
                                {{ $ingredient->sku ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                <span class="font-medium">{{ $ingredient->name }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $ingredient->category?->displayLabel() ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                {{ $ingredient->purchaseUnit?->displayLabel() ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                {{ $ingredient->consumptionUnit?->displayLabel() ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">
                                @php
                                    $rate = (float) $ingredient->conversion_rate;
                                    echo fmod($rate, 1.0) === 0.0 ? number_format($rate, 0) : rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.');
                                @endphp
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">
                                {{ number_format((float) $ingredient->purchase_price, 2) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">
                                {{ number_format((float) $ingredient->cost_per_unit, 2) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">
                                @php
                                    $low = (float) $ingredient->min_stock_level;
                                    echo fmod($low, 1.0) === 0.0 ? number_format($low, 0) : rtrim(rtrim(number_format($low, 2, '.', ''), '0'), '.');
                                @endphp
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $ingredient->creator?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('ingredients.edit', $ingredient) }}"
                                       class="text-blue-600 hover:text-blue-800"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('ingredients.destroy', $ingredient) }}"
                                          method="POST"
                                          class="inline"
                                          onsubmit="return confirm('Are you sure you want to delete this ingredient?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-600 hover:text-red-800"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-leaf text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No ingredients found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new ingredient.</p>
                                    <a href="{{ route('ingredients.create') }}"
                                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Ingredient
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $ingredients])
    </div>
</div>
@endsection
