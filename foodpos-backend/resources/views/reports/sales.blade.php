@extends('layouts.app')

@section('title', 'Sales Report')

@section('content')
@php
    $categoryOptions = $categories->map(fn ($category) => [
        'id' => (int) $category->id,
        'name' => $category->displayLabel(),
    ])->values();
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Sales</h1>
        <p class="mt-1 text-sm text-gray-500">Period summary and sales by category. Optionally filter to one category to see matching orders.</p>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-lg border border-red-200 bg-red-50 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="get" action="{{ route('reports.sales') }}" class="bg-white rounded-lg shadow border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            @if($availableBranches->isNotEmpty())
                <div class="min-w-0">
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                    <select name="branch_id" id="branch_id" class="block w-full filter-control pr-8">
                        @if(show_branch_ui() && $availableBranches->count() > 1)
                            <option value="">All branches</option>
                        @endif
                        @foreach($availableBranches as $b)
                            <option value="{{ $b->id }}" @selected((string) request('branch_id', optional($selectedBranch)->id) === (string) $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="min-w-0"
                 x-data="searchableSelect({
                     options: @js($categoryOptions),
                     value: @js($selectedCategoryId ? (string) $selectedCategoryId : ''),
                     maxResults: 200,
                     placeholder: 'All categories',
                     emptyMessage: 'No categories found',
                 })"
                 x-init="init()">
                <x-searchable-select
                    label="Category"
                    compact
                    useButtonOptions
                    id="sales_category"
                >
                    <x-slot:hiddenInput>
                        <input type="hidden" name="category_id" x-model="selectedValue">
                    </x-slot:hiddenInput>
                </x-searchable-select>
            </div>

            <div class="min-w-0">
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" name="from" id="from" value="{{ $from }}" class="block w-full filter-control">
            </div>
            <div class="min-w-0">
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" name="to" id="to" value="{{ $to }}" class="block w-full filter-control">
            </div>
            <div class="min-w-0">
                <label class="block text-sm font-medium text-transparent mb-1 select-none" aria-hidden="true">Apply</label>
                <button type="submit" class="inline-flex w-full items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-sync-alt mr-2"></i>Apply
                </button>
            </div>
        </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Total Revenue</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ format_currency($totalRevenue) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Orders</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($orderCount) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Avg Order Value</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ format_currency($avgOrderValue) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Discounts (total)</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ format_currency($discountAmount) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Breakdown</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div><dt class="text-sm text-gray-500">Subtotal</dt><dd class="text-lg font-semibold">{{ format_currency($subtotal) }}</dd></div>
            <div><dt class="text-sm text-gray-500">Tax</dt><dd class="text-lg font-semibold">{{ format_currency($taxAmount) }}</dd></div>
            <div><dt class="text-sm text-gray-500">Total</dt><dd class="text-lg font-semibold text-indigo-600">{{ format_currency($totalRevenue) }}</dd></div>
        </dl>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="font-semibold text-gray-900">Sales by category</h2>
            <p class="mt-0.5 text-sm text-gray-500">
                {{ format_date($from) }} – {{ format_date($to) }}
                @if($selectedBranch) · {{ $selectedBranch->name }} @elseif($availableBranches->count() > 1) · All branches @endif
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty sold</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sales</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($categoryRows as $row)
                        @php
                            $isSelected = $selectedCategoryId && $row['category_id'] === $selectedCategoryId;
                            $drillUrl = $row['category_id']
                                ? route('reports.sales', array_filter([
                                    'branch_id' => request('branch_id', optional($selectedBranch)->id),
                                    'from' => $from,
                                    'to' => $to,
                                    'category_id' => $row['category_id'],
                                ], fn ($v) => $v !== null && $v !== ''))
                                : null;
                        @endphp
                        <tr class="{{ $isSelected ? 'bg-indigo-50' : '' }}">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                @if($drillUrl)
                                    <a href="{{ $drillUrl }}" class="text-indigo-700 hover:text-indigo-900">{{ $row['category_label'] }}</a>
                                @else
                                    {{ $row['category_label'] }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right tabular-nums text-gray-700">{{ number_format($row['order_count']) }}</td>
                            <td class="px-4 py-3 text-sm text-right tabular-nums text-gray-700">{{ number_format($row['quantity'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right tabular-nums font-medium text-gray-900">{{ format_currency($row['sales']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">No category sales for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($categoryRows->isNotEmpty())
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900">Total (line sales)</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-sm text-right tabular-nums font-semibold text-gray-900">{{ number_format($categoryRows->sum('quantity'), 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right tabular-nums font-semibold text-gray-900">{{ format_currency($categoryRows->sum('sales')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    @if($selectedCategory && $categorySummary)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Matching orders</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($categorySummary['order_count']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Quantity sold</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($categorySummary['matched_quantity'], 2) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Matched sales</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700">{{ format_currency($categorySummary['matched_sales']) }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 bg-gray-50 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-gray-900">{{ $selectedCategory->displayLabel() }} orders</h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        {{ format_date($from) }} – {{ format_date($to) }}
                        @if($selectedBranch) · {{ $selectedBranch->name }} @elseif($availableBranches->count() > 1) · All branches @endif
                    </p>
                </div>
                <a href="{{ route('reports.sales', array_filter([
                        'branch_id' => request('branch_id', optional($selectedBranch)->id),
                        'from' => $from,
                        'to' => $to,
                    ], fn ($v) => $v !== null && $v !== '')) }}"
                   class="inline-flex items-center h-9 px-3 text-sm font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Clear category
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Matched qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Matched sales</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Order total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($categoryOrders as $order)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('order-management.show', $order) }}" class="text-indigo-700 hover:text-indigo-900">{{ $order->order_number }}</a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::formatOrderDate($order) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::typeLabel($order->type) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ \App\Support\OrderHistoryReport::customerDisplayName($order) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right tabular-nums">{{ number_format((float) $order->matched_quantity, 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-right tabular-nums">{{ format_currency($order->matched_sales) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right tabular-nums">{{ format_currency($order->total_amount) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">No matching orders were found for this category.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($categoryOrders->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">{{ $categoryOrders->links() }}</div>
            @endif
        </div>
    @endif
</div>
@endsection
