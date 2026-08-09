@extends('layouts.app')

@section('title', 'Platform Invoices')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Platform Invoices</h1>
            <p class="mt-1 text-sm text-gray-500">Bill tenant companies for FoodPOS subscriptions and services.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('platform-billing.report') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-chart-bar mr-2"></i> Billing report
            </a>
            <a href="{{ route('platform-invoices.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
                <i class="fas fa-plus mr-2"></i> New invoice
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="q" class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                <input type="text" name="q" id="q" value="{{ request('q') }}" placeholder="Invoice # or company" class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="company_id" class="block text-xs font-medium text-gray-600 mb-1">Company</label>
                <select name="company_id" id="company_id" class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="status" id="status" class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All statuses</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm font-medium hover:bg-slate-800">Filter</button>
                <a href="{{ route('platform-invoices.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issue / Due</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cycle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($invoices as $invoice)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('platform-invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $invoice->invoice_number }}</a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $invoice->company->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $invoice->issue_date->format('M j, Y') }}<br>
                                <span class="{{ $invoice->isOverdue() ? 'text-red-600 font-medium' : '' }}">Due {{ $invoice->due_date->format('M j, Y') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">{{ $invoice->formattedTotal() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium {{ $invoice->balance_due > 0 ? 'text-amber-700' : 'text-green-700' }}">{{ $invoice->formattedBalanceDue() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $invoice->billingIntervalLabel() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @include('platform-invoices._status-badge', ['status' => $invoice->status, 'overdue' => $invoice->isOverdue()])
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                <a href="{{ route('platform-invoices.show', $invoice) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                                <a href="{{ route('platform-invoices.print', $invoice) }}" target="_blank" class="text-gray-600 hover:text-gray-900">Print</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">No invoices yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">{{ $invoices->links() }}</div>
        @endif
    </div>
</div>
@endsection
