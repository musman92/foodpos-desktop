@php
    $reportMeta = match ($reportType) {
        'receivable' => [
            'title' => 'Accounts Receivable',
            'subtitle' => 'Outstanding customer balances — amounts owed to you.',
            'partyLabel' => 'Customer',
            'routeName' => 'reports.accounts-receivable',
            'pdfRouteName' => 'reports.accounts-receivable.pdf',
            'excelRouteName' => 'reports.accounts-receivable.excel',
            'statementType' => 'customer',
            'totalColor' => 'text-amber-700',
            'amountLabel' => 'Outstanding',
        ],
        'payable' => [
            'title' => 'Accounts Payable',
            'subtitle' => 'Outstanding supplier balances — amounts you owe vendors.',
            'partyLabel' => 'Supplier',
            'routeName' => 'reports.accounts-payable',
            'pdfRouteName' => 'reports.accounts-payable.pdf',
            'excelRouteName' => 'reports.accounts-payable.excel',
            'statementType' => 'supplier',
            'totalColor' => 'text-red-700',
            'amountLabel' => 'Outstanding',
        ],
        'customer-credit' => [
            'title' => 'Customer Credits',
            'subtitle' => 'Customer advances and prepayments available for future sales.',
            'partyLabel' => 'Customer',
            'routeName' => 'reports.customer-credits',
            'pdfRouteName' => 'reports.customer-credits.pdf',
            'excelRouteName' => 'reports.customer-credits.excel',
            'statementType' => 'customer',
            'totalColor' => 'text-emerald-700',
            'amountLabel' => 'Credit available',
        ],
        'supplier-prepayment' => [
            'title' => 'Supplier Prepayments',
            'subtitle' => 'Amounts prepaid to suppliers available against future purchases.',
            'partyLabel' => 'Supplier',
            'routeName' => 'reports.supplier-prepayments',
            'pdfRouteName' => 'reports.supplier-prepayments.pdf',
            'excelRouteName' => 'reports.supplier-prepayments.excel',
            'statementType' => 'supplier',
            'totalColor' => 'text-emerald-700',
            'amountLabel' => 'Prepaid',
        ],
        default => [
            'title' => 'Outstanding Report',
            'subtitle' => '',
            'partyLabel' => 'Party',
            'routeName' => 'reports.index',
            'pdfRouteName' => 'reports.index',
            'excelRouteName' => 'reports.index',
            'statementType' => 'customer',
            'totalColor' => 'text-gray-700',
            'amountLabel' => 'Amount',
        ],
    };
    extract($reportMeta);
    $exportParams = request()->only(['branch_id']);
    $branchLabel = $selectedBranch?->name ?? ($availableBranches->count() > 1 ? 'All branches (company total)' : ($availableBranches->first()?->name ?? '—'));
    $printTotalColor = match ($reportType) {
        'receivable' => '#b45309',
        'payable' => '#b91c1c',
        'customer-credit', 'supplier-prepayment' => '#047857',
        default => '#111827',
    };
@endphp

<style>
    .outstanding-report-print-only {
        display: none;
    }

    @media print {
        @page {
            margin: 12mm;
        }

        body {
            background: #fff !important;
        }

        .outstanding-report-no-print,
        .outstanding-report-screen-only,
        body > .flex.h-screen > .hidden.lg\:flex,
        body > .flex.h-screen > .flex-1.flex.flex-col > div:first-child,
        body > .flex.h-screen .fixed.inset-y-0,
        main > .mb-4,
        [x-show="sidebarOpen"] {
            display: none !important;
        }

        body > .flex.h-screen,
        body > .flex.h-screen > .flex-1,
        main {
            display: block !important;
            height: auto !important;
            overflow: visible !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
        }

        .max-w-5xl {
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        #outstanding-report-printable {
            box-shadow: none !important;
            border: none !important;
            border-radius: 0 !important;
            overflow: visible !important;
        }

        .outstanding-report-print-only {
            display: block !important;
        }

        #outstanding-report-printable .listing-table th,
        #outstanding-report-printable .listing-table td {
            color: #111 !important;
            padding: 6px 8px !important;
        }

        #outstanding-report-printable .listing-table thead {
            background: #fff !important;
        }

        #outstanding-report-printable .listing-table th {
            border-bottom: 2px solid #111 !important;
            font-size: 10px !important;
        }

        #outstanding-report-printable .listing-table td,
        #outstanding-report-printable .listing-table tfoot td {
            border-bottom: 1px solid #e5e7eb !important;
        }

        #outstanding-report-printable .listing-table tfoot td {
            border-top: 2px solid #111 !important;
            font-weight: bold !important;
        }

        #outstanding-report-printable .print-amount {
            color: {{ $printTotalColor }} !important;
            font-weight: bold !important;
            white-space: nowrap;
        }
    }
</style>

<div class="max-w-5xl mx-auto">
    <div class="mb-6 outstanding-report-no-print">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
    </div>

    <form method="get" action="{{ route($routeName) }}" class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6 outstanding-report-no-print">
        <div class="flex items-end gap-4">
            @if($availableBranches->isNotEmpty())
                <div class="flex-1 min-w-0">
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                    <select name="branch_id" id="branch_id" class="block w-full filter-control">
                        @if(show_branch_ui() && $availableBranches->count() > 1)
                            <option value="" {{ request('branch_id', optional($selectedBranch)->id ?? '') === '' ? 'selected' : '' }}>All branches (company total)</option>
                        @endif
                        @foreach($availableBranches as $b)
                            <option value="{{ $b->id }}" {{ (string) request('branch_id', optional($selectedBranch)->id ?? $availableBranches->first()->id) === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="shrink-0">
                <button type="submit" name="generate" value="1" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 whitespace-nowrap">
                    <i class="fas fa-sync-alt mr-2"></i>Generate Report
                </button>
            </div>
        </div>
        @if($availableBranches->isNotEmpty())
            <p class="mt-2 text-xs text-gray-500">
                Outstanding amounts use each {{ strtolower($partyLabel) }}&apos;s company ledger balance (same as the {{ strtolower($partyLabel) }} list).
            </p>
        @endif
    </form>

    @if($showReport && $report)
        <div class="flex flex-wrap justify-end gap-3 mb-4 outstanding-report-no-print">
            <button type="button"
                    onclick="window.print()"
                    class="inline-flex items-center justify-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                <i class="fas fa-print mr-2 text-gray-600"></i>Print
            </button>
            <a href="{{ route($excelRouteName, $exportParams) }}"
               class="inline-flex items-center justify-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                <i class="fas fa-file-excel mr-2 text-green-600"></i>Export Excel
            </a>
            <a href="{{ route($pdfRouteName, $exportParams) }}"
               class="inline-flex items-center justify-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                <i class="fas fa-file-pdf mr-2 text-red-600"></i>Export PDF
            </a>
        </div>

        <div id="outstanding-report-printable" class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="outstanding-report-print-only" style="padding: 0 0 12px 0;">
                <div style="text-align: center; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 14px;">
                    <p style="font-size: 20px; font-weight: bold; margin: 0 0 6px 0; text-transform: uppercase;">{{ $businessName }}</p>
                    @if($businessAddress)
                        <p style="font-size: 11px; color: #333; margin: 0 0 3px 0; line-height: 1.45;">{{ $businessAddress }}</p>
                    @endif
                    @if($businessPhone)
                        <p style="font-size: 11px; color: #333; margin: 0; line-height: 1.45;">Tel: {{ $businessPhone }}</p>
                    @endif
                </div>

                <table style="width: 100%; margin: 0 0 12px 0; border-collapse: collapse;">
                    <tr>
                        <td style="vertical-align: top; width: 58%;">
                            <p style="font-size: 16px; font-weight: bold; margin: 0;">{{ $title }}</p>
                            <p style="font-size: 10px; color: #555; margin: 4px 0 0 0;">As of {{ format_date($report['as_of']) }}</p>
                        </td>
                        <td style="vertical-align: top; text-align: right; width: 42%;">
                            <p style="font-size: 10px; color: #444; margin: 0;"><strong>Generated:</strong> {{ format_datetime($generatedAt) }}</p>
                        </td>
                    </tr>
                </table>

                <table style="width: 100%; margin-bottom: 14px; border-collapse: collapse; font-size: 11px;">
                    <tr>
                        <td style="padding: 6px 8px; border: 1px solid #ccc; font-weight: bold; width: 22%;">Branch</td>
                        <td style="padding: 6px 8px; border: 1px solid #ccc;">{{ $branchLabel }}</td>
                        <td style="padding: 6px 8px; border: 1px solid #ccc; font-weight: bold; width: 22%;">{{ Str::plural($partyLabel) }}</td>
                        <td style="padding: 6px 8px; border: 1px solid #ccc;">{{ number_format($report['party_count']) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 8px; border: 1px solid #ccc; font-weight: bold;">Total {{ strtolower($amountLabel) }}</td>
                        <td colspan="3" style="padding: 6px 8px; border: 1px solid #ccc; font-weight: bold; color: {{ $printTotalColor }};">{{ format_currency($report['total']) }}</td>
                    </tr>
                </table>
            </div>

            <div class="px-6 py-5 border-b border-gray-200 bg-gray-50 outstanding-report-screen-only">
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $partyLabel }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $amountLabel }}</th>
                            @if(auth()->user()->hasAppPermission('account-statements.index'))
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider outstanding-report-no-print">Statement</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($report['rows'] as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $row['name'] }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $row['contact'] ?? '—' }}</td>
                                <td class="px-6 py-3 text-sm text-right font-semibold tabular-nums {{ $totalColor }} print-amount">
                                    {{ format_currency($row['balance']) }}
                                </td>
                                @if(auth()->user()->hasAppPermission('account-statements.index'))
                                    <td class="px-6 py-3 text-sm text-right outstanding-report-no-print">
                                        <a href="{{ route('account-statements.index', ['type' => $statementType, 'party_id' => $row['id']]) }}"
                                           class="text-emerald-600 hover:text-emerald-900"
                                           title="Account statement">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->hasAppPermission('account-statements.index') ? 4 : 3 }}" class="px-6 py-12 text-center text-sm text-gray-500">
                                    No {{ strtolower($partyLabel) }} records for this selection.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($report['party_count'] > 0)
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td colspan="2" class="px-6 py-3 text-sm font-semibold text-gray-700 text-right">Total</td>
                                <td class="px-6 py-3 text-sm font-bold text-right tabular-nums {{ $totalColor }} print-amount">{{ format_currency($report['total']) }}</td>
                                @if(auth()->user()->hasAppPermission('account-statements.index'))
                                    <td class="outstanding-report-no-print"></td>
                                @endif
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <p class="outstanding-report-print-only" style="margin-top: 12px; font-size: 9px; color: #666; line-height: 1.5;">
                Amounts reflect current ledger balances on {{ strtolower($partyLabel) }} accounts (same as the {{ strtolower($partyLabel) }} list).
            </p>
        </div>
    @elseif($showReport)
        <div class="bg-white rounded-lg shadow border border-gray-200 p-8 text-center text-gray-500">
            No records for this selection.
        </div>
    @else
        <div class="bg-white rounded-lg shadow border border-dashed border-gray-300 p-8 text-center">
            <i class="fas fa-file-invoice text-3xl text-gray-300 mb-3"></i>
            <p class="text-gray-600">Choose a branch (optional) and click <strong>Generate Report</strong>.</p>
        </div>
    @endif
</div>
