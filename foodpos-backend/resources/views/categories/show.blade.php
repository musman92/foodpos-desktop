@extends('layouts.app')

@section('title', 'Category Details')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Category Details</h1>
            <p class="mt-1 text-sm text-gray-500">View complete information about this category</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('categories.edit', $category) }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-edit mr-2"></i>
                Edit Category
            </a>
            <a href="{{ route('categories.index') }}" 
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
        </div>
    </div>

    <!-- Category Information Card -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
            <div class="flex items-center">
                @if($category->image)
                    <div class="flex-shrink-0 h-20 w-20">
                        <img src="{{ Storage::url($category->image) }}" 
                             alt="{{ $category->name }}" 
                             class="h-20 w-20 rounded-lg object-cover">
                    </div>
                @else
                    <div class="flex-shrink-0 h-20 w-20">
                        <div class="h-20 w-20 rounded-lg bg-gradient-to-br from-purple-400 to-pink-500 flex items-center justify-center">
                            <i class="fas fa-folder text-white text-2xl"></i>
                        </div>
                    </div>
                @endif
                <div class="ml-4 flex-1">
                    <h2 class="text-2xl font-bold text-gray-900">{{ $category->name }}</h2>
                    <div class="mt-1 flex items-center">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $category->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="ml-3 text-sm text-gray-500">
                            Slug: {{ $category->slug }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">
            <!-- Category Information -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Category Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Code</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $category->code ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Category Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $category->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Slug</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $category->slug }}</dd>
                    </div>
                    @if($category->parent)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Parent Category</dt>
                            <dd class="mt-1">
                                <a href="{{ route('categories.show', $category->parent) }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $category->parent->displayLabel() }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if($category->children->count() > 0)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Subcategories</dt>
                            <dd class="mt-1">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    {{ $category->children->count() }} subcategories
                                </span>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Sort Order</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $category->sort_order }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $category->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </dd>
                    </div>
                </dl>
                    @if($category->description)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Description</dt>
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $category->description }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Subcategories -->
            @if($category->children->count() > 0)
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                        Subcategories
                        <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ $category->children->count() }} subcategories
                        </span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($category->children as $child)
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-900">
                                            <a href="{{ route('categories.show', $child) }}" class="text-indigo-600 hover:text-indigo-900">
                                                {{ $child->name }}
                                            </a>
                                        </h4>
                                        <p class="text-xs text-gray-500 mt-1">{{ $child->menuItems->count() }} items</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Menu Items -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                    Menu Items
                    <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                        {{ $category->menuItems->count() }} items
                    </span>
                </h3>
                @if($category->menuItems->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($category->menuItems as $menuItem)
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-900">{{ $menuItem->name }}</h4>
                                        <p class="text-xs text-gray-500 mt-1">{{ format_currency($menuItem->price) }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 border-2 border-dashed border-gray-300 rounded-lg">
                        <i class="fas fa-utensils text-gray-400 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-500">No menu items in this category yet</p>
                    </div>
                @endif
            </div>

            <!-- Timestamps -->
            <div class="pt-4 border-t border-gray-200">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Created At</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($category->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($category->updated_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

