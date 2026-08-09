@extends('layouts.app')

@section('title', 'Refunds')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Refunds</h1>
            <p class="mt-1 text-sm text-gray-500 max-w-2xl">Start a refund by order number (opens the adjustment form). Below is your refund history; use filters to narrow the list.</p>
        </div>
        <a href="{{ route('order-management.index') }}" class="inline-flex items-center justify-center h-11 px-4 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50 shrink-0">Orders</a>
    </div>
    {{-- Top: start refund (opens adjustment on next screen) --}}
    <section class="rounded-xl border-2 border-indigo-200 bg-gradient-to-br from-indigo-50 to-white shadow-sm overflow-hidden" aria-labelledby="start-refund-heading">
        <div class="px-4 py-3 sm:px-6 sm:py-4 border-b border-indigo-100 bg-white/80">
            <h2 id="start-refund-heading" class="text-base font-semibold text-indigo-950">Start a refund</h2>
            <p class="text-sm text-indigo-900/80 mt-0.5">Enter the order number, then click <span class="font-medium">Refund</span> to open the line-by-line adjustment form.</p>
        </div>
        <div class="p-4 sm:p-6">
            <form method="GET" action="{{ route('order-management.refunds.start') }}" class="flex flex-col lg:flex-row lg:items-end gap-4">
                <div class="flex-1 min-w-0 w-full">
                    <label for="start_order_number" class="block text-sm font-medium text-gray-800 mb-1.5">Order number</label>
                    <input
                        type="text"
                        name="order_number"
                        id="start_order_number"
                        value="{{ old('order_number') }}"
                        required
                        autocomplete="off"
                        placeholder="e.g. ORD-1001"
                        class="block w-full h-11 px-3 rounded-lg border border-gray-300 shadow-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 h-11 px-8 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 shadow-sm shrink-0 w-full sm:w-auto">
                    <i class="fas fa-undo-alt" aria-hidden="true"></i>
                    Refund
                </button>
            </form>
        </div>
    </section>

    {{-- Refund history --}}
    <section class="space-y-4" aria-labelledby="refund-history-heading">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
            <h2 id="refund-history-heading" class="text-lg font-semibold text-gray-900">Refund history</h2>
        </div>

        <div class="bg-white shadow rounded-lg p-4 border border-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Filter list</p>
            <form method="GET" action="{{ route('order-management.refunds.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                    <label for="list_order" class="block text-sm font-medium text-gray-700 mb-1">Order # (list)</label>
                    <input type="text" name="list_order" id="list_order" value="{{ request('list_order') }}" placeholder="Filter this table" class="block w-full filter-control">
                </div>
                <div class="md:col-span-2 lg:col-span-4 flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Filter</button>
                    <a href="{{ route('order-management.refunds.index') }}" class="inline-flex items-center justify-center h-11 px-4 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Reset</a>
                </div>
            </form>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            @include('partials.listing-per-page-bar', [
                'action' => route('order-management.refunds.index'),
                'paginator' => $refunds,
                'perPage' => $perPage,
            ])

            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Order</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Branch</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Refund total</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">By</th>
                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($refunds as $refund)
                            <tr>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $refunds->firstItem() + $loop->index }}</td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ $refund->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">{{ $refund->order->order_number ?? '—' }}</td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ $refund->order->branch->name ?? '—' }}</td>
                                <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">{{ format_currency((float) $refund->total_refund) }}</td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ $refund->creator->name ?? '—' }}</td>
                                <td class="px-3 py-3 whitespace-nowrap text-right">
                                    @if($refund->order)
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('order-management.show', $refund->order) }}" class="text-indigo-600 hover:text-indigo-800" title="View order">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('pos.invoice', $refund->order) }}" class="text-gray-600 hover:text-gray-800" title="Print receipt">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">No refunds match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('partials.listing-table-pagination', ['paginator' => $refunds])
        </div>
    </section>
</div>
@endsection
