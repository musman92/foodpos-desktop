@extends('layouts.app')

@section('title', 'Purchase Returns')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Purchase Returns</h1>
            <p class="mt-1 text-sm text-gray-500">History of goods returned to suppliers</p>
        </div>
        <a href="{{ route('purchase-returns.create') }}"
           class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
            <i class="fas fa-plus mr-2"></i>
            Purchase Return
        </a>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('purchase-returns.index'),
            'paginator' => $returns,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Return #</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Purchase #</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Supplier</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Amount</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($returns as $return)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $returns->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">{{ $return->return_number }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ format_date($return->return_date) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                @if($return->purchase)
                                    <a href="{{ route('purchases.show', $return->purchase) }}" class="text-indigo-600 hover:text-indigo-800">
                                        {{ $return->purchase->purchase_number }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">{{ $return->supplier->name ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">{{ format_currency($return->total_amount) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('purchase-returns.show', $return) }}" class="text-indigo-600 hover:text-indigo-800" title="View"><i class="fas fa-eye"></i></a>
                                    @if(auth()->user()->hasAppPermission('purchase-returns.update'))
                                        <a href="{{ route('purchase-returns.edit', $return) }}" class="text-gray-600 hover:text-gray-800" title="Edit"><i class="fas fa-edit"></i></a>
                                    @endif
                                    @if(auth()->user()->hasAppPermission('purchase-returns.destroy'))
                                        <form action="{{ route('purchase-returns.destroy', $return) }}"
                                              method="POST"
                                              class="inline"
                                              onsubmit="return confirm('Delete this purchase return? Stock and supplier balance will be restored.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-undo text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No purchase returns yet</h3>
                                    <p class="text-sm text-gray-500 mb-4">Record returns when goods are sent back to a supplier.</p>
                                    <a href="{{ route('purchase-returns.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        Purchase Return
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $returns])
    </div>
</div>
@endsection
