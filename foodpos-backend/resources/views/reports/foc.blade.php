@extends('layouts.app')

@section('title', 'FOC Report')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to Reports</a>
        <h1 class="text-2xl font-bold text-gray-900">FOC</h1>
        <p class="mt-1 text-sm text-gray-500">Complimentary orders for the selected period (value given away).</p>
    </div>

    @include('reports._filters')

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">FOC orders</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['order_count']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Total FOC value</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['total_value'], 2) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Items</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($rows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $row['date'] }}</td>
                            <td class="px-4 py-3 text-sm font-medium whitespace-nowrap">
                                <a href="{{ route('order-management.show', $row['id']) }}" class="text-indigo-600 hover:text-indigo-800">
                                    {{ $row['order_number'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $row['branch'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $row['type_label'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $row['customer'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $row['cashier'] }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-700 tabular-nums">{{ number_format($row['item_count']) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 tabular-nums">{{ number_format($row['total_amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">No FOC orders in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->isNotEmpty())
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex justify-between text-sm font-semibold text-gray-900">
                <span>{{ number_format($summary['order_count']) }} order{{ $summary['order_count'] === 1 ? '' : 's' }}</span>
                <span>Total FOC value: {{ number_format($summary['total_value'], 2) }}</span>
            </div>
        @endif
    </div>
</div>
@endsection
