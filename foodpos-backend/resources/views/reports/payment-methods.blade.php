@extends('layouts.app')

@section('title', 'Payment Methods Report')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to Reports</a>
        <h1 class="text-2xl font-bold text-gray-900">Payment Methods</h1>
        <p class="mt-1 text-sm text-gray-500">Revenue and orders by money source.</p>
    </div>

    @include('reports._filters')

    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Source</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">% of Total</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($bySource as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['name'] }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format($row['order_count']) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ number_format($row['revenue'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-500">
                                {{ $totalRevenue > 0 ? number_format(($row['revenue'] / $totalRevenue) * 100, 1) : 0 }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">No orders in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($bySource->isNotEmpty())
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 text-right font-semibold text-gray-900">
                Total: {{ number_format($totalRevenue, 2) }}
            </div>
        @endif
    </div>
</div>
@endsection
