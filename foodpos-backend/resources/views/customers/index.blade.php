@extends('layouts.app')

@section('title', 'Customers')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Customers</h1>
            <p class="mt-1 text-sm text-gray-500">Manage your customers and their delivery addresses</p>
        </div>
        <div class="flex items-center gap-3">
            @include('partials.catalog-export-actions', ['routeName' => 'customers.export'])
            <a href="{{ route('customers.import') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-file-import mr-2"></i>
                Import
            </a>
            <a href="{{ route('customers.create') }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-plus mr-2"></i>
                Add Customer
            </a>
        </div>
    </div>

    @include('partials.global-tenant-listing-filters', [
        'action' => route('customers.index'),
        'search' => $search ?? '',
        'searchPlaceholder' => 'Search by code, name, phone, or email…',
    ])

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('customers.index'),
            'paginator' => $customers,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Code</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Email</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Phone</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Balance</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($customers as $customer)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $customers->firstItem() + $loop->index }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">
                                {{ $customer->code ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                <span class="font-medium">{{ $customer->name }}</span>
                                @if($customer->is_default)
                                    <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">Default</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $customer->email ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $customer->phone ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right tabular-nums {{ $customer->balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ format_currency($customer->balance) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('customers.show', $customer) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if(auth()->user()->hasAppPermission('account-statements.index'))
                                        <a href="{{ route('account-statements.index', ['type' => 'customer', 'party_id' => $customer->id]) }}"
                                           class="text-emerald-600 hover:text-emerald-800"
                                           title="Account statement">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('customers.edit', $customer) }}"
                                       class="text-blue-600 hover:text-blue-800"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @if(!$customer->is_default)
                                        <form action="{{ route('customers.destroy', $customer) }}"
                                              method="POST"
                                              class="inline"
                                              onsubmit="return confirm('Are you sure you want to delete this customer?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-red-600 hover:text-red-800"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No customers found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new customer.</p>
                                    <a href="{{ route('customers.create') }}"
                                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Customer
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $customers])
    </div>
</div>
@endsection
