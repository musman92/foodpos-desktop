@extends('layouts.app')

@section('title', 'Ingredient Category Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ingredient Category Details</h1>
            <p class="mt-1 text-sm text-gray-500">View complete information about this category</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('ingredient-categories.edit', $ingredientCategory) }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-edit mr-2"></i>
                Edit Category
            </a>
            <a href="{{ route('ingredient-categories.index') }}" 
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
        </div>
    </div>

    <!-- Category Information Card -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-teal-50 to-cyan-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 h-16 w-16">
                    <div class="h-16 w-16 rounded-full bg-gradient-to-br from-teal-400 to-cyan-500 flex items-center justify-center">
                        <i class="fas fa-tags text-white text-xl"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <h2 class="text-2xl font-bold text-gray-900">{{ $ingredientCategory->name }}</h2>
                    <div class="mt-1 flex items-center">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $ingredientCategory->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $ingredientCategory->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="ml-3 px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                            {{ $ingredientCategory->ingredients->count() }} ingredients
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
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $ingredientCategory->code ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Category Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ingredientCategory->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Sort Order</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ingredientCategory->sort_order }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $ingredientCategory->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $ingredientCategory->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </dd>
                    </div>
                    @if($ingredientCategory->description)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Description</dt>
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $ingredientCategory->description }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Ingredients -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                    Ingredients
                    <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                        {{ $ingredientCategory->ingredients->count() }} ingredients
                    </span>
                </h3>
                @if($ingredientCategory->ingredients->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($ingredientCategory->ingredients as $ingredient)
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-900">
                                            <a href="{{ route('ingredients.show', $ingredient) }}" class="text-indigo-600 hover:text-indigo-900">
                                                {{ $ingredient->name }}
                                            </a>
                                        </h4>
                                        @if($ingredient->base_unit_id)
                                            <p class="text-xs text-gray-500 mt-1">Unit: {{ $ingredient->unit_name }} ({{ $ingredient->base_unit_id }})</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 border-2 border-dashed border-gray-300 rounded-lg">
                        <i class="fas fa-leaf text-gray-400 text-3xl mb-2"></i>
                        <p class="text-sm text-gray-500">No ingredients in this category yet</p>
                    </div>
                @endif
            </div>

            <!-- Timestamps -->
            <div class="pt-4 border-t border-gray-200">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Created At</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($ingredientCategory->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($ingredientCategory->updated_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

