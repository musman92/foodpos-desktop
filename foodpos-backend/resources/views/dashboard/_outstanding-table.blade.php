@php
    $isReceivable = ($type ?? 'receivable') === 'receivable';
    $title = $isReceivable ? 'Customer Receivables' : 'Supplier Payables';
    $partyLabel = $isReceivable ? 'Customer' : 'Supplier';
    $reportRoute = $isReceivable ? 'reports.accounts-receivable' : 'reports.accounts-payable';
    $icon = $isReceivable ? 'fa-user-clock' : 'fa-truck';
    $accentBg = $isReceivable ? 'bg-amber-50 text-amber-600' : 'bg-rose-50 text-rose-600';
    $amountClass = $isReceivable ? 'text-amber-700' : 'text-rose-700';
    $totalBg = $isReceivable ? 'bg-amber-50/80 border-amber-100' : 'bg-rose-50/80 border-rose-100';
    $rows = $report['rows'] ?? collect();
    $total = (float) ($report['total'] ?? 0);
    $partyCount = (int) ($report['party_count'] ?? 0);
    $branchId = $selectedBranch->id ?? null;
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col h-full overflow-hidden">
    <div class="px-4 sm:px-5 py-4 border-b border-gray-100">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-2 min-w-0">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $accentBg }}">
                    <i class="fas {{ $icon }} text-sm"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $partyCount }} {{ Str::plural(strtolower($partyLabel), $partyCount) }} with balance</p>
                </div>
            </div>
            <div class="shrink-0 rounded-lg border px-3 py-2 text-right {{ $totalBg }}">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Total</p>
                <p class="text-lg font-bold tabular-nums {{ $amountClass }}">{{ format_currency($total) }}</p>
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-auto max-h-80">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 sticky top-0 z-10">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $partyLabel }}</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 hidden sm:table-cell">Contact</th>
                    <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500">Outstanding</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                    <tr class="hover:bg-gray-50/80">
                        <td class="px-4 py-2.5 font-medium text-gray-900 max-w-[10rem] sm:max-w-none">
                            <span class="truncate block" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 hidden sm:table-cell max-w-[12rem]">
                            <span class="truncate block" title="{{ $row['contact'] ?? '' }}">{{ $row['contact'] ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right font-semibold tabular-nums {{ $amountClass }}">
                            {{ format_currency($row['balance']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-10 text-center text-sm text-gray-500">
                            No outstanding {{ strtolower(Str::plural($partyLabel)) }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($partyCount > 0)
                <tfoot class="bg-gray-50 border-t border-gray-200">
                    <tr>
                        <td class="px-4 py-2.5 text-sm font-semibold text-gray-700">Total</td>
                        <td class="px-4 py-2.5 hidden sm:table-cell"></td>
                        <td class="px-4 py-2.5 text-sm font-bold text-right tabular-nums {{ $amountClass }}">{{ format_currency($total) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="px-4 sm:px-5 py-3 border-t border-gray-100 bg-gray-50/50">
        <a href="{{ route($reportRoute, array_filter(['branch_id' => $branchId, 'generate' => 1])) }}"
           class="inline-flex items-center text-xs font-medium text-indigo-600 hover:text-indigo-800">
            View full report
            <i class="fas fa-arrow-right ml-1.5 text-[10px]"></i>
        </a>
    </div>
</div>
