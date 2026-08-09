@php
    $breakdown = $breakdown ?? null;
    $payoutGroups = $breakdown['payout_groups'] ?? [];
@endphp
@if($breakdown)
<div class="space-y-5">
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-4 py-3 bg-gray-50 border-b border-gray-200">
            <span class="text-sm font-medium text-gray-700">Total Sale</span>
            <span class="text-sm font-semibold text-gray-900 tabular-nums">{{ format_currency((float) $breakdown['total_sale']) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-100">
            <span class="text-sm font-medium text-gray-700">COGS</span>
            <span class="text-sm font-semibold text-red-700 tabular-nums">− {{ format_currency((float) $breakdown['cogs']) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-100">
            <span class="text-sm font-medium text-gray-700">Expenses</span>
            <span class="text-sm font-semibold text-red-700 tabular-nums">− {{ format_currency((float) $breakdown['expenses_total']) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-100">
            <span class="text-sm font-medium text-gray-700">Payout</span>
            <span class="text-sm font-semibold text-red-700 tabular-nums">− {{ format_currency((float) $breakdown['payouts_total']) }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 px-4 py-3 bg-emerald-50">
            <span class="text-sm font-semibold text-gray-900">Net Profit</span>
            <span class="text-base font-bold tabular-nums {{ ($breakdown['net_profit'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                {{ format_currency((float) $breakdown['net_profit']) }}
            </span>
        </div>
    </div>

    <p class="text-xs text-gray-500">
        Net Profit = Total Sale − COGS − Expenses − Payout
    </p>

    <div>
        <div class="flex items-center justify-between gap-2 mb-2">
            <h4 class="text-sm font-semibold text-gray-900">Expenses</h4>
            <span class="text-xs text-gray-500 tabular-nums">{{ format_currency((float) $breakdown['expenses_total']) }}</span>
        </div>
        @if(count($breakdown['expenses'] ?? []) > 0)
            <div class="rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
                @foreach($breakdown['expenses'] as $row)
                    <div class="px-4 py-3 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $row['label'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $row['date'] }}
                                @if(!empty($row['detail']))
                                    · {{ $row['detail'] }}
                                @endif
                            </p>
                        </div>
                        <span class="shrink-0 text-sm font-semibold text-gray-900 tabular-nums">{{ format_currency((float) $row['amount']) }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center">No expenses in this period.</p>
        @endif
    </div>

    <div x-data="{ payoutTab: 'all' }">
        <div class="flex items-center justify-between gap-2 mb-2">
            <h4 class="text-sm font-semibold text-gray-900">Payout</h4>
            <span class="text-xs text-gray-500 tabular-nums">{{ format_currency((float) $breakdown['payouts_total']) }}</span>
        </div>

        @if(count($breakdown['payouts'] ?? []) > 0)
            <div class="flex gap-2 overflow-x-auto pb-2 mb-3 -mx-1 px-1">
                <button type="button"
                        @click="payoutTab = 'all'"
                        :class="payoutTab === 'all' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300'"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition">
                    All
                    <span class="tabular-nums opacity-80">{{ format_currency((float) $breakdown['payouts_total']) }}</span>
                </button>
                @foreach($payoutGroups as $index => $group)
                    <button type="button"
                            @click="payoutTab = '{{ $index }}'"
                            :class="payoutTab === '{{ $index }}' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300'"
                            class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition max-w-[14rem]">
                        <span class="truncate">{{ $group['label'] }}</span>
                        <span class="tabular-nums opacity-80 shrink-0">{{ format_currency((float) $group['total']) }}</span>
                    </button>
                @endforeach
            </div>

            <div x-show="payoutTab === 'all'" x-cloak>
                @include('dashboard._net-profit-payout-rows', [
                    'rows' => $breakdown['payouts'],
                    'subtotal' => (float) $breakdown['payouts_total'],
                    'subtotalLabel' => 'All payouts',
                ])
            </div>
            @foreach($payoutGroups as $index => $group)
                <div x-show="payoutTab === '{{ $index }}'" x-cloak>
                    @include('dashboard._net-profit-payout-rows', [
                        'rows' => $group['rows'],
                        'subtotal' => (float) $group['total'],
                        'subtotalLabel' => $group['label'],
                    ])
                </div>
            @endforeach
        @else
            <p class="text-sm text-gray-500 rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center">No payouts in this period.</p>
        @endif
    </div>
</div>
@endif
