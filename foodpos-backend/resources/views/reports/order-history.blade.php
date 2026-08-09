@extends('layouts.app')

@section('title', 'Order History')

@section('content')
@php
    $customerOptions = $customers->map(fn ($customer) => [
        'id' => (int) $customer->id,
        'name' => $customer->name,
    ])->values();
    $staffOptions = $staff->map(fn ($member) => [
        'id' => (int) $member->id,
        'name' => $member->name,
    ])->values();
@endphp
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Order History</h1>
        <p class="mt-1 text-sm text-gray-500">Search and review completed orders by customer, staff, type, bill number, and date range. Open or in-progress tabs are not included.</p>
    </div>

    <form method="get"
          action="{{ route('reports.order-history') }}"
          x-data="{
              customers: @json($customerOptions),
              staff: @json($staffOptions),
              customerId: @json(request('customer_id') !== null && request('customer_id') !== '' ? (string) request('customer_id') : ''),
              waiterId: @json(request('waiter_id') !== null && request('waiter_id') !== '' ? (string) request('waiter_id') : ''),
              riderId: @json(request('delivery_rider_id') !== null && request('delivery_rider_id') !== '' ? (string) request('delivery_rider_id') : ''),
          }"
          class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @if($availableBranches->isNotEmpty())
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                    <select name="branch_id" id="branch_id" class="block w-full filter-control">
                        @if(show_branch_ui() && $availableBranches->count() > 1)
                            <option value="">All branches</option>
                        @endif
                        @foreach($availableBranches as $b)
                            <option value="{{ $b->id }}" {{ (request('branch_id', optional($selectedBranch)->id ?? null) == $b->id) ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div x-data="searchableSelect({
                    options: customers,
                    value: customerId,
                    maxResults: 150,
                    placeholder: 'Search customers…',
                    emptyMessage: 'No customers found',
                    onChange: (value) => { customerId = value ? String(value) : ''; },
                })"
                 x-init="init(); $watch('selectedValue', (value) => { customerId = value ? String(value) : ''; })">
                <x-searchable-select
                    label="Customer"
                    compact
                    useButtonOptions
                    id="customer_search"
                >
                    <x-slot:hiddenInput>
                        <input type="hidden" name="customer_id" x-model="selectedValue">
                    </x-slot:hiddenInput>
                </x-searchable-select>
            </div>
            <div x-data="searchableSelect({
                    options: staff,
                    value: waiterId,
                    maxResults: 150,
                    placeholder: 'Search waiters…',
                    emptyMessage: 'No waiters found',
                    onChange: (value) => { waiterId = value ? String(value) : ''; },
                })"
                 x-init="init(); $watch('selectedValue', (value) => { waiterId = value ? String(value) : ''; })">
                <x-searchable-select
                    label="Waiter"
                    compact
                    useButtonOptions
                    id="waiter_search"
                >
                    <x-slot:hiddenInput>
                        <input type="hidden" name="waiter_id" x-model="selectedValue">
                    </x-slot:hiddenInput>
                </x-searchable-select>
            </div>
            <div x-data="searchableSelect({
                    options: staff,
                    value: riderId,
                    maxResults: 150,
                    placeholder: 'Search riders…',
                    emptyMessage: 'No riders found',
                    onChange: (value) => { riderId = value ? String(value) : ''; },
                })"
                 x-init="init(); $watch('selectedValue', (value) => { riderId = value ? String(value) : ''; })">
                <x-searchable-select
                    label="Rider"
                    compact
                    useButtonOptions
                    id="delivery_rider_search"
                >
                    <x-slot:hiddenInput>
                        <input type="hidden" name="delivery_rider_id" x-model="selectedValue">
                    </x-slot:hiddenInput>
                </x-searchable-select>
            </div>
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type" id="type" class="block w-full filter-control">
                    <option value="">All types</option>
                    <option value="dine_in" {{ request('type') === 'dine_in' ? 'selected' : '' }}>Dine in</option>
                    <option value="takeaway" {{ request('type') === 'takeaway' ? 'selected' : '' }}>Take away</option>
                    <option value="delivery" {{ request('type') === 'delivery' ? 'selected' : '' }}>Delivery</option>
                </select>
            </div>
            <div>
                <label for="order_number" class="block text-sm font-medium text-gray-700 mb-1">Order #</label>
                <input type="text" name="order_number" id="order_number" value="{{ request('order_number') }}" placeholder="Search bill number" class="block w-full filter-control">
            </div>
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">Start date</label>
                <input type="date" name="from" id="from" value="{{ $from }}" class="block w-full filter-control" required>
            </div>
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">End date</label>
                <input type="date" name="to" id="to" value="{{ $to }}" class="block w-full filter-control" required>
            </div>
            <div class="md:col-span-2 lg:col-span-4 flex flex-wrap gap-2">
                <button type="submit" name="generate" value="1" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-search mr-2"></i>Generate Report
                </button>
                <a href="{{ route('reports.order-history') }}" class="inline-flex items-center justify-center h-11 px-4 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </div>
    </form>

    @if($showReport && $summary)
        @php
            $typeRows = \App\Support\OrderHistoryReport::typeRowsForDisplay($summary);
            $totalCountLabel = \App\Support\OrderHistoryReport::orderCountLabel((int) $summary['order_count']);
        @endphp

        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3 border-b border-gray-200 bg-gray-50">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Orders</h2>
                    <p class="text-sm text-gray-600 mt-0.5">{{ $period['period_label'] }}</p>
                    @if($selectedBranch)
                        <p class="text-sm text-gray-500">{{ $selectedBranch->name }}</p>
                    @elseif($availableBranches->count() > 1)
                        <p class="text-sm text-gray-500">All branches</p>
                    @endif
                </div>
                <a href="{{ route('reports.order-history.pdf', request()->only(['branch_id', 'from', 'to', 'customer_id', 'waiter_id', 'delivery_rider_id', 'type', 'order_number'])) }}"
                   class="inline-flex items-center justify-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm shrink-0">
                    <i class="fas fa-file-pdf mr-2 text-red-600"></i>Export PDF
                </a>
            </div>

            <div class="flex flex-wrap items-stretch gap-2 px-4 py-2 border-b border-gray-100 bg-gray-50/80">
                @foreach($typeRows as $typeRow)
                    <div class="inline-flex flex-col min-w-[7rem] flex-1 sm:flex-none px-2.5 py-1.5 rounded-lg border border-gray-200 bg-white shadow-sm">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $typeRow['label'] }}</span>
                        <span class="text-xs font-bold text-gray-900 tabular-nums">{{ $typeRow['count_label'] }}</span>
                        <span class="text-xs font-bold text-indigo-700 tabular-nums">{{ format_currency($typeRow['amount']) }}</span>
                    </div>
                @endforeach
                <div class="inline-flex flex-col min-w-[7rem] flex-1 sm:flex-none px-2.5 py-1.5 rounded-lg border-2 border-indigo-200 bg-indigo-50 shadow-sm">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Total</span>
                    <span class="text-xs font-bold text-gray-900 tabular-nums">{{ $totalCountLabel }}</span>
                    <span class="text-xs font-bold text-indigo-700 tabular-nums">{{ format_currency($summary['total_amount']) }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waiter</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rider</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($orders as $order)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $order->order_number }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::formatOrderDate($order) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::typeLabel($order->type) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::customerDisplayName($order) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $order->waiter?->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $order->deliveryRider?->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $order->table?->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 text-right tabular-nums">{{ $order->items_count }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right tabular-nums">{{ format_currency($order->total_amount) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-8 text-center text-sm text-gray-500">No orders match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
