@extends('layouts.app')

@section('title', 'Variants')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Variants</h1>
            <p class="mt-1 text-sm text-gray-500">Manage size variants (Small, Medium, Large, etc.) for your menu items</p>
        </div>
        <div class="flex items-center gap-3">
            @include('partials.catalog-export-actions', ['routeName' => 'variants.export'])
            <a href="{{ route('variants.import') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-file-import mr-2"></i>
                Import
            </a>
            <a href="{{ route('variants.create') }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-plus mr-2"></i>
                Add Variant
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('variants.index'),
            'paginator' => $variants,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Code</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Description</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($variants as $variant)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $variants->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">{{ $variant->name }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-mono text-gray-600">{{ $variant->code ?? '—' }}</td>
                            <td class="px-3 py-3 text-gray-500">{{ Str::limit($variant->description ?? '—', 50) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if($variant->is_active)
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('variants.show', $variant) }}" class="text-indigo-600 hover:text-indigo-800" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('variants.edit', $variant) }}" class="text-blue-600 hover:text-blue-800" title="Edit"><i class="fas fa-edit"></i></a>
                                    <form action="{{ route('variants.destroy', $variant) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this variant?');">
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
                                    <i class="fas fa-tags text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No variants found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new variant.</p>
                                    <a href="{{ route('variants.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Variant
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $variants])
    </div>
</div>
@endsection
