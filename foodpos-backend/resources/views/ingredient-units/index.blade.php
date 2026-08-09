@extends('layouts.app')

@section('title', 'Ingredient Units')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ingredient Units</h1>
            <p class="mt-1 text-sm text-gray-500">Manage units of measure for ingredients</p>
        </div>
        <div class="flex items-center gap-3">
            @include('partials.catalog-export-actions', ['routeName' => 'ingredient-units.export'])
            <a href="{{ route('ingredient-units.import') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-file-import mr-2"></i>
                Import
            </a>
            <a href="{{ route('ingredient-units.create') }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-plus mr-2"></i>
                Add Unit
            </a>
        </div>
    </div>

    @include('partials.global-tenant-listing-filters', [
        'action' => route('ingredient-units.index'),
        'search' => $search ?? '',
        'searchPlaceholder' => 'Search by name or code…',
    ])

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('ingredient-units.index'),
            'paginator' => $units,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Code</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Description</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Ingredients</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($units as $unit)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $units->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">{{ $unit->code ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900"><span class="font-medium">{{ $unit->name }}</span></td>
                            <td class="px-3 py-3 text-gray-600 max-w-xs"><span class="line-clamp-2">{{ $unit->description ?? '—' }}</span></td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">{{ (int) ($unit->linked_ingredients_count ?? 0) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('ingredient-units.show', $unit) }}" class="text-indigo-600 hover:text-indigo-800" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('ingredient-units.edit', $unit) }}" class="text-blue-600 hover:text-blue-800" title="Edit"><i class="fas fa-edit"></i></a>
                                    <form action="{{ route('ingredient-units.destroy', $unit) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this unit?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-ruler-combined text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No units found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new ingredient unit.</p>
                                    <a href="{{ route('ingredient-units.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Unit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $units])
    </div>
</div>
@endsection
