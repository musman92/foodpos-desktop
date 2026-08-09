@php
    $opValues = $operationalComparison['values'] ?? [];
    $opLabels = $operationalComparison['labels'] ?? [];
    $opKeys = $operationalComparison['keys'] ?? [];
    $inflow = (float) ($operationalComparison['cash_inflow'] ?? 0);
    $outflow = (float) ($operationalComparison['cash_outflow'] ?? 0);
    $netFlow = (float) ($operationalComparison['net_flow'] ?? ($inflow - $outflow));

    $rowStyles = [
        'purchases' => ['icon' => 'fa-cart-shopping', 'tone' => 'sky'],
        'sales' => ['icon' => 'fa-arrow-trend-up', 'tone' => 'indigo'],
        'expenses' => ['icon' => 'fa-receipt', 'tone' => 'amber'],
        'supplier_payments' => ['icon' => 'fa-truck', 'tone' => 'rose'],
        'customer_received' => ['icon' => 'fa-hand-holding-dollar', 'tone' => 'emerald'],
    ];
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sm:p-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between mb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                    <i class="fas fa-chart-column text-sm"></i>
                </span>
                <h2 class="text-lg font-semibold text-gray-900">Operational Comparison</h2>
            </div>
            <p class="text-sm text-gray-500 mt-2 ml-11">Cash movement for {{ $periodStats['label'] ?? ($startDate.' – '.$endDate) }}</p>
        </div>

        <div class="grid grid-cols-3 gap-3 lg:min-w-[24rem]">
            <div class="rounded-xl bg-emerald-50/80 border border-emerald-100 px-3 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Inflow</p>
                <p class="text-lg font-bold tabular-nums text-emerald-900 mt-0.5">{{ format_currency($inflow) }}</p>
            </div>
            <div class="rounded-xl bg-rose-50/80 border border-rose-100 px-3 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">Outflow</p>
                <p class="text-lg font-bold tabular-nums text-rose-900 mt-0.5">{{ format_currency($outflow) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Net</p>
                <p class="text-lg font-bold tabular-nums mt-0.5 {{ $netFlow >= 0 ? 'text-gray-900' : 'text-red-600' }}">{{ format_currency($netFlow) }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
        <div class="xl:col-span-8 relative rounded-xl bg-gradient-to-b from-gray-50/80 to-white border border-gray-100 p-3 sm:p-4 min-h-[20rem]">
            <canvas id="dashboardOperationalChart"></canvas>
        </div>

        <div class="xl:col-span-4 flex flex-col gap-2">
            @foreach($opLabels as $index => $label)
                @php
                    $key = $opKeys[$index] ?? '';
                    $style = $rowStyles[$key] ?? ['icon' => 'fa-circle', 'tone' => 'gray'];
                    $value = (float) ($opValues[$index] ?? 0);
                    $toneClasses = [
                        'sky' => 'text-sky-600 bg-sky-50',
                        'indigo' => 'text-indigo-600 bg-indigo-50',
                        'amber' => 'text-amber-600 bg-amber-50',
                        'rose' => 'text-rose-600 bg-rose-50',
                        'emerald' => 'text-emerald-600 bg-emerald-50',
                        'gray' => 'text-gray-600 bg-gray-50',
                    ];
                    $tone = $toneClasses[$style['tone']] ?? $toneClasses['gray'];
                @endphp
                <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-white px-3 py-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $tone }}">
                            <i class="fas {{ $style['icon'] }} text-xs"></i>
                        </span>
                        <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
                    </div>
                    <span class="text-sm font-bold tabular-nums text-gray-900 shrink-0">{{ format_currency($value) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
