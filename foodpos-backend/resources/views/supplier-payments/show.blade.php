@extends('layouts.app')

@section('title', 'Supplier Payment Details')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Supplier Payment Details</h1>
                <p class="mt-1 text-sm text-gray-500">Payment #{{ $supplierPayment->payment_number }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if(auth()->user()->hasAppPermission('supplier-payments.destroy'))
                    <form action="{{ route('supplier-payments.destroy', $supplierPayment) }}"
                          method="POST"
                          class="inline"
                          onsubmit="return confirm('Delete this payment and restore supplier and payment source balances?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 h-12 border border-red-200 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                            <i class="fas fa-trash mr-2"></i>
                            Delete
                        </button>
                    </form>
                @endif
                <a href="{{ route('supplier-payments.index') }}" 
                   class="text-sm text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <!-- Payment Information -->
            <div>
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Information</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Payment Number</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $supplierPayment->payment_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Payment Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ format_date($supplierPayment->payment_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Supplier</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $supplierPayment->supplier->name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Branch</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $supplierPayment->branch->name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Total Amount</dt>
                        <dd class="mt-1 text-sm font-semibold text-red-600">{{ format_currency($supplierPayment->total_amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Payment source</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $supplierPayment->moneySource->name ?? ucfirst($supplierPayment->payment_method ?? 'N/A') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Account</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $supplierPayment->account->name ?? 'N/A' }}</dd>
                    </div>
                    @if($supplierPayment->notes)
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Notes</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $supplierPayment->notes }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            <!-- Paid Purchases -->
            @if($supplierPayment->purchases->count() > 0)
            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Paid Purchases</h2>
                <div class="overflow-x-auto">
                    <table class="listing-table min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($supplierPayment->purchases as $purchase)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $purchase->purchase_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ format_date($purchase->purchase_date) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ format_currency($purchase->total_amount) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                                        {{ format_currency($purchase->pivot->amount) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

