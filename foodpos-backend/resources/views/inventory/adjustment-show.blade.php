@extends('layouts.app')

@section('title', 'Adjustment #'.$stockMovement->id)

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Adjustment detail</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $stockMovement->created_at->format('Y-m-d H:i') }}</p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="{{ route('inventory.adjustment.index') }}" class="inline-flex items-center justify-center h-11 px-4 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Back to list</a>
            <a href="{{ route('inventory.adjustment.edit', $stockMovement) }}" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Edit</a>
            <form action="{{ route('inventory.adjustment.destroy', $stockMovement) }}"
                  method="POST"
                  class="inline"
                  onsubmit="return confirm('Delete this adjustment? Stock will reverse to undo this change.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center justify-center h-11 px-4 border border-red-300 text-sm font-medium rounded-lg text-red-700 hover:bg-red-50">Delete</button>
            </form>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg border border-gray-100 divide-y divide-gray-200">
        <dl class="px-6 py-4 grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 text-sm">
            <dt class="text-gray-500 font-medium">Branch</dt>
            <dd class="sm:col-span-2 text-gray-900">{{ $stockMovement->branch->name ?? '—' }}</dd>
            <dt class="text-gray-500 font-medium">Item</dt>
            <dd class="sm:col-span-2 text-gray-900">
                @if($stockMovement->ingredient_id)
                    <span class="text-gray-500 text-xs font-medium uppercase tracking-wide">Ingredient</span>
                    <span class="block sm:inline sm:ml-1">{{ $stockMovement->ingredient->name ?? '—' }}</span>
                @elseif($stockMovement->menu_item_id)
                    <span class="text-gray-500 text-xs font-medium uppercase tracking-wide">Menu item</span>
                    <span class="block sm:inline sm:ml-1">{{ $stockMovement->menuItem->name ?? '—' }}</span>
                @else
                    —
                @endif
            </dd>
            <dt class="text-gray-500 font-medium">Direction</dt>
            <dd class="sm:col-span-2">
                @if($stockMovement->movement === 'in')
                    <span class="text-green-700 font-medium">Increase stock</span>
                @else
                    <span class="text-amber-800 font-medium">Decrease stock</span>
                @endif
            </dd>
            <dt class="text-gray-500 font-medium">Quantity</dt>
            <dd class="sm:col-span-2 text-gray-900">{{ number_format((float) $stockMovement->quantity, 2) }} {{ $stockMovement->unit_name }}</dd>
            <dt class="text-gray-500 font-medium">Recorded by</dt>
            <dd class="sm:col-span-2 text-gray-900">{{ $stockMovement->creator->name ?? '—' }}</dd>
        </dl>
        <div class="px-6 py-4">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Notes</h2>
            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $stockMovement->notes ?: '—' }}</p>
        </div>
    </div>
</div>
@endsection
