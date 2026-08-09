@extends('layouts.app')

@section('title', 'Taxes')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Taxes</h1>
            <p class="mt-1 text-sm text-gray-500">Manage tax rates for your business</p>
        </div>
        <a href="{{ route('taxes.create') }}"
           class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
            <i class="fas fa-plus mr-2"></i>
            Add Tax
        </a>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('taxes.index'),
            'paginator' => $taxes,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Percentage</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($taxes as $tax)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $taxes->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900"><span class="font-medium">{{ $tax->name }}</span></td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">{{ number_format($tax->percentage, 2) }}%</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $tax->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $tax->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('taxes.show', $tax) }}" class="text-indigo-600 hover:text-indigo-800" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('taxes.edit', $tax) }}" class="text-blue-600 hover:text-blue-800" title="Edit"><i class="fas fa-edit"></i></a>
                                    <form action="{{ route('taxes.destroy', $tax) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this tax?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-percent text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No taxes found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new tax.</p>
                                    <a href="{{ route('taxes.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Tax
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $taxes])
    </div>
</div>
@endsection
