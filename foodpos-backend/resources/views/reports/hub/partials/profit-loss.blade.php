<div class="report-hub-panel">
    @if($showReport && $report)
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-200 bg-gray-50">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Profit &amp; Loss Statement</h2>
                        <p class="text-sm text-gray-600 mt-1">{{ $report['period_label'] }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Net profit</p>
                        <p class="text-2xl font-bold {{ $report['net_profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ format_currency($report['net_profit']) }}</p>
                    </div>
                </div>
            </div>
            <div class="px-6 py-5 space-y-8">
                <section>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">Revenue</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt>Gross sales</dt><dd class="font-medium">{{ format_currency($report['revenue']['gross_sales']) }}</dd></div>
                        <div class="flex justify-between"><dt>Less: Discounts</dt><dd class="font-medium text-red-700">({{ format_currency($report['revenue']['discounts']) }})</dd></div>
                        <div class="flex justify-between pt-2 border-t"><dt class="font-semibold">Net sales</dt><dd class="font-semibold">{{ format_currency($report['revenue']['net_sales']) }}</dd></div>
                    </dl>
                </section>
                <section>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">Cost of goods sold</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt>Total COGS</dt><dd class="font-medium">{{ format_currency($report['cogs']['total']) }}</dd></div>
                        <div class="flex justify-between pt-2 border-t"><dt class="font-semibold">Gross profit</dt><dd class="font-semibold text-green-700">{{ format_currency($report['cogs']['gross_profit']) }}</dd></div>
                    </dl>
                </section>
                <section>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">Operating expenses</h3>
                    <p class="text-sm font-semibold">{{ format_currency($report['operating_expenses']['total']) }}</p>
                </section>
            </div>
        </div>
    @else
        @include('reports.hub.partials._empty')
    @endif
</div>
