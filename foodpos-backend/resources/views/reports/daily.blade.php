@extends('layouts.app')

@section('title', 'Daily Sales Report')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to Reports</a>
        <h1 class="text-2xl font-bold text-gray-900">Daily Sales</h1>
        <p class="mt-1 text-sm text-gray-500">Day-by-day revenue and order count.</p>
    </div>

    @include('reports._filters')

    <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($daily as $d)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ \Carbon\Carbon::parse($d->date)->format('D, M j, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-700">{{ number_format($d->order_count) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ number_format($d->revenue, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-gray-500">No orders in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
