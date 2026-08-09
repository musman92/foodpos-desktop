@extends('layouts.app')

@section('title', 'Consumption Detail')

@section('content')
@php
    $item = $detail['item'];
    $summary = $detail['summary'];
    $branchLabel = $selectedBranch?->name
        ?? ($availableBranches->count() > 1 ? 'All branches' : ($availableBranches->first()?->name ?? '—'));
    $backParams = array_filter([
        'branch_id' => $branchId,
        'from' => $from,
        'to' => $to,
    ], fn ($value) => $value !== null && $value !== '');
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <a href="{{ route('reports.consumption', $backParams) }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Consumption Report
        </a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $item['name'] }}</h1>
        <p class="mt-1 text-sm text-gray-500">
            Audit trail for {{ strtolower($item['type'] === 'ingredient' ? 'ingredient' : 'menu item') }} consumption
            · {{ format_date($from, $branchId) }} – {{ format_date($to, $branchId) }}
            · {{ $branchLabel }}
        </p>
        @if($item['type'] === 'ingredient')
            <a href="{{ route('reports.ingredient-ledger', array_filter([
                'ingredient_id' => $item['id'],
                'branch_id' => $branchId,
                'from' => $from,
                'to' => $to,
            ])) }}" class="inline-flex items-center mt-2 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-clipboard-list mr-1.5"></i>
                Open full ingredient ledger (purchases + sales + adjustments)
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total qty used</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">
                {{ number_format($summary['total_quantity'], 2) }}
                @if($item['unit'])
                    <span class="text-base font-medium text-gray-500">{{ $item['unit'] }}</span>
                @endif
            </p>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">From sales</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($summary['sales_quantity'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($summary['sales_order_count']) }} order line(s)</p>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">From adjustments</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($summary['adjustment_quantity'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($summary['adjustment_count']) }} movement(s)</p>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total cost</p>
            <p class="mt-1 text-2xl font-bold text-indigo-700">{{ format_currency($summary['total_cost']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="font-semibold text-gray-900">Orders where this was consumed</h2>
            <p class="mt-0.5 text-sm text-gray-500">Grouped by order line. FIFO batch splits are combined into one used quantity.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Menu item</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Used qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($detail['sales'] as $sale)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                @if($sale['order_id'])
                                    <a href="{{ route('order-management.show', $sale['order_id']) }}" class="text-indigo-700 hover:text-indigo-900">
                                        {{ $sale['order_number'] ?: '—' }}
                                    </a>
                                @else
                                    {{ $sale['order_number'] ?: '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ $sale['occurred_at'] ? format_datetime($sale['occurred_at'], $branchId) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $sale['menu_item_name'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $sale['branch'] ?: '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right tabular-nums">
                                {{ number_format($sale['quantity'], 2) }}
                                @if($sale['unit'])
                                    <span class="text-gray-500">{{ $sale['unit'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right tabular-nums">{{ format_currency($sale['cost']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No sales consumption found for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="font-semibold text-gray-900">Stock adjustments</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manual outs, waste, and released/cancelled stock movements in the same period.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Related order / menu item</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Used qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">View</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($detail['adjustments'] as $adjustment)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                @if($adjustment['movement_id'])
                                    <a href="{{ route('inventory.adjustment.show', $adjustment['movement_id']) }}" class="text-indigo-700 hover:text-indigo-900">
                                        {{ $adjustment['occurred_at'] ? format_datetime($adjustment['occurred_at'], $branchId) : '—' }}
                                    </a>
                                @else
                                    {{ $adjustment['occurred_at'] ? format_datetime($adjustment['occurred_at'], $branchId) : '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm capitalize text-gray-700">
                                @if($adjustment['movement_id'])
                                    <a href="{{ route('inventory.adjustment.show', $adjustment['movement_id']) }}" class="text-indigo-700 hover:text-indigo-900 capitalize">
                                        {{ $adjustment['type'] }}
                                    </a>
                                @else
                                    {{ $adjustment['type'] }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                @if($adjustment['order_id'])
                                    <a href="{{ route('order-management.show', $adjustment['order_id']) }}" class="text-indigo-700 hover:text-indigo-900">
                                        {{ $adjustment['order_number'] }}
                                    </a>
                                    @if($adjustment['menu_item_name'])
                                        <span class="text-gray-500"> · {{ $adjustment['menu_item_name'] }}</span>
                                    @endif
                                @elseif($adjustment['menu_item_name'])
                                    {{ $adjustment['menu_item_name'] }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ $adjustment['created_by'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $adjustment['notes'] ?: '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right tabular-nums">
                                {{ number_format($adjustment['quantity'], 2) }}
                                @if($adjustment['unit'])
                                    <span class="text-gray-500">{{ $adjustment['unit'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right tabular-nums">{{ format_currency($adjustment['cost']) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                @if($adjustment['movement_id'])
                                    <a href="{{ route('inventory.adjustment.show', $adjustment['movement_id']) }}" class="text-indigo-700 hover:text-indigo-900 font-medium">
                                        Open
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">No stock adjustments found for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
