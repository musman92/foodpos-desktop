@extends('layouts.app')

@section('title', 'Gross Margin Report')

@section('content')
@php
    use App\Support\GrossMarginReport;
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Gross Margin Report</h1>
        <p class="mt-1 text-sm text-gray-500">Sale price, recipe cost, and gross margin for each menu item.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Items</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($summary['item_count']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Avg. Margin</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">
                {{ $summary['avg_margin_percent'] !== null ? number_format($summary['avg_margin_percent'], 1).'%' : '—' }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Below Cost</p>
            <p class="mt-1 text-2xl font-bold {{ $summary['negative_margin_count'] > 0 ? 'text-red-600' : 'text-gray-900' }}">
                {{ number_format($summary['negative_margin_count']) }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Stale Costs</p>
            <p class="mt-1 text-2xl font-bold {{ $summary['stale_cost_count'] > 0 ? 'text-amber-600' : 'text-gray-900' }}">
                {{ number_format($summary['stale_cost_count']) }}
            </p>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-4 sm:p-6">
        <form method="GET" action="{{ route('reports.gross-margin') }}" class="space-y-4">
            <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
            <input type="hidden" name="dir" value="{{ $filters['dir'] }}">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search by name</label>
                    <input type="search"
                           name="search"
                           id="search"
                           value="{{ $filters['search'] }}"
                           placeholder="Type to search…"
                           class="block w-full filter-control">
                </div>
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category_id" id="category_id" class="block w-full filter-control">
                        <option value="">All categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>
                                {{ $category->displayLabel() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Item type</label>
                    <select name="type" id="type" class="block w-full filter-control">
                        <option value="all" @selected($filters['type'] === 'all')>All types</option>
                        <option value="recipe" @selected($filters['type'] === 'recipe')>Recipe</option>
                        <option value="single" @selected($filters['type'] === 'single')>Single</option>
                    </select>
                </div>
                <div>
                    <label for="availability" class="block text-sm font-medium text-gray-700 mb-1">Availability</label>
                    <select name="availability" id="availability" class="block w-full filter-control">
                        <option value="all" @selected($filters['availability'] === 'all')>All</option>
                        <option value="available" @selected($filters['availability'] === 'available')>Available</option>
                        <option value="unavailable" @selected($filters['availability'] === 'unavailable')>Unavailable</option>
                    </select>
                </div>
                <div>
                    <label for="min_margin" class="block text-sm font-medium text-gray-700 mb-1">Min margin %</label>
                    <input type="number"
                           name="min_margin"
                           id="min_margin"
                           value="{{ $filters['min_margin'] }}"
                           step="0.1"
                           min="0"
                           max="100"
                           placeholder="e.g. 20"
                           class="block w-full filter-control">
                </div>
                <div>
                    <label for="max_margin" class="block text-sm font-medium text-gray-700 mb-1">Max margin %</label>
                    <input type="number"
                           name="max_margin"
                           id="max_margin"
                           value="{{ $filters['max_margin'] }}"
                           step="0.1"
                           min="0"
                           max="100"
                           placeholder="e.g. 80"
                           class="block w-full filter-control">
                </div>
                <div>
                    <label for="per_page" class="block text-sm font-medium text-gray-700 mb-1">Per page</label>
                    <select name="per_page" id="per_page" class="block w-full filter-control">
                        @foreach([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit"
                        class="inline-flex items-center justify-center h-11 px-4 rounded-lg bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <i class="fas fa-filter mr-2"></i>
                    Apply
                </button>
                <a href="{{ route('reports.gross-margin') }}"
                   class="inline-flex items-center justify-center h-11 px-4 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ GrossMarginReport::sortUrl(request(), 'name') }}" class="inline-flex items-center gap-1 hover:text-gray-700">
                                Item <i class="fas {{ GrossMarginReport::sortIcon(request(), 'name') }}"></i>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ GrossMarginReport::sortUrl(request(), 'category') }}" class="inline-flex items-center gap-1 hover:text-gray-700">
                                Category <i class="fas {{ GrossMarginReport::sortIcon(request(), 'category') }}"></i>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ GrossMarginReport::sortUrl(request(), 'type') }}" class="inline-flex items-center gap-1 hover:text-gray-700">
                                Type <i class="fas {{ GrossMarginReport::sortIcon(request(), 'type') }}"></i>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ GrossMarginReport::sortUrl(request(), 'price') }}" class="inline-flex items-center gap-1 hover:text-gray-700 justify-end w-full">
                                Sale Price <i class="fas {{ GrossMarginReport::sortIcon(request(), 'price') }}"></i>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ GrossMarginReport::sortUrl(request(), 'cost') }}" class="inline-flex items-center gap-1 hover:text-gray-700 justify-end w-full">
                                Cost <i class="fas {{ GrossMarginReport::sortIcon(request(), 'cost') }}"></i>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ GrossMarginReport::sortUrl(request(), 'margin') }}" class="inline-flex items-center gap-1 hover:text-gray-700 justify-end w-full">
                                Gross Margin <i class="fas {{ GrossMarginReport::sortIcon(request(), 'margin') }}"></i>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ GrossMarginReport::sortUrl(request(), 'margin_percent') }}" class="inline-flex items-center gap-1 hover:text-gray-700 justify-end w-full">
                                Margin % <i class="fas {{ GrossMarginReport::sortIcon(request(), 'margin_percent') }}"></i>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ GrossMarginReport::sortUrl(request(), 'status') }}" class="inline-flex items-center gap-1 hover:text-gray-700">
                                Status <i class="fas {{ GrossMarginReport::sortIcon(request(), 'status') }}"></i>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($rows as $row)
                        @php $item = $row['menu_item']; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('menu-items.show', $item) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                    {{ $item->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row['category_name'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $item->type === 'recipe' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ ucfirst($item->type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ format_currency($row['price']) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-900">
                                {{ format_currency($row['cost']) }}
                                @if($row['cost_is_stale'])
                                    <span class="block text-xs text-amber-600" title="Re-save menu item to update stored cost">stale</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium {{ $row['margin'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ format_currency($row['margin']) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-semibold {{ ($row['margin_percent'] ?? 0) >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $row['margin_percent'] !== null ? number_format($row['margin_percent'], 1).'%' : '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $item->is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $item->is_available ? 'Available' : 'Unavailable' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">No menu items match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rows->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $rows->links() }}
            </div>
        @endif
    </div>

    <p class="text-xs text-gray-500">
        Cost is calculated from current ingredient prices (including waste and unit conversion). Click column headers to sort.
    </p>
</div>
@endsection
