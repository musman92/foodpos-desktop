@php
    $items = $topFoodItems['items'] ?? collect();
    $label = $topFoodItems['label'] ?? '';
    $totalQuantity = (float) ($topFoodItems['total_quantity'] ?? 0);
    $totalRevenue = (float) ($topFoodItems['total_revenue'] ?? 0);
    $branchId = $selectedBranch->id ?? null;
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col h-full overflow-hidden">
    <div class="px-4 sm:px-5 py-4 border-b border-gray-100">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2 min-w-0">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-yellow-50 text-yellow-600">
                    <i class="fas fa-utensils text-sm"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">Top 10 Food Items</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $label }}</p>
                </div>
            </div>
            <div class="shrink-0 rounded-lg border border-yellow-100 bg-yellow-50/80 px-3 py-2 text-right">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-yellow-700">Total sold</p>
                <p class="text-lg font-bold tabular-nums text-yellow-900">{{ number_format($totalQuantity, 0) }}</p>
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-auto max-h-80">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 sticky top-0 z-10">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 w-10">#</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">Item</th>
                    <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500">Qty</th>
                    <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500">Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($items as $index => $row)
                    <tr class="hover:bg-gray-50/80">
                        <td class="px-4 py-2.5 text-gray-500 tabular-nums align-top">{{ $index + 1 }}</td>
                        <td class="px-4 py-2.5 font-semibold text-gray-900 align-top">
                            <span class="truncate block max-w-[12rem] sm:max-w-none" title="{{ $row->item_name }}">{{ $row->item_name }}</span>
                            @if($row->deal_id)
                                <span class="text-[10px] font-semibold uppercase text-amber-600">Deal</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-900 font-semibold align-top">{{ number_format($row->total_quantity, 0) }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-gray-900 align-top">{{ format_currency($row->total_revenue) }}</td>
                    </tr>
                    @foreach($row->variants ?? [] as $variant)
                        <tr class="hover:bg-gray-50/60 bg-gray-50/30">
                            <td class="px-4 py-1.5"></td>
                            <td class="px-4 py-1.5 text-sm text-gray-600 pl-8">
                                <span class="text-gray-400 mr-1.5">└</span>{{ $variant->label }}
                            </td>
                            <td class="px-4 py-1.5 text-right tabular-nums text-gray-600 text-sm">{{ number_format($variant->total_quantity, 0) }}</td>
                            <td class="px-4 py-1.5 text-right tabular-nums text-gray-600 text-sm">{{ format_currency($variant->total_revenue) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">No sales in this period.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($items->count() > 0)
                <tfoot class="bg-gray-50 border-t border-gray-200">
                    <tr>
                        <td colspan="2" class="px-4 py-2.5 text-sm font-semibold text-gray-700 text-right">Total</td>
                        <td class="px-4 py-2.5 text-sm font-bold text-right tabular-nums text-gray-900">{{ number_format($totalQuantity, 0) }}</td>
                        <td class="px-4 py-2.5 text-sm font-bold text-right tabular-nums text-gray-900">{{ format_currency($totalRevenue) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="px-4 sm:px-5 py-3 border-t border-gray-100 bg-gray-50/50">
        <a href="{{ route('reports.top-selling', array_filter(['branch_id' => $branchId, 'from' => $startDate ?? null, 'to' => $endDate ?? null, 'limit' => 10])) }}"
           class="inline-flex items-center text-xs font-medium text-indigo-600 hover:text-indigo-800">
            View full report
            <i class="fas fa-arrow-right ml-1.5 text-[10px]"></i>
        </a>
    </div>
</div>
