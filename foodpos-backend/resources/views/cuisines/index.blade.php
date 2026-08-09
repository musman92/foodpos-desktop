@extends('layouts.app')

@section('title', 'Cuisines')

@php
    use Illuminate\Support\Facades\Storage;
@endphp

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Cuisines</h1>
            <p class="mt-1 text-sm text-gray-500">Manage different cuisine types for your menu items</p>
        </div>
        <a href="{{ route('cuisines.create') }}"
           class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
            <i class="fas fa-plus mr-2"></i>
            Add Cuisine
        </a>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('cuisines.index'),
            'paginator' => $cuisines,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Cuisine</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Description</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Menu Items</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Sort Order</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($cuisines as $cuisine)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $cuisines->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    @if($cuisine->image)
                                        <img src="{{ Storage::url($cuisine->image) }}" alt="{{ $cuisine->name }}" class="h-10 w-10 rounded-lg object-cover flex-shrink-0">
                                    @else
                                        <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-utensils text-white text-sm"></i>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900">{{ $cuisine->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $cuisine->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <p class="text-gray-500 line-clamp-2 max-w-xs">{{ $cuisine->description ?? 'No description' }}</p>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    {{ $cuisine->menuItems->count() }} items
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">{{ $cuisine->sort_order }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $cuisine->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $cuisine->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('cuisines.show', $cuisine) }}" class="text-indigo-600 hover:text-indigo-800" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('cuisines.edit', $cuisine) }}" class="text-blue-600 hover:text-blue-800" title="Edit"><i class="fas fa-edit"></i></a>
                                    <form action="{{ route('cuisines.destroy', $cuisine) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this cuisine? This will not delete the menu items, but they will lose their cuisine association.');">
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
                                    <i class="fas fa-utensils text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No cuisines found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new cuisine.</p>
                                    <a href="{{ route('cuisines.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Cuisine
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $cuisines])
    </div>
</div>
@endsection
