@extends('layouts.app')

@section('title', 'Product Addon Details')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $productAddon->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Product addon details</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('product-addons.edit', $productAddon) }}" class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"><i class="fas fa-edit mr-2"></i>Edit</a>
            <a href="{{ route('product-addons.index') }}" class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"><i class="fas fa-arrow-left mr-2"></i>Back</a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-6 space-y-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><dt class="text-sm font-medium text-gray-500">Code</dt><dd class="mt-1 text-sm font-mono text-gray-900">{{ $productAddon->code ?? '—' }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500">Sale price</dt><dd class="mt-1 text-lg font-bold text-gray-900">{{ format_currency($productAddon->price) }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500">Cost price</dt><dd class="mt-1 text-lg font-semibold text-indigo-700">{{ format_currency($productAddon->cost ?? 0) }}</dd></div>
                <div><dt class="text-sm font-medium text-gray-500">Inventory</dt><dd class="mt-1 text-sm text-gray-900">@if($productAddon->track_inventory){{ ucfirst($productAddon->type) }} (tracked)@else None@endif</dd></div>
            </dl>

            @if($productAddon->type === 'single' && $productAddon->menuItem)
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-sm font-medium text-gray-700">Linked menu item</p>
                    <p class="mt-1 text-sm text-gray-900">{{ $productAddon->menuItem->name }} @if($productAddon->menuItem->sku)<span class="text-gray-500">({{ $productAddon->menuItem->sku }})</span>@endif</p>
                </div>
            @endif

            @if($productAddon->type === 'recipe' && $productAddon->recipes->count())
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Recipe ingredients</h3>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full text-sm divide-y divide-gray-200">
                            <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left">Ingredient</th><th class="px-4 py-2 text-left">Qty</th><th class="px-4 py-2 text-right">Line cost</th></tr></thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($productAddon->recipes as $recipe)
                                    <tr>
                                        <td class="px-4 py-2">{{ $recipe->ingredient?->name ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ $recipe->quantity }} {{ $recipe->unit_name ?? $recipe->unit_id }}</td>
                                        <td class="px-4 py-2 text-right">{{ format_currency($recipe->lineCost()) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
