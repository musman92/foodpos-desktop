@php $currencySymbol = currency_symbol(); @endphp
<style>
    @media print {
        .period-closing-expense-dialog { display: none !important; }
        .period-closing-info-btn { display: none !important; }
        .period-closing-print {
            overflow: visible !important;
        }
        .period-closing-print .period-closing-grid {
            display: block !important;
        }
        .period-closing-print .period-closing-col {
            width: 100% !important;
            max-width: 100% !important;
            display: block !important;
            border-right: none !important;
            page-break-inside: auto;
            break-inside: auto;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .period-closing-day-card {
            page-break-inside: avoid;
            break-inside: avoid;
            overflow: visible !important;
        }
    }
</style>
@foreach($report['periods'] as $section)
    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden mb-6 period-closing-print">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900">{{ $section['label'] }}</h2>
            <p class="text-sm text-gray-600">{{ format_date($section['from']) }} – {{ format_date($section['to']) }}</p>
            @if($selectedBranch)
                <p class="text-sm text-gray-500">{{ $selectedBranch->name }}</p>
            @elseif($availableBranches->count() > 1)
                <p class="text-sm text-gray-500">All branches</p>
            @endif
        </div>

        @php $showStock = $section['show_stock'] ?? true; @endphp
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-0 divide-y xl:divide-y-0 xl:divide-x divide-gray-200 period-closing-grid">
            @if($showStock)
                {{-- Available stock (current inventory — shown once for multi-week reports) --}}
                <div class="xl:col-span-4 p-4 period-closing-col">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Available stock</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase text-gray-500 border-b border-gray-200">
                                    <th class="py-2 pr-2">S/No</th>
                                    <th class="py-2 pr-2">Product</th>
                                    <th class="py-2 pr-2 text-right">Rate ({{ $currencySymbol }})</th>
                                    <th class="py-2 pr-2 text-right">Qty</th>
                                    <th class="py-2 text-right">Amount ({{ $currencySymbol }})</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($section['stock'] as $line)
                                    <tr>
                                        <td class="py-2 pr-2 text-gray-500">{{ $line['sno'] }}</td>
                                        <td class="py-2 pr-2 font-medium text-gray-900">{{ $line['product'] }}</td>
                                        <td class="py-2 pr-2 text-right tabular-nums">{{ format_amount($line['rate']) }}</td>
                                        <td class="py-2 pr-2 text-right tabular-nums">{{ format_quantity($line['qty']) }}</td>
                                        <td class="py-2 text-right tabular-nums font-medium">{{ format_amount($line['amount']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-gray-500">No available stock.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($section['stock']) > 0)
                                <tfoot>
                                    <tr class="border-t border-gray-300 font-semibold">
                                        <td colspan="4" class="py-2 pr-2 text-right">Total</td>
                                        <td class="py-2 text-right tabular-nums">{{ format_amount($section['stock_total']) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            @endif

            {{-- Daily sales --}}
            <div class="{{ $showStock ? 'xl:col-span-5' : 'xl:col-span-8' }} p-4 period-closing-col">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Daily sales ({{ $currencySymbol }})</h3>
                <div class="space-y-4">
                    @foreach($section['daily_sales'] as $day)
                        <div class="rounded-lg border border-gray-200 overflow-hidden period-closing-day-card"
                             x-data="{ expenseOpen: false }">
                            <div class="px-3 py-2 bg-gray-100 border-b border-gray-200 flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-900">{{ $day['label'] }}</span>
                                <span class="text-xs text-gray-600">{{ format_date($day['date']) }}</span>
                            </div>
                            <div class="px-3 py-2 space-y-1 text-sm">
                                <div class="flex justify-between font-semibold text-gray-900">
                                    <span>Total daily sale</span>
                                    <span class="tabular-nums">{{ format_amount($day['total_sale']) }}</span>
                                </div>
                                @foreach($day['payments'] as $payment)
                                    <div class="flex justify-between text-gray-600">
                                        <span>{{ $payment['label'] }}</span>
                                        <span class="tabular-nums">{{ format_amount($payment['amount']) }}</span>
                                    </div>
                                @endforeach
                                <div class="flex justify-between text-gray-600">
                                    <span>Cash receivable</span>
                                    <span class="tabular-nums">{{ format_amount($day['cash_receivable'] ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600">
                                    <span>Total receivable</span>
                                    <span class="tabular-nums">{{ format_amount($day['total_receivable'] ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between text-gray-600 items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5">
                                        Expenses
                                        @if(($day['expense_total'] ?? 0) > 0)
                                            <button type="button"
                                                    class="period-closing-info-btn inline-flex items-center justify-center h-5 w-5 rounded-full text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                    title="View expenses"
                                                    aria-label="View expenses for {{ format_date($day['date']) }}"
                                                    @click="expenseOpen = true">
                                                <i class="fas fa-info-circle text-sm"></i>
                                            </button>
                                        @endif
                                    </span>
                                    <span class="tabular-nums">{{ format_amount($day['expense_total'] ?? 0) }}</span>
                                </div>
                                @if(($day['expense_total'] ?? 0) > 0)
                                    <div class="hidden print:block pl-2 space-y-0.5 text-xs text-gray-500 border-l-2 border-gray-200 ml-1">
                                        @foreach(($day['expense_lines'] ?? []) as $line)
                                            <div class="flex justify-between gap-2">
                                                <span>
                                                    {{ $line['label'] }}
                                                    @if(!empty($line['detail']))
                                                        <span class="text-gray-400">— {{ $line['detail'] }}</span>
                                                    @endif
                                                </span>
                                                <span class="tabular-nums whitespace-nowrap">{{ format_amount($line['amount']) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="flex justify-between pt-1 border-t border-gray-100 font-medium text-indigo-700">
                                    <span>Cash in hand</span>
                                    <span class="tabular-nums">{{ format_amount($day['cash_in_hand'] ?? 0) }}</span>
                                </div>
                            </div>

                            @if(($day['expense_total'] ?? 0) > 0)
                                <div x-show="expenseOpen"
                                     x-cloak
                                     class="period-closing-expense-dialog fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
                                     @keydown.escape.window="expenseOpen = false">
                                    <div class="absolute inset-0" @click="expenseOpen = false"></div>
                                    <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden"
                                         @click.stop
                                         role="dialog"
                                         aria-modal="true"
                                         aria-labelledby="day-expenses-title-{{ $section['from'] }}-{{ $day['date'] }}">
                                        <div class="px-4 py-3 border-b border-gray-200 flex items-start justify-between gap-3">
                                            <div>
                                                <h4 id="day-expenses-title-{{ $section['from'] }}-{{ $day['date'] }}"
                                                    class="text-base font-semibold text-gray-900">Expenses</h4>
                                                <p class="text-sm text-gray-500">{{ $day['label'] }} · {{ format_date($day['date']) }}</p>
                                            </div>
                                            <button type="button"
                                                    class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                                                    @click="expenseOpen = false"
                                                    aria-label="Close">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <div class="max-h-80 overflow-y-auto">
                                            <table class="min-w-full text-sm">
                                                <thead class="bg-gray-50 sticky top-0">
                                                    <tr class="text-left text-xs uppercase text-gray-500 border-b border-gray-200">
                                                        <th class="px-4 py-2 font-medium">Item</th>
                                                        <th class="px-4 py-2 font-medium text-right">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    @foreach(($day['expense_lines'] ?? []) as $line)
                                                        <tr>
                                                            <td class="px-4 py-2.5">
                                                                <div class="font-medium text-gray-900">{{ $line['label'] }}</div>
                                                                @if(!empty($line['detail']))
                                                                    <div class="text-xs text-gray-500 mt-0.5">{{ $line['detail'] }}</div>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-2.5 text-right tabular-nums text-gray-900 whitespace-nowrap">
                                                                {{ format_amount($line['amount']) }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-between bg-gray-50">
                                            <span class="text-sm font-semibold text-gray-900">Total</span>
                                            <span class="text-sm font-semibold tabular-nums text-gray-900">{{ format_amount($day['expense_total'] ?? 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Closing --}}
            <div class="{{ $showStock ? 'xl:col-span-3' : 'xl:col-span-4' }} p-4 bg-gray-50/60 period-closing-col">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Closing ({{ currency_symbol() }})</h3>
                @include('reports._period-closing-summary', ['closing' => $section['closing']])
            </div>
        </div>
    </div>
@endforeach
