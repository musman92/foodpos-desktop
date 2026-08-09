@extends('layouts.app')

@section('title', 'Orders')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Order Management</h1>
            <p class="mt-1 text-sm text-gray-500">View orders (completed orders are read-only). Use <strong>Refunds</strong> to correct totals or stock.</p>
        </div>
    </div>
    <div class="bg-white shadow rounded-lg p-4">
        <form method="GET" action="{{ route('order-management.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                <label for="order_number" class="block text-sm font-medium text-gray-700 mb-1">Order #</label>
                <input type="text" name="order_number" id="order_number" value="{{ request('order_number') }}" placeholder="Search" class="block w-full filter-control">
            </div>
            <div class="md:col-span-2 lg:col-span-4 flex gap-2">
                <button type="submit" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Filter</button>
                <a href="{{ route('order-management.index') }}" class="inline-flex items-center justify-center h-11 px-4 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('order-management.index'),
            'paginator' => $orders,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Branch</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Total</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Payment</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Refunds</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($orders as $order)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $orders->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">{{ $order->order_number }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ $order->branch->name ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ $order->listSortAt()->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">{{ format_currency($order->total_amount) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ ucfirst($order->payment_status) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ ucfirst($order->status) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if((int) $order->refunds_count > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-900">{{ $order->refunds_count }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('order-management.show', $order) }}" class="text-indigo-600 hover:text-indigo-800" title="View order">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('pos.invoice', $order) }}" class="text-gray-600 hover:text-gray-800" title="Print receipt">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    @if($canDelete ?? false)
                                        <form action="{{ route('order-management.destroy', $order) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Delete order {{ $order->order_number }} and reverse payments, KOTs, and inventory? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800" title="Delete order">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-sm text-gray-500">No orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $orders])
    </div>
</div>
@endsection
