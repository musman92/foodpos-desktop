@extends('layouts.app')

@section('title', 'Variant Details')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $variant->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Variant details and usage</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('variants.edit', $variant) }}" 
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                <i class="fas fa-edit mr-2"></i>
                Edit
            </a>
            <a href="{{ route('variants.index') }}" 
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back
            </a>
        </div>
    </div>

    <!-- Variant Information -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Variant Information</h2>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Name</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $variant->name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Code</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $variant->code ?? '-' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Status</label>
                    <p class="mt-1">
                        @if($variant->is_active)
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                Active
                            </span>
                        @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                Inactive
                            </span>
                        @endif
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Sort Order</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $variant->sort_order }}</p>
                </div>
            </div>
            @if($variant->description)
                <div>
                    <label class="block text-sm font-medium text-gray-500">Description</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $variant->description }}</p>
                </div>
            @endif
        </div>
    </div>

    @if(is_array($variant->options) && count($variant->options) > 0)
        <div class="bg-white shadow rounded-xl overflow-hidden border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-slate-50 to-indigo-50/40">
                <h2 class="text-lg font-semibold text-gray-900">Options & default prices</h2>
                <p class="mt-1 text-sm text-gray-500">Used to prefill menu items. Each menu item can override these.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/80">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-12">#</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Option</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Default price</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-20">Order</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach($variant->options as $index => $option)
                            <tr class="hover:bg-indigo-50/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">{{ $index + 1 }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $option['name'] ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-indigo-700">{{ $option['code'] ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 tabular-nums">
                                    @if(isset($option['price']) && $option['price'] !== null && $option['price'] !== '')
                                        {{ format_currency($option['price']) }}
                                    @else
                                        <span class="text-gray-400 italic">Menu base price</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 tabular-nums">{{ $option['sort_order'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Menu Items Using This Variant -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Menu Items Using This Variant</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Menu Item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($variant->menuItems as $menuItem)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('menu-items.show', $menuItem) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                    {{ $menuItem->name }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold text-gray-900">
                                    {{ format_currency($menuItem->pivot->price) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($menuItem->pivot->is_default)
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Yes
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400">No</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-sm text-gray-500">
                                No menu items are using this variant yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
