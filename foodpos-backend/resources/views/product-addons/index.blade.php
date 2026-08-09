@extends('layouts.app')

@section('title', 'Product Addons')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Product Addons</h1>
            <p class="mt-1 text-sm text-gray-500">Manage product addons for your menu items</p>
        </div>
        <div class="flex items-center gap-3">
            @include('partials.catalog-export-actions', ['routeName' => 'product-addons.export'])
            <a href="{{ route('product-addons.import') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-file-import mr-2"></i>
                Import
            </a>
            <a href="{{ route('product-addons.create') }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-plus mr-2"></i>
                Add Product Addon
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('product-addons.index'),
            'paginator' => $productAddons,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Code</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Price</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Cost</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Inventory</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($productAddons as $productAddon)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $productAddons->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-mono text-gray-600">{{ $productAddon->code ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">{{ $productAddon->name }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">{{ format_currency($productAddon->price) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-700 tabular-nums">{{ format_currency($productAddon->cost ?? 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                @if($productAddon->track_inventory)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ ucfirst($productAddon->type) }}</span>
                                @else
                                    <span class="text-gray-400">None</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('product-addons.show', $productAddon) }}" class="text-indigo-600 hover:text-indigo-800" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('product-addons.edit', $productAddon) }}" class="text-blue-600 hover:text-blue-800" title="Edit"><i class="fas fa-edit"></i></a>
                                    <form action="{{ route('product-addons.destroy', $productAddon) }}" method="POST" class="inline" onsubmit="return confirm('Delete this addon?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-puzzle-piece text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No product addons found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new product addon.</p>
                                    <a href="{{ route('product-addons.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Product Addon
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $productAddons])
    </div>
</div>
@endsection
