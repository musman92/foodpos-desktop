@extends('layouts.app')

@section('title', 'Top Selling Items Report')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to Reports</a>
        <h1 class="text-2xl font-bold text-gray-900">Top Selling Items</h1>
        <p class="mt-1 text-sm text-gray-500">Top products and deals by total quantity, with size/variant breakdown on the rows below each item.</p>
    </div>

    @include('reports._filters')

    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($items as $i => $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-500 align-top">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 align-top">
                                {{ $row->item_name }}
                                @if($row->deal_id)
                                    <span class="ml-1 text-xs font-medium text-amber-600">Deal</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 align-top">{{ number_format($row->total_quantity, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 align-top">{{ number_format($row->total_revenue, 2) }}</td>
                        </tr>
                        @foreach($row->variants ?? [] as $variant)
                            <tr class="hover:bg-gray-50 bg-gray-50/60">
                                <td class="px-4 py-2"></td>
                                <td class="px-4 py-2 text-sm text-gray-600 pl-10">
                                    <span class="text-gray-400 mr-1">└</span>{{ $variant->label }}
                                </td>
                                <td class="px-4 py-2 text-sm text-right text-gray-600">{{ number_format($variant->total_quantity, 2) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-gray-600">{{ number_format($variant->total_revenue, 2) }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">No orders in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
