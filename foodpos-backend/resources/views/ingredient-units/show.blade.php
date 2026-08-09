@extends('layouts.app')

@section('title', $ingredientUnit->name)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $ingredientUnit->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Ingredient unit details</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('ingredient-units.edit', $ingredientUnit) }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                <i class="fas fa-edit mr-2"></i>
                Edit
            </a>
            <a href="{{ route('ingredient-units.index') }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-gray-100 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200">
                Back to list
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Unit Information</h2>
        </div>
        <dl class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <dt class="text-sm font-medium text-gray-500">Code</dt>
                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $ingredientUnit->code ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Name</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $ingredientUnit->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Ingredients using this unit</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $ingredientUnit->linkedIngredientsCount() }}</dd>
            </div>
            <div class="md:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Description</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $ingredientUnit->description ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    @if($linkedIngredients->count() > 0)
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Linked Ingredients</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($linkedIngredients as $ingredient)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <a href="{{ route('ingredients.show', $ingredient) }}" class="text-indigo-600 hover:text-indigo-900">
                                        {{ $ingredient->name }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $ingredient->sku ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
