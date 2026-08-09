@extends('layouts.app')

@section('title', 'Customer Payment')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Customer payment</h1>
                <p class="mt-1 text-sm text-gray-500">Payment #{{ $customerPayment->payment_number }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if(auth()->user()->hasAppPermission('customer-payments.destroy'))
                    <form action="{{ route('customer-payments.destroy', $customerPayment) }}"
                          method="POST"
                          class="inline"
                          onsubmit="return confirm('Delete this payment and restore customer and payment source balances?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 h-12 border border-red-200 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                            <i class="fas fa-trash mr-2"></i>
                            Delete
                        </button>
                    </form>
                @endif
                <a href="{{ route('customer-payments.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left mr-2"></i>Back to list
                </a>
            </div>
        </div>

        <div class="p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Payment number</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $customerPayment->payment_number }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Payment date</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ format_date($customerPayment->payment_date) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Customer</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        <a href="{{ route('customers.show', $customerPayment->customer) }}" class="text-indigo-600 hover:text-indigo-800">
                            {{ $customerPayment->customer->name ?? 'N/A' }}
                        </a>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Branch</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $customerPayment->branch->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Amount received</dt>
                    <dd class="mt-1 text-sm font-semibold text-green-600">{{ format_currency($customerPayment->amount) }}</dd>
                </div>
                @if((float) ($customerPayment->discount_amount ?? 0) > 0)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Discount / write-off</dt>
                    <dd class="mt-1 text-sm font-semibold text-amber-700">{{ format_currency($customerPayment->discount_amount) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Total applied to balance</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ format_currency((float) $customerPayment->amount + (float) $customerPayment->discount_amount) }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-gray-500">Payment source</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $customerPayment->moneySource->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Recorded by</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $customerPayment->creator->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Customer balance after</dt>
                    <dd class="mt-1 text-sm font-semibold {{ ($customerPayment->customer->balance ?? 0) > 0 ? 'text-amber-700' : 'text-green-600' }}">
                        {{ format_currency($customerPayment->customer->balance ?? 0) }}
                    </dd>
                </div>
                @if($customerPayment->notes)
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Notes</dt>
                        <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $customerPayment->notes }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>
</div>
@endsection
