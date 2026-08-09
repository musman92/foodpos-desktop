@php
    $reportMeta = match ($reportType) {
        'receivable' => ['title' => 'Accounts Receivable', 'partyLabel' => 'Customer', 'totalColor' => 'text-amber-700', 'amountLabel' => 'Outstanding', 'statementType' => 'customer'],
        'payable' => ['title' => 'Accounts Payable', 'partyLabel' => 'Supplier', 'totalColor' => 'text-red-700', 'amountLabel' => 'Outstanding', 'statementType' => 'supplier'],
        'customer-credit' => ['title' => 'Customer Credits', 'partyLabel' => 'Customer', 'totalColor' => 'text-emerald-700', 'amountLabel' => 'Credit available', 'statementType' => 'customer'],
        'supplier-prepayment' => ['title' => 'Supplier Prepayments', 'partyLabel' => 'Supplier', 'totalColor' => 'text-emerald-700', 'amountLabel' => 'Prepaid', 'statementType' => 'supplier'],
        default => ['title' => 'Outstanding Report', 'partyLabel' => 'Party', 'totalColor' => 'text-gray-700', 'amountLabel' => 'Amount', 'statementType' => 'customer'],
    };
    extract($reportMeta);
@endphp
<div id="outstanding-report-printable" class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
    <div class="px-6 py-5 border-b border-gray-200 bg-gray-50">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ $title }}</h2>
                <p class="text-sm text-gray-600 mt-1">As of {{ format_date($report['as_of']) }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-gray-500">Total {{ strtolower($amountLabel) }}</p>
                <p class="text-2xl font-bold {{ $totalColor }}">{{ format_currency($report['total']) }}</p>
                <p class="text-sm text-gray-500">{{ number_format($report['party_count']) }} {{ Str::plural(strtolower($partyLabel), $report['party_count']) }}</p>
            </div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="listing-table min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $partyLabel }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ $amountLabel }}</th>
                    @if(auth()->user()->hasAppPermission('account-statements.index'))
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Statement</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($report['rows'] as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $row['name'] }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600">{{ $row['contact'] ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-right font-semibold tabular-nums {{ $totalColor }}">{{ format_currency($row['balance']) }}</td>
                        @if(auth()->user()->hasAppPermission('account-statements.index'))
                            <td class="px-6 py-3 text-sm text-right">
                                <a href="{{ route('account-statements.index', ['type' => $statementType, 'party_id' => $row['id']]) }}" class="text-emerald-600 hover:text-emerald-900"><i class="fas fa-file-invoice"></i></a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()->hasAppPermission('account-statements.index') ? 4 : 3 }}" class="px-6 py-12 text-center text-sm text-gray-500">No records for this selection.</td></tr>
                @endforelse
            </tbody>
            @if($report['party_count'] > 0)
                <tfoot class="bg-gray-50 border-t border-gray-200">
                    <tr>
                        <td colspan="2" class="px-6 py-3 text-sm font-semibold text-gray-700 text-right">Total</td>
                        <td class="px-6 py-3 text-sm font-bold text-right tabular-nums {{ $totalColor }}">{{ format_currency($report['total']) }}</td>
                        @if(auth()->user()->hasAppPermission('account-statements.index'))<td></td>@endif
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
