@extends('layouts.app')

@section('title', 'Menu Items')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Menu Items</h1>
            <p class="mt-1 text-sm text-gray-500">Manage your menu items and products</p>
        </div>
        <div class="flex items-center gap-3">
            @include('partials.menu-item-export-action')
            <a href="{{ route('menu-items.import') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-file-import mr-2"></i>
                Import
            </a>
            <a href="{{ route('menu-items.create') }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-plus mr-2"></i>
                Add Menu Item
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-4 sm:p-6">
        <form method="GET" action="{{ route('menu-items.index') }}" class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end">
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search by name</label>
                <input type="search"
                       name="search"
                       id="search"
                       value="{{ $search }}"
                       placeholder="Type to search…"
                       class="block w-full filter-control">
            </div>
            <div class="w-full sm:w-64">
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category_id"
                        id="category_id"
                        class="block w-full filter-control">
                    <option value="">All categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) ($categoryId ?? '') === (string) $category->id)>
                                {{ $category->displayLabel() }}
                            </option>
                        @endforeach
                </select>
            </div>
            <div class="w-full sm:w-48">
                <label for="available" class="block text-sm font-medium text-gray-700 mb-1">Available</label>
                <select name="available"
                        id="available"
                        class="block w-full filter-control">
                    <option value="" @selected(($availability ?? null) === null)>All</option>
                    <option value="1" @selected(($availability ?? null) === true)>Available</option>
                    <option value="0" @selected(($availability ?? null) === false)>Unavailable</option>
                </select>
            </div>
            <div class="w-full sm:w-52">
                <label for="track_inventory" class="block text-sm font-medium text-gray-700 mb-1">Track inventory</label>
                <select name="track_inventory"
                        id="track_inventory"
                        class="block w-full filter-control">
                    <option value="" @selected(($trackInventory ?? null) === null)>All</option>
                    <option value="1" @selected(($trackInventory ?? null) === true)>Tracking</option>
                    <option value="0" @selected(($trackInventory ?? null) === false)>Not tracking</option>
                </select>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit"
                        class="inline-flex items-center justify-center h-11 px-4 rounded-lg bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <i class="fas fa-filter mr-2"></i>
                    Apply
                </button>
                <a href="{{ route('menu-items.index') }}"
                   class="inline-flex items-center justify-center h-11 px-4 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Menu Items Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('menu-items.index'),
            'paginator' => $menuItems,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Item</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Category</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Type</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Price</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Inventory</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($menuItems as $menuItem)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $menuItems->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($menuItem->image)
                                        <div class="flex-shrink-0 h-12 w-12">
                                            <img src="{{ $menuItem->resolvedImageUrl() }}" 
                                                 alt="{{ $menuItem->name }}" 
                                                 class="h-12 w-12 rounded-lg object-cover">
                                        </div>
                                    @else
                                        <div class="flex-shrink-0 h-12 w-12">
                                            <div class="h-12 w-12 rounded-lg bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center">
                                                <i class="fas fa-utensils text-white text-lg"></i>
                                            </div>
                                        </div>
                                    @endif
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $menuItem->name }}
                                        </div>
                                        <div class="text-sm text-gray-500 line-clamp-1">
                                            {{ Str::limit($menuItem->description, 50) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="text-sm text-gray-900">
                                    {{ $menuItem->category->displayLabel() }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $menuItem->type === 'recipe' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ ucfirst($menuItem->type) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums font-medium">
                                {{ format_currency($menuItem->price) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $menuItem->is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $menuItem->is_available ? 'Available' : 'Unavailable' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $menuItem->track_inventory ? 'bg-sky-100 text-sky-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $menuItem->track_inventory ? 'Tracking' : 'Off' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('menu-items.show', $menuItem) }}" 
                                       class="text-indigo-600 hover:text-indigo-900" 
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('menu-items.edit', $menuItem) }}" 
                                       class="text-blue-600 hover:text-blue-900" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('menu-items.duplicate', $menuItem) }}"
                                          method="POST"
                                          class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="text-gray-600 hover:text-gray-900"
                                                title="Duplicate">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('menu-items.destroy', $menuItem) }}" 
                                          method="POST" 
                                          class="inline"
                                          onsubmit="return confirm('Are you sure you want to delete this menu item?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="text-red-600 hover:text-red-900" 
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-utensils text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No menu items found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new menu item.</p>
                                    <a href="{{ route('menu-items.create') }}" 
                                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Menu Item
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $menuItems])
    </div>
</div>
@endsection

