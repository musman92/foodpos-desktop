@extends('layouts.app')

@section('title', 'Profit & Loss Report')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Profit &amp; Loss</h1>
        <p class="mt-1 text-sm text-gray-500">Income statement for the selected branch and date range.</p>
    </div>

    <form method="get" action="{{ route('reports.profit-loss') }}" class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            @if($availableBranches->isNotEmpty())
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                    <select name="branch_id" id="branch_id" class="block w-full filter-control md:w-48">
                        @if(show_branch_ui() && $availableBranches->count() > 1)
                            <option value="">All branches</option>
                        @endif
                        @foreach($availableBranches as $b)
                            <option value="{{ $b->id }}" {{ (request('branch_id', optional($selectedBranch)->id ?? null) == $b->id) ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">Start date</label>
                <input type="date" name="from" id="from" value="{{ $from }}" class="block w-full filter-control md:w-40" required>
            </div>
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">End date</label>
                <input type="date" name="to" id="to" value="{{ $to }}" class="block w-full filter-control md:w-40" required>
            </div>
            <div>
                <button type="submit" name="generate" value="1" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-file-invoice-dollar mr-2"></i>Generate Report
                </button>
            </div>
        </div>
    </form>

    @if($showReport && $report)
        <div class="flex justify-end mb-4">
            <a href="{{ route('reports.profit-loss.pdf', request()->only(['branch_id', 'from', 'to'])) }}"
               class="inline-flex items-center justify-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                <i class="fas fa-file-pdf mr-2 text-red-600"></i>Export PDF
            </a>
        </div>

        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-200 bg-gray-50">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Profit &amp; Loss Statement</h2>
                        <p class="text-sm text-gray-600 mt-1">{{ $report['period_label'] }}</p>
                        @if($selectedBranch)
                            <p class="text-sm text-gray-500">{{ $selectedBranch->name }}</p>
                        @elseif($availableBranches->count() > 1)
                            <p class="text-sm text-gray-500">All branches</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Net profit</p>
                        <p class="text-2xl font-bold {{ $report['net_profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ format_currency($report['net_profit']) }}
                        </p>
                        @if($report['net_margin_percent'] !== null)
                            <p class="text-sm text-gray-500">{{ number_format($report['net_margin_percent'], 1) }}% net margin</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="px-6 py-5 space-y-8">
                <section>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">Revenue</h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between gap-4 text-sm">
                            <dt class="text-gray-700">Gross sales</dt>
                            <dd class="font-medium text-gray-900 tabular-nums">{{ format_currency($report['revenue']['gross_sales']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 text-sm">
                            <dt class="text-gray-700">Less: Discounts</dt>
                            <dd class="font-medium text-red-700 tabular-nums">({{ format_currency($report['revenue']['discounts']) }})</dd>
                        </div>
                        <div class="flex justify-between gap-4 text-sm">
                            <dt class="text-gray-700">Less: Refunds</dt>
                            <dd class="font-medium text-red-700 tabular-nums">({{ format_currency($report['revenue']['refunds']) }})</dd>
                        </div>
                        <div class="flex justify-between gap-4 pt-2 border-t border-gray-200 text-sm">
                            <dt class="font-semibold text-gray-900">Net sales</dt>
                            <dd class="font-semibold text-gray-900 tabular-nums">{{ format_currency($report['revenue']['net_sales']) }}</dd>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-gray-500">{{ number_format($report['revenue']['order_count']) }} completed orders in period (excludes tax)</p>
                </section>

                <section>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">Cost of goods sold</h3>
                    <dl class="space-y-2">
                        <div class="flex justify-between gap-4 text-sm">
                            <dt class="text-gray-700">Recipe / item cost (sold)</dt>
                            <dd class="font-medium text-gray-900 tabular-nums">{{ format_currency($report['cogs']['sold_cost']) }}</dd>
                        </div>
                        @if($report['cogs']['refund_cost'] > 0)
                            <div class="flex justify-between gap-4 text-sm">
                                <dt class="text-gray-700">Less: Cost reversed (refunds)</dt>
                                <dd class="font-medium text-green-700 tabular-nums">({{ format_currency($report['cogs']['refund_cost']) }})</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-4 pt-2 border-t border-gray-200 text-sm">
                            <dt class="font-semibold text-gray-900">Total COGS</dt>
                            <dd class="font-semibold text-gray-900 tabular-nums">{{ format_currency($report['cogs']['total']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 pt-2 border-t border-gray-200 text-sm">
                            <dt class="font-semibold text-gray-900">Gross profit</dt>
                            <dd class="font-semibold {{ $report['cogs']['gross_profit'] >= 0 ? 'text-green-700' : 'text-red-700' }} tabular-nums">
                                {{ format_currency($report['cogs']['gross_profit']) }}
                                @if($report['cogs']['gross_margin_percent'] !== null)
                                    <span class="text-gray-500 font-normal">({{ number_format($report['cogs']['gross_margin_percent'], 1) }}%)</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                    @if($report['cogs']['lines_without_cost'] > 0)
                        <p class="mt-2 text-xs text-amber-700">
                            {{ number_format($report['cogs']['lines_without_cost']) }} sold line(s) had no recipe/item cost — COGS may be understated.
                        </p>
                    @endif
                </section>

                <section>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">Operating expenses</h3>
                    @if($report['operating_expenses']['categories']->isNotEmpty())
                        <dl class="space-y-2">
                            @foreach($report['operating_expenses']['categories'] as $row)
                                <div class="flex justify-between gap-4 text-sm">
                                    <dt class="text-gray-700">{{ $row['label'] }}</dt>
                                    <dd class="font-medium text-gray-900 tabular-nums">{{ format_currency($row['amount']) }}</dd>
                                </div>
                            @endforeach
                            <div class="flex justify-between gap-4 pt-2 border-t border-gray-200 text-sm">
                                <dt class="font-semibold text-gray-900">Total operating expenses</dt>
                                <dd class="font-semibold text-gray-900 tabular-nums">{{ format_currency($report['operating_expenses']['total']) }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="text-sm text-gray-500">No operating expenses recorded for this period.</p>
                    @endif
                </section>

                <section class="pt-4 border-t-2 border-gray-300">
                    <div class="flex justify-between gap-4">
                        <dt class="text-base font-bold text-gray-900">Net profit (loss)</dt>
                        <dd class="text-base font-bold {{ $report['net_profit'] >= 0 ? 'text-green-700' : 'text-red-700' }} tabular-nums">
                            {{ format_currency($report['net_profit']) }}
                        </dd>
                    </div>
                </section>
            </div>
        </div>

        <p class="mt-4 text-xs text-gray-500">
            Revenue is net of discounts and refunds (tax excluded). COGS uses recipe/item costs at report time. Purchases and supplier payments are not included as operating expenses.
        </p>
    @elseif($showReport)
        <div class="bg-white rounded-lg shadow border border-gray-200 p-8 text-center text-gray-500">
            No data available for the selected period.
        </div>
    @else
        <div class="bg-white rounded-lg shadow border border-dashed border-gray-300 p-8 text-center">
            <i class="fas fa-calendar-alt text-3xl text-gray-300 mb-3"></i>
            <p class="text-gray-600">Select a start and end date, then click <strong>Generate Report</strong>.</p>
        </div>
    @endif
</div>
@endsection
