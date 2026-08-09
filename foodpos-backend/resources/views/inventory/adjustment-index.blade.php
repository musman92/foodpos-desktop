@extends('layouts.app')

@section('title', 'Inventory adjustments')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Inventory adjustments</h1>
            <p class="mt-1 text-sm text-gray-500">History of manual stock changes. Edit or delete an entry to reverse and reapply stock.</p>
        </div>
        <a href="{{ route('inventory.adjustment.create', request()->only(['branch_id'])) }}"
           class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 shadow-sm">
            New adjustment
        </a>
    </div>
    <div class="bg-white shadow rounded-lg p-4 sm:p-6">
        <form method="GET" action="{{ route('inventory.adjustment.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @if(show_branch_ui() && $branches->count() > 0)
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                    <select name="branch_id" id="branch_id" class="block w-full filter-control">
                        <option value="">All</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" name="from" id="from" value="{{ request('from') }}" class="block w-full filter-control">
            </div>
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" name="to" id="to" value="{{ request('to') }}" class="block w-full filter-control">
            </div>
            <div>
                <label for="ingredient" class="block text-sm font-medium text-gray-700 mb-1">Ingredient or menu item</label>
                <input type="text" name="ingredient" id="ingredient" value="{{ request('ingredient') }}" placeholder="Name contains…" class="block w-full filter-control">
            </div>
            <div class="md:col-span-2 lg:col-span-4 flex flex-wrap gap-2">
                <button type="submit" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-filter mr-2"></i>
                    Filter
                </button>
                <a href="{{ route('inventory.adjustment.index') }}" class="inline-flex items-center justify-center h-11 px-4 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('inventory.adjustment.index'),
            'paginator' => $adjustments,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Branch</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Item</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Direction</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Qty</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">By</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Notes</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($adjustments as $row)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $adjustments->firstItem() + $loop->index }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $row->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $row->branch->name ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                @if($row->ingredient_id)
                                    <span class="text-gray-500 text-xs uppercase mr-1">Ingredient</span><span class="font-medium">{{ $row->ingredient->name ?? '—' }}</span>
                                @elseif($row->menu_item_id)
                                    <span class="text-gray-500 text-xs uppercase mr-1">Menu</span><span class="font-medium">{{ $row->menuItem->name ?? '—' }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if($row->movement === 'in')
                                    <span class="text-green-700 font-medium">Increase</span>
                                @else
                                    <span class="text-amber-800 font-medium">Decrease</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">
                                {{ number_format((float) $row->quantity, 2) }}
                                <span class="text-gray-500">{{ $row->unit_name }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $row->creator->name ?? '—' }}
                            </td>
                            <td class="px-3 py-3 text-gray-600 max-w-xs">
                                <span class="line-clamp-2" title="{{ $row->notes }}">{{ $row->notes ? \Illuminate\Support\Str::limit($row->notes, 48) : '—' }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('inventory.adjustment.show', $row) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('inventory.adjustment.edit', $row) }}"
                                       class="text-gray-600 hover:text-gray-900"
                                       title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <form action="{{ route('inventory.adjustment.destroy', $row) }}"
                                          method="POST"
                                          class="inline"
                                          onsubmit="return confirm('Delete this adjustment? Stock will reverse to undo this change.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-boxes text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No adjustments match your filters</h3>
                                    <p class="text-sm text-gray-500">Try changing the filter criteria or create a new adjustment.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $adjustments])
    </div>
</div>
@endsection
