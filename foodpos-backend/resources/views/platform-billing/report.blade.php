@extends('layouts.app')

@section('title', 'Platform Billing Report')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Billing report</h1>
            <p class="mt-1 text-sm text-gray-500">Evaluate tenant billing — demo companies excluded. Totals may mix currencies if tenants use different payment currencies.</p>
        </div>
        <a href="{{ route('platform-invoices.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
            <i class="fas fa-plus mr-2"></i> New invoice
        </a>
    </div>

    <div class="bg-white shadow rounded-lg p-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label for="start_date" class="block text-xs font-medium text-gray-600 mb-1">From</label>
                <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="end_date" class="block text-xs font-medium text-gray-600 mb-1">To</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="md:col-span-2">
                <label for="company_id" class="block text-xs font-medium text-gray-600 mb-1">Company</label>
                <select name="company_id" id="company_id" class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected($companyId === $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm font-medium hover:bg-slate-800">Apply</button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
            <p class="text-sm font-medium text-gray-500">Invoiced (period)</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">{{ format_platform_currency($summary['total_invoiced']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
            <p class="text-sm font-medium text-gray-500">Collected (period)</p>
            <p class="mt-2 text-2xl font-bold text-green-700">{{ format_platform_currency($summary['total_collected']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
            <p class="text-sm font-medium text-gray-500">Outstanding (all open)</p>
            <p class="mt-2 text-2xl font-bold text-amber-700">{{ format_platform_currency($summary['total_outstanding']) }}</p>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Last 6 months</h2>
        <div class="h-72">
            <canvas id="billingChart"></canvas>
        </div>
    </div>

    @if(count($summary['by_company']) > 0)
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Billable tenants</h2>
                <p class="text-sm text-gray-500">Per-tenant price, currency, and recurring interval.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="listing-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due / trial</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Invoiced</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Collected</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($summary['by_company'] as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $row['company']->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ format_platform_currency($row['billing_amount'], $row['currency']) }}
                                    / {{ $row['billing_interval_label'] }}
                                    <div class="text-xs text-gray-400">{{ $row['billing_status'] }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    @if($row['trial_ends_at'] && $row['trial_ends_at']->isFuture())
                                        Trial until {{ $row['trial_ends_at']->format('M j, Y') }}
                                    @elseif($row['billing_due_date'])
                                        Due {{ $row['billing_due_date']->format('M j, Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-right text-gray-900">{{ format_platform_currency($row['invoiced'], $row['currency']) }}</td>
                                <td class="px-6 py-4 text-sm text-right text-green-700">{{ format_platform_currency($row['collected'], $row['currency']) }}</td>
                                <td class="px-6 py-4 text-sm text-right font-semibold text-amber-700">{{ format_platform_currency($row['outstanding'], $row['currency']) }}</td>
                                <td class="px-6 py-4 text-right text-sm whitespace-nowrap space-x-2">
                                    @if($row['billing_amount'] > 0)
                                        <form action="{{ route('platform-invoices.generate', $row['company']) }}" method="POST" class="inline" onsubmit="return confirm('Generate invoice from billing plan?');">
                                            @csrf
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-900">Generate</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('companies.edit', $row['company']) }}" class="text-gray-600 hover:text-gray-900">Edit plan</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900">Invoices in period</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('platform-invoices.show', $invoice) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">{{ $invoice->invoice_number }}</a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $invoice->company->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">{{ $invoice->formattedTotal() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-amber-700">{{ $invoice->formattedBalanceDue() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">@include('platform-invoices._status-badge', ['status' => $invoice->status, 'overdue' => $invoice->isOverdue()])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">No invoices in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">{{ $invoices->links() }}</div>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const series = @json($monthlySeries);
    const ctx = document.getElementById('billingChart');
    if (!ctx || !series.length) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: series.map(row => row.label),
            datasets: [
                {
                    label: 'Invoiced',
                    data: series.map(row => row.invoiced),
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderRadius: 6,
                },
                {
                    label: 'Collected',
                    data: series.map(row => row.collected),
                    backgroundColor: 'rgba(16, 185, 129, 0.75)',
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true },
            },
        },
    });
});
</script>
@endsection
