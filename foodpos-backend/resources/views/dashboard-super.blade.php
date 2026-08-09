@extends('layouts.app')

@section('title', 'Super Admin Dashboard')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Super Admin Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Platform overview, tenant billing, and company management.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('platform-billing.report') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-chart-bar mr-2"></i> Billing report
            </a>
            <a href="{{ route('platform-invoices.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
                <i class="fas fa-file-invoice-dollar mr-2"></i> New invoice
            </a>
        </div>
    </div>

    <!-- Platform stats (demo tenants excluded) -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Outstanding</p>
                    @forelse($billing['outstanding_by_currency'] as $row)
                        <p class="text-xl font-bold text-amber-700">{{ format_platform_currency($row['amount'], $row['currency']) }}</p>
                    @empty
                        <p class="text-2xl font-bold text-amber-700">{{ format_platform_currency(0) }}</p>
                    @endforelse
                </div>
                <div class="rounded-lg bg-amber-100 p-3"><i class="fas fa-clock text-amber-600 text-xl"></i></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">{{ $billing['open_invoices'] }} open · {{ $billing['overdue_count'] }} overdue · demo excluded</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Collected (MTD)</p>
                    @forelse($billing['collected_mtd_by_currency'] as $row)
                        <p class="text-xl font-bold text-green-700">{{ format_platform_currency($row['amount'], $row['currency']) }}</p>
                    @empty
                        <p class="text-2xl font-bold text-green-700">{{ format_platform_currency(0) }}</p>
                    @endforelse
                </div>
                <div class="rounded-lg bg-green-100 p-3"><i class="fas fa-hand-holding-usd text-green-600 text-xl"></i></div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Invoiced (MTD)</p>
                    @forelse($billing['invoiced_mtd_by_currency'] as $row)
                        <p class="text-xl font-bold text-gray-900">{{ format_platform_currency($row['amount'], $row['currency']) }}</p>
                    @empty
                        <p class="text-2xl font-bold text-gray-900">{{ format_platform_currency(0) }}</p>
                    @endforelse
                </div>
                <div class="rounded-lg bg-indigo-100 p-3"><i class="fas fa-file-invoice text-indigo-600 text-xl"></i></div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Active companies</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $companies->where('status', 'active')->count() }}</p>
                </div>
                <div class="rounded-lg bg-slate-100 p-3"><i class="fas fa-building text-slate-600 text-xl"></i></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">{{ $companiesCount }} total · {{ $companies->sum('branches_count') }} branches</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white shadow rounded-lg p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Billing trend</h2>
                <a href="{{ route('platform-billing.report') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Full report</a>
            </div>
            <div class="h-64">
                <canvas id="superBillingChart"></canvas>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Outstanding by tenant</h2>
                </div>
                @if(empty($billing['outstanding_tenants']) || $billing['outstanding_tenants']->isEmpty())
                    <div class="p-6 text-sm text-gray-500">No outstanding balances.</div>
                @else
                    <ul class="divide-y divide-gray-200 max-h-64 overflow-y-auto">
                        @foreach($billing['outstanding_tenants'] as $row)
                            <li class="px-6 py-3 flex items-center justify-between hover:bg-gray-50">
                                <a href="{{ route('companies.show', $row['company']) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900 truncate">{{ $row['company']->name }}</a>
                                <span class="text-sm font-semibold text-amber-700 ml-2">{{ format_platform_currency($row['amount'], $row['currency']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Recent invoices</h2>
                <a href="{{ route('platform-invoices.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">View all</a>
            </div>
            @if($recentInvoices->isEmpty())
                <div class="p-6 text-sm text-gray-500">No invoices yet. Create one to start tracking tenant billing.</div>
            @else
                <ul class="divide-y divide-gray-200">
                    @foreach($recentInvoices as $invoice)
                        <li class="px-6 py-4 hover:bg-gray-50">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('platform-invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $invoice->invoice_number }}</a>
                                    <p class="text-sm text-gray-500 truncate">{{ $invoice->company->name }}</p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ $invoice->formattedTotal() }}</p>
                                    @include('platform-invoices._status-badge', ['status' => $invoice->status, 'overdue' => $invoice->isOverdue()])
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <!-- Companies list -->
    <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 bg-slate-50">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-building mr-2 text-slate-600"></i>
                Companies
            </h2>
            <p class="mt-1 text-sm text-gray-500">View and manage all companies in the system.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branches</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($companies as $company)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="font-medium text-gray-900">{{ $company->name }}</div>
                            @if($company->slug)
                                <div class="text-xs text-gray-500">{{ $company->slug }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $company->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($company->status ?? 'N/A') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $company->branches_count ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $company->email ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                            <a href="{{ route('companies.show', $company) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                            <a href="{{ route('companies.edit', $company) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            @if($company->status === 'active')
                                <a href="{{ route('companies.secret-login', $company) }}" class="text-green-600 hover:text-green-900" title="Login as company">Login as</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-building text-4xl text-gray-300 mb-3"></i>
                            <p>No companies yet.</p>
                            <a href="{{ route('companies.create') }}" class="mt-3 inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                <i class="fas fa-plus mr-2"></i>
                                Add Company
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($companies->isNotEmpty())
        <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
            <a href="{{ route('companies.create') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                <i class="fas fa-plus mr-1"></i>
                Add new company
            </a>
        </div>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const series = @json($monthlySeries);
    const canvas = document.getElementById('superBillingChart');
    if (!canvas || !series.length) return;

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: series.map(row => row.label),
            datasets: [
                {
                    label: 'Invoiced',
                    data: series.map(row => row.invoiced),
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: 'Collected',
                    data: series.map(row => row.collected),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
        },
    });
});
</script>
@endsection
