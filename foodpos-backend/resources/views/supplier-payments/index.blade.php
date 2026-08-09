@extends('layouts.app')

@section('title', 'Supplier Payments')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Supplier Payments</h1>
            <p class="mt-1 text-sm text-gray-500">View and manage supplier payment records</p>
        </div>
        <a href="{{ route('supplier-payments.create') }}"
           class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
            <i class="fas fa-plus mr-2"></i>
            New Payment
        </a>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('supplier-payments.index'),
            'paginator' => $payments,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Payment #</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Supplier</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Branch</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Amount</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Paid via</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Account</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($payments as $payment)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $payments->firstItem() + $loop->index }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">
                                {{ $payment->payment_number }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ format_date($payment->payment_date) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                <span class="font-medium">{{ $payment->supplier->name ?? '—' }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $payment->branch->name ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-red-600 tabular-nums">
                                {{ format_currency($payment->total_amount) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $payment->moneySource->name ?? ucfirst($payment->payment_method ?? '—') }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">
                                {{ $payment->account->name ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('supplier-payments.show', $payment) }}"
                                       class="text-indigo-600 hover:text-indigo-800"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if(auth()->user()->hasAppPermission('supplier-payments.destroy'))
                                        <form action="{{ route('supplier-payments.destroy', $payment) }}"
                                              method="POST"
                                              class="inline"
                                              onsubmit="return confirm('Delete this payment and restore balances?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-red-600 hover:text-red-800"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-money-bill-wave text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No supplier payments found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new supplier payment.</p>
                                    <a href="{{ route('supplier-payments.create') }}"
                                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        New Payment
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $payments])
    </div>
</div>
@endsection
