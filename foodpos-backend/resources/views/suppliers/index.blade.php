@extends('layouts.app')

@section('title', 'Suppliers')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Suppliers</h1>
            <p class="mt-1 text-sm text-gray-500">Manage your suppliers and vendor information</p>
        </div>
        <div class="flex items-center gap-3">
            @include('partials.catalog-export-actions', ['routeName' => 'suppliers.export'])
            <a href="{{ route('suppliers.import') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-file-import mr-2"></i>
                Import
            </a>
            <a href="{{ route('suppliers.create') }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-plus mr-2"></i>
                Add Supplier
            </a>
        </div>
    </div>

    @include('partials.global-tenant-listing-filters', [
        'action' => route('suppliers.index'),
        'search' => $search ?? '',
        'searchPlaceholder' => 'Search by code, name, phone, or email…',
    ])

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('suppliers.index'),
            'paginator' => $suppliers,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Code</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Contact person</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Phone</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Balance</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $suppliers->firstItem() + $loop->index }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">
                                {{ $supplier->code ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                <span class="font-medium">{{ $supplier->name }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $supplier->contact_person ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $supplier->phone ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right tabular-nums {{ $supplier->balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ format_currency($supplier->balance) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $supplier->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($supplier->status) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('suppliers.show', $supplier) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if(auth()->user()->hasAppPermission('account-statements.index'))
                                        <a href="{{ route('account-statements.index', ['type' => 'supplier', 'party_id' => $supplier->id]) }}"
                                           class="text-emerald-600 hover:text-emerald-800"
                                           title="Account statement">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('suppliers.edit', $supplier) }}"
                                       class="text-blue-600 hover:text-blue-800"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('suppliers.destroy', $supplier) }}"
                                          method="POST"
                                          class="inline"
                                          onsubmit="return confirm('Are you sure you want to delete this supplier?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-600 hover:text-red-800"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-truck text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No suppliers found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new supplier.</p>
                                    <a href="{{ route('suppliers.create') }}"
                                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Supplier
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $suppliers])
    </div>
</div>
@endsection
