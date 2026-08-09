@extends('layouts.app')

@section('title', 'Ingredient Ledger')

@section('content')
@php
    $branchLabel = $selectedBranch?->name
        ?? ($availableBranches->count() > 1 ? 'All branches' : ($availableBranches->first()?->name ?? '—'));
    $ingredient = $ledger['ingredient'] ?? null;
    $summary = $ledger['summary'] ?? null;
    $opening = $ledger['opening'] ?? null;
    $ingredientOptions = collect($ingredients)->map(fn ($option) => [
        'id' => (int) $option['id'],
        'name' => $option['sku']
            ? $option['name'].' ('.$option['sku'].')'
            : $option['name'],
        'code' => $option['sku'],
        'search_text' => trim(($option['name'] ?? '').' '.($option['sku'] ?? '')),
    ])->values();
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Ingredient Ledger</h1>
        <p class="mt-1 text-sm text-gray-500">
            One timeline for purchases, sales, and stock adjustments — use this to debug remaining stock.
        </p>
    </div>

    <form method="GET"
          action="{{ route('reports.ingredient-ledger') }}"
          class="bg-white rounded-lg shadow border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="lg:col-span-2 min-w-0"
                 x-data="searchableSelect({
                     options: @js($ingredientOptions),
                     value: @js($ingredientId ? (string) $ingredientId : ''),
                     maxResults: 150,
                     placeholder: 'Search ingredients…',
                     emptyMessage: 'No ingredients found',
                 })"
                 x-init="init()">
                <x-searchable-select
                    label="Ingredient"
                    compact
                    useButtonOptions
                    required
                    id="ingredient_search"
                >
                    <x-slot:hiddenInput>
                        <input type="hidden" name="ingredient_id" x-model="selectedValue">
                    </x-slot:hiddenInput>
                </x-searchable-select>
            </div>
            @if(show_branch_ui() && $availableBranches->count() > 1)
            <div>
                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                <select name="branch_id" id="branch_id" class="block w-full filter-control">
                    <option value="">All branches</option>
                    @foreach($availableBranches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            @elseif($selectedBranch)
                <input type="hidden" name="branch_id" value="{{ $selectedBranch->id }}">
            @endif
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" name="from" id="from" value="{{ $from }}" required class="block w-full filter-control">
            </div>
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" name="to" id="to" value="{{ $to }}" required class="block w-full filter-control">
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="submit"
                    class="inline-flex items-center h-10 px-4 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                <i class="fas fa-search mr-2"></i>
                View ledger
            </button>
        </div>
    </form>

    @if(! $ledger)
        <div class="bg-white rounded-xl shadow border border-gray-200 px-6 py-12 text-center text-sm text-gray-500">
            Select an ingredient and date range to see purchases, consumption, and adjustments together.
        </div>
    @else
        <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">{{ $ingredient['name'] }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        @if($ingredient['sku'])
                            SKU {{ $ingredient['sku'] }} ·
                        @endif
                        @if($ingredient['category'])
                            {{ $ingredient['category'] }} ·
                        @endif
                        {{ format_date($from, $branchId) }} – {{ format_date($to, $branchId) }}
                        · {{ $branchLabel }}
                    </p>
                </div>
                <div class="text-sm text-gray-600 space-y-1 text-right">
                    <p>
                        Track stock:
                        <span class="font-medium {{ $ingredient['track_stock'] === 'yes' ? 'text-green-700' : 'text-amber-700' }}">
                            {{ $ingredient['track_stock'] === 'yes' ? 'Yes' : 'No' }}
                        </span>
                    </p>
                    <p>
                        Units:
                        <span class="font-medium text-gray-900">{{ $ingredient['consumption_unit'] ?: '—' }}</span>
                        (stock)
                        @if($ingredient['purchase_unit'] && $ingredient['purchase_unit'] !== $ingredient['consumption_unit'])
                            ·
                            <span class="font-medium text-gray-900">{{ $ingredient['purchase_unit'] }}</span>
                            (purchase)
                            · 1 {{ $ingredient['purchase_unit'] }} = {{ number_format($ingredient['conversion_rate'], 4) }} {{ $ingredient['consumption_unit'] }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Opening</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($opening['qty'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $opening['consumption_unit'] }}
                    @if($opening['purchase_unit'] !== $opening['consumption_unit'])
                        · {{ number_format($opening['purchase_qty'], 4) }} {{ $opening['purchase_unit'] }}
                    @endif
                </p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Purchased</p>
                <p class="mt-1 text-2xl font-bold text-green-700 tabular-nums">+{{ number_format($summary['purchased_qty'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $ingredient['consumption_unit'] }}
                    @if($ingredient['purchase_unit'] !== $ingredient['consumption_unit'])
                        · {{ number_format($summary['purchased_purchase_qty'], 4) }} {{ $ingredient['purchase_unit'] }}
                    @endif
                </p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Returned</p>
                <p class="mt-1 text-2xl font-bold text-orange-700 tabular-nums">−{{ number_format($summary['returned_qty'] ?? 0, 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $ingredient['consumption_unit'] }}
                    @if($ingredient['purchase_unit'] !== $ingredient['consumption_unit'])
                        · {{ number_format($summary['returned_purchase_qty'] ?? 0, 4) }} {{ $ingredient['purchase_unit'] }}
                    @endif
                </p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sold</p>
                <p class="mt-1 text-2xl font-bold text-red-700 tabular-nums">−{{ number_format($summary['sold_qty'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $ingredient['consumption_unit'] }}
                    @if($ingredient['purchase_unit'] !== $ingredient['consumption_unit'])
                        · {{ number_format($summary['sold_purchase_qty'], 4) }} {{ $ingredient['purchase_unit'] }}
                    @endif
                </p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Adjustments</p>
                <p class="mt-1 text-lg font-bold text-gray-900 tabular-nums">
                    <span class="text-green-700">+{{ number_format($summary['adjusted_in_qty'], 2) }}</span>
                    <span class="text-gray-400 mx-1">/</span>
                    <span class="text-red-700">−{{ number_format($summary['adjusted_out_qty'], 2) }}</span>
                </p>
                <p class="text-xs text-gray-500 mt-1">In / out ({{ $ingredient['consumption_unit'] }})</p>
            </div>
            <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current stock</p>
                <p class="mt-1 text-2xl font-bold text-indigo-700 tabular-nums">{{ number_format($summary['current_qty'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $ingredient['consumption_unit'] }}
                    @if($ingredient['purchase_unit'] !== $ingredient['consumption_unit'])
                        · {{ number_format($summary['current_purchase_qty'], 4) }} {{ $ingredient['purchase_unit'] }}
                    @endif
                </p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Timeline</h3>
                <p class="text-sm text-gray-500">{{ number_format($summary['event_count']) }} event(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">When</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Detail</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty change</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Line cost</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr class="bg-slate-50">
                            <td class="px-4 py-3 text-sm text-gray-500" colspan="4">Opening balance (before {{ format_date($from, $branchId) }})</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-400 tabular-nums">—</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 tabular-nums">
                                {{ number_format($opening['qty'], 2) }}
                                <span class="text-gray-500 font-normal">{{ $ingredient['consumption_unit'] }}</span>
                                @if($ingredient['purchase_unit'] !== $ingredient['consumption_unit'])
                                    <div class="text-xs text-gray-500 font-normal">
                                        {{ number_format($opening['purchase_qty'], 4) }} {{ $ingredient['purchase_unit'] }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right text-gray-400">—</td>
                            <td class="px-4 py-3 text-sm text-gray-400">—</td>
                        </tr>
                        @forelse($ledger['rows'] as $row)
                            @php
                                $badge = match ($row['kind']) {
                                    'purchase' => 'bg-green-100 text-green-800',
                                    'purchase_return' => 'bg-orange-100 text-orange-800',
                                    'sale' => 'bg-red-100 text-red-800',
                                    'adjustment_in' => 'bg-emerald-100 text-emerald-800',
                                    'adjustment_out', 'waste' => 'bg-amber-100 text-amber-800',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                                $qtyClass = $row['signed_qty'] >= 0 ? 'text-green-700' : 'text-red-700';
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                    {{ $row['occurred_at'] ? format_datetime($row['occurred_at'], $branchId) : '—' }}
                                    @if($row['business_date'])
                                        <div class="text-xs text-gray-400">Biz {{ format_date($row['business_date'], $branchId) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                        {{ $row['kind_label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    @if($row['reference_type'] === 'purchase' && $row['reference_id'])
                                        <a href="{{ route('purchases.show', $row['reference_id']) }}" class="text-indigo-700 hover:text-indigo-900 font-medium">
                                            {{ $row['reference_label'] }}
                                        </a>
                                    @elseif($row['reference_type'] === 'purchase_return' && $row['reference_id'])
                                        <a href="{{ route('purchase-returns.show', $row['reference_id']) }}" class="text-indigo-700 hover:text-indigo-900 font-medium">
                                            {{ $row['reference_label'] }}
                                        </a>
                                    @elseif($row['reference_type'] === 'order' && $row['reference_id'])
                                        <a href="{{ route('order-management.show', $row['reference_id']) }}" class="text-indigo-700 hover:text-indigo-900 font-medium">
                                            {{ $row['reference_label'] }}
                                        </a>
                                    @elseif($row['reference_type'] === 'adjustment' && $row['reference_id'])
                                        <a href="{{ route('inventory.adjustment.show', $row['reference_id']) }}" class="text-indigo-700 hover:text-indigo-900 font-medium">
                                            {{ $row['reference_label'] }}
                                        </a>
                                    @else
                                        {{ $row['reference_label'] ?: '—' }}
                                    @endif
                                    @if($row['branch'] && ! $selectedBranch)
                                        <div class="text-xs text-gray-400">{{ $row['branch'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $row['detail'] ?: '—' }}
                                    @if($row['notes'])
                                        <div class="text-xs text-gray-400 mt-0.5">{{ $row['notes'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-medium tabular-nums {{ $qtyClass }} whitespace-nowrap">
                                    {{ $row['signed_qty'] >= 0 ? '+' : '' }}{{ number_format($row['signed_qty'], 2) }}
                                    <span class="text-gray-500 font-normal">{{ $ingredient['consumption_unit'] }}</span>
                                    @if($ingredient['purchase_unit'] !== $ingredient['consumption_unit'])
                                        <div class="text-xs text-gray-500 font-normal">
                                            {{ $row['qty_purchase'] >= 0 ? '+' : '' }}{{ number_format($row['qty_purchase'], 4) }}
                                            {{ $ingredient['purchase_unit'] }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 tabular-nums whitespace-nowrap">
                                    {{ number_format($row['balance_qty'], 2) }}
                                    <span class="text-gray-500 font-normal">{{ $ingredient['consumption_unit'] }}</span>
                                    @if($ingredient['purchase_unit'] !== $ingredient['consumption_unit'])
                                        <div class="text-xs text-gray-500 font-normal">
                                            {{ number_format($row['balance_purchase_qty'], 4) }} {{ $ingredient['purchase_unit'] }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right tabular-nums text-gray-700 whitespace-nowrap">
                                    {{ format_currency($row['line_cost']) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                    {{ $row['created_by'] ?: '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">
                                    No purchases, sales, or adjustments for this ingredient in the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 text-xs text-gray-500">
                Qty changes and balances use the stock (consumption) unit. Purchase-unit equivalents are shown when units differ.
                Opening is inferred from current stock minus period net change.
            </div>
        </div>

        @if($ledger['batches']->isNotEmpty())
            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-900">Current stock batches</h3>
                    <p class="text-sm text-gray-500 mt-0.5">On-hand FIFO batches right now (not limited to the date range).</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit cost</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last restocked</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($ledger['batches'] as $batch)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $batch['branch'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-right tabular-nums text-gray-900">
                                        {{ number_format($batch['quantity'], 2) }} {{ $ingredient['consumption_unit'] }}
                                        @if($ingredient['purchase_unit'] !== $ingredient['consumption_unit'])
                                            <div class="text-xs text-gray-500">
                                                {{ number_format($batch['purchase_quantity'], 4) }} {{ $ingredient['purchase_unit'] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right tabular-nums text-gray-700">
                                        {{ format_currency($batch['unit_cost']) }}
                                        <span class="text-xs text-gray-500">/ {{ $ingredient['consumption_unit'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $batch['last_restocked_at'] ? format_datetime($batch['last_restocked_at'], $branchId) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
