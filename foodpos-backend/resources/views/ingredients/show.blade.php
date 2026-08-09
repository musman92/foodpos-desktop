@extends('layouts.app')

@section('title', 'Ingredient Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ingredient Details</h1>
            <p class="mt-1 text-sm text-gray-500">View complete information about this ingredient</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('ingredients.edit', $ingredient) }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-edit mr-2"></i>
                Edit Ingredient
            </a>
            <a href="{{ route('ingredients.index') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 h-16 w-16">
                    <div class="h-16 w-16 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center">
                        <i class="fas fa-leaf text-white text-xl"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <h2 class="text-2xl font-bold text-gray-900">{{ $ingredient->name }}</h2>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                        @if($ingredient->sku)
                            <span>Code: {{ $ingredient->sku }}</span>
                        @endif
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $ingredient->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $ingredient->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Ingredient Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Category</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ingredient->category?->displayLabel() ?? 'Uncategorized' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Purchase unit</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ingredient->purchaseUnit?->displayLabel() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Consumption unit</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ingredient->consumptionUnit?->displayLabel() ?? $ingredient->unit_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Conversion</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ number_format((float) $ingredient->conversion_rate, 4) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Purchase price</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ format_currency($ingredient->purchase_price) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Cost per consumption unit</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ format_currency($ingredient->cost_per_unit) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Low qty alert</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ number_format((float) $ingredient->min_stock_level, 2) }}</dd>
                    </div>
                    @if($ingredient->creator)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created by</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $ingredient->creator->name }}</dd>
                        </div>
                    @endif
                    @if($ingredient->description)
                        <div class="md:col-span-3">
                            <dt class="text-sm font-medium text-gray-500">Description</dt>
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $ingredient->description }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="pt-4 border-t border-gray-200">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Created At</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($ingredient->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($ingredient->updated_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
