@extends('layouts.app')

@section('title', 'Product Categories')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Product Categories</h1>
            <p class="mt-1 text-sm text-gray-500">Organize your menu items into categories</p>
        </div>
        <div class="flex items-center gap-3">
            @include('partials.catalog-export-actions', ['routeName' => 'categories.export'])
            <a href="{{ route('categories.import') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-file-import mr-2"></i>
                Import
            </a>
            <a href="{{ route('categories.create') }}" 
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-plus mr-2"></i>
                Add Category
            </a>
        </div>
    </div>

    @include('partials.global-tenant-listing-filters', [
        'action' => route('categories.index'),
        'search' => $search ?? '',
        'searchPlaceholder' => 'Search by name, code, slug, or description…',
    ])

    <!-- Categories Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('categories.index'),
            'paginator' => $categories,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Category</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Description</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Menu Items</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Sort Order</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($categories as $category)
                        <!-- Parent Category Row -->
                        <tr class="border-l-2 border-indigo-200">
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $categories->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($category->image)
                                        <div class="flex-shrink-0 h-12 w-12">
                                            <img src="{{ Storage::url($category->image) }}" 
                                                 alt="{{ $category->name }}" 
                                                 class="h-12 w-12 rounded-lg object-cover">
                                        </div>
                                    @else
                                        <div class="flex-shrink-0 h-12 w-12">
                                            <div class="h-12 w-12 rounded-lg bg-gradient-to-br from-purple-400 to-pink-500 flex items-center justify-center">
                                                <i class="fas fa-folder text-white text-lg"></i>
                                            </div>
                                        </div>
                                    @endif
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                            @if($category->code)
                                                <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-200 text-gray-700">{{ $category->code }}</span>
                                            @endif
                                            <span>{{ $category->name }}</span>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $category->slug }}
                                            @if($category->children->count() > 0)
                                                <span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                                    {{ $category->children->count() }} subcategories
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <p class="text-sm text-gray-500 line-clamp-2 max-w-xs">
                                    {{ $category->description ?? 'No description' }}
                                </p>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex flex-col gap-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $category->menuItems->count() }} items
                                    </span>
                                    @if($category->children->count() > 0)
                                        @php
                                            $totalItems = $category->menuItems->count();
                                            foreach($category->children as $child) {
                                                $totalItems += $child->menuItems->count();
                                            }
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $totalItems }} total (including subcategories)
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                                {{ $category->sort_order }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('categories.show', $category) }}" 
                                       class="text-indigo-600 hover:text-indigo-900" 
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('categories.edit', $category) }}"
                                       class="text-blue-600 hover:text-blue-900"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('categories.destroy', $category) }}"
                                          method="POST"
                                          class="inline"
                                          onsubmit="return confirm('Are you sure you want to delete this category? This will not delete the menu items, but they will be uncategorized.');">
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
                        <!-- Child Categories -->
                        @if($category->children->count() > 0)
                            @foreach($category->children as $child)
                                <tr>
                                    <td class="px-3 py-3"></td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8"></div> <!-- Indentation spacer -->
                                            <i class="fas fa-arrow-right text-gray-400 mr-2"></i>
                                            @if($child->image)
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <img src="{{ Storage::url($child->image) }}" 
                                                         alt="{{ $child->name }}" 
                                                         class="h-10 w-10 rounded-lg object-cover">
                                                </div>
                                            @else
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-purple-300 to-pink-400 flex items-center justify-center">
                                                        <i class="fas fa-folder text-white text-sm"></i>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-700 flex items-center gap-2">
                                                    @if($child->code)
                                                        <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-200 text-gray-600">{{ $child->code }}</span>
                                                    @endif
                                                    <span>{{ $child->name }}</span>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ $child->slug }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="text-sm text-gray-500 line-clamp-2 max-w-xs">
                                            {{ $child->description ?? 'No description' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ $child->menuItems->count() }} items
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ $child->sort_order }}
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $child->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $child->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('categories.show', $child) }}" 
                                               class="text-indigo-600 hover:text-indigo-900" 
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('categories.edit', $child) }}"
                                               class="text-blue-600 hover:text-blue-900"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('categories.destroy', $child) }}"
                                                  method="POST"
                                                  class="inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this category? This will not delete the menu items, but they will be uncategorized.');">
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
                            @endforeach
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-folder-open text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No categories found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new category.</p>
                                    <a href="{{ route('categories.create') }}" 
                                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Category
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $categories])
    </div>
</div>
@endsection

