<div class="report-hub-panel">
    @if($branchError)
        <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900 mb-4">
            {{ $branchError }}
        </div>
    @endif

    @if($statement && $party)
        @php
            $balanceColor = match (true) {
                abs((float) $partyBalance) < 0.009 => 'text-green-600',
                $type === 'customer' && (float) $partyBalance > 0 => 'text-amber-700',
                $type === 'employee' && (float) $partyBalance > 0 => 'text-green-700',
                (float) $partyBalance > 0 => 'text-red-600',
                default => 'text-red-700',
            };
        @endphp
        <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900">{{ $party->name }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ $typeLabel }} statement
                        @if($branch)
                            · {{ $branch->name }}
                        @endif
                        @if($from || $to)
                            ·
                            @if($from && $to)
                                {{ format_date($from) }} – {{ format_date($to) }}
                            @elseif($from)
                                From {{ format_date($from) }}
                            @else
                                Until {{ format_date($to) }}
                            @endif
                        @else
                            · All dates
                        @endif
                    </p>
                </div>
                <div class="flex flex-col sm:items-end gap-2 shrink-0">
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ $type === 'employee' ? 'Balance' : 'Outstanding' }}</p>
                        <p class="text-xl font-bold {{ $balanceColor }}">
                            {{ format_currency(abs((float) $partyBalance)) }}
                            @if($type === 'employee' && abs((float) $partyBalance) >= 0.009)
                                <span class="text-sm font-medium">{{ (float) $partyBalance > 0 ? 'payable' : 'advance' }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $partyBalanceHint }}</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment source</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Debit (DR)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit (CR)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($statement['lines'] as $line)
                            <tr @class(['bg-slate-50' => ($line['type'] ?? '') === 'opening_balance'])>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900">{{ format_date($line['date_display']) }}</td>
                                <td class="px-6 py-3 text-sm {{ ($line['type'] ?? '') === 'opening_balance' ? 'font-semibold text-gray-900' : 'text-gray-700' }}">{{ $line['label'] }}</td>
                                <td class="px-6 py-3 text-sm">
                                    @if($line['url'])
                                        <a href="{{ $line['url'] }}" class="text-indigo-600 hover:text-indigo-900 font-medium">{{ $line['reference'] }}</a>
                                    @else
                                        <span class="text-gray-900">{{ $line['reference'] }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $line['money_source'] ?? '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right tabular-nums text-gray-900">
                                    {{ $line['debit'] > 0 ? format_currency($line['debit']) : '—' }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right tabular-nums text-gray-900">
                                    {{ $line['credit'] > 0 ? format_currency($line['credit']) : '—' }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-medium tabular-nums text-gray-900">
                                    {{ format_currency($line['balance']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                    No transactions found for this {{ strtolower($typeLabel) }} in the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($statement['lines']) > 0)
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td colspan="6" class="px-6 py-3 text-sm font-semibold text-gray-700 text-right">Closing balance (this branch, selected period)</td>
                                <td class="px-6 py-3 text-sm font-bold text-right tabular-nums text-indigo-700">{{ format_currency($statement['closing_balance']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl shadow border border-gray-200 px-6 py-12 text-center text-sm text-gray-500">
            Select a party type and search for a customer, supplier, or employee, then click Apply.
        </div>
    @endif
</div>
