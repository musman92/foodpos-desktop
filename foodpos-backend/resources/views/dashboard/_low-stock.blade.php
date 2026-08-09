@php
    $rows = $lowStockItems['rows'] ?? collect();
    $total = (int) ($lowStockItems['total'] ?? 0);
    $branchId = $selectedBranch->id ?? null;
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col h-full overflow-hidden">
    <div class="px-4 sm:px-5 py-4 border-b border-gray-100">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2 min-w-0">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600">
                    <i class="fas fa-boxes text-sm"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">Low Stock</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Ingredients and purchased menu items at or below minimum</p>
                </div>
            </div>
            <div class="shrink-0 rounded-lg border border-red-100 bg-red-50/80 px-3 py-2 text-right">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-red-700">Items low</p>
                <p class="text-lg font-bold tabular-nums text-red-900">{{ number_format($total) }}</p>
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-auto max-h-80">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 sticky top-0 z-10">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">Item</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 hidden md:table-cell">Type</th>
                    <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500">On hand</th>
                    <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500 hidden sm:table-cell">Min</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 hidden sm:table-cell">Unit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                    <tr class="hover:bg-red-50/40">
                        <td class="px-4 py-2.5 font-medium text-gray-900">
                            <span class="truncate block max-w-[10rem] sm:max-w-none" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 hidden md:table-cell">
                            {{ ($row['kind'] ?? 'ingredient') === 'menu_item' ? 'Menu item' : 'Ingredient' }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-red-700">{{ number_format($row['current'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-600 hidden sm:table-cell">{{ number_format($row['min_level'], 2) }}</td>
                        <td class="px-4 py-2.5 text-gray-500 hidden sm:table-cell">{{ $row['unit'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">All tracked items are above minimum stock.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($total > 0)
                <tfoot class="bg-gray-50 border-t border-gray-200">
                    <tr>
                        <td class="px-4 py-2.5 text-sm font-semibold text-gray-700">Total</td>
                        <td colspan="4" class="px-4 py-2.5 text-sm font-bold text-right tabular-nums text-red-700">{{ number_format($total) }} items</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="px-4 sm:px-5 py-3 border-t border-gray-100 bg-gray-50/50">
        <a href="{{ route('inventory.index', array_filter(['branch_id' => $branchId])) }}"
           class="inline-flex items-center text-xs font-medium text-indigo-600 hover:text-indigo-800">
            View inventory
            <i class="fas fa-arrow-right ml-1.5 text-[10px]"></i>
        </a>
    </div>
</div>
