@extends('layouts.app')

@section('title', $invoice->invoice_number)

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900">{{ $invoice->invoice_number }}</h1>
                @include('platform-invoices._status-badge', ['status' => $invoice->status, 'overdue' => $invoice->isOverdue()])
            </div>
            <p class="mt-1 text-sm text-gray-500">{{ $invoice->company->name }} · {{ $invoice->currency }} · {{ $invoice->billingIntervalLabel() }} · Issued {{ $invoice->issue_date->format('M j, Y') }} · Due {{ $invoice->due_date->format('M j, Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($invoice->status === 'draft')
                <form action="{{ route('platform-invoices.send', $invoice) }}" method="POST" onsubmit="return confirm('Mark this invoice as sent?');">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
                        <i class="fas fa-paper-plane mr-2"></i> Mark sent
                    </button>
                </form>
            @endif
            @if($invoice->isEditable())
                <a href="{{ route('platform-invoices.edit', $invoice) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-edit mr-2"></i> Edit
                </a>
            @endif
            <a href="{{ route('platform-invoices.print', $invoice) }}" target="_blank" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                <i class="fas fa-print mr-2"></i> Print
            </a>
            @if($invoice->amount_paid <= 0 && $invoice->status !== 'void')
                <form action="{{ route('platform-invoices.void', $invoice) }}" method="POST" onsubmit="return confirm('Void this invoice?');">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-red-300 text-red-700 rounded-lg text-sm font-medium hover:bg-red-50">
                        Void
                    </button>
                </form>
            @endif
            <a href="{{ route('platform-invoices.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Back</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Invoice details</h2>
                </div>
                <div class="p-6 space-y-4">
                    @if($invoice->period_start && $invoice->period_end)
                        <p class="text-sm text-gray-600">Billing period: {{ $invoice->period_start->format('M j, Y') }} – {{ $invoice->period_end->format('M j, Y') }}</p>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="listing-table min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Unit</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($invoice->items as $item)
                                    <tr>
                                        <td class="px-3 py-3 text-sm text-gray-900">{{ $item->description }}</td>
                                        <td class="px-3 py-3 text-sm text-right text-gray-600">{{ format_quantity((float) $item->quantity) }}</td>
                                        <td class="px-3 py-3 text-sm text-right text-gray-600">{{ format_platform_currency((float) $item->unit_price, $invoice->currency) }}</td>
                                        <td class="px-3 py-3 text-sm text-right font-medium text-gray-900">{{ format_platform_currency((float) $item->line_total, $invoice->currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end">
                        <dl class="w-full max-w-xs space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-600">Subtotal</dt><dd>{{ format_platform_currency((float) $invoice->subtotal, $invoice->currency) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Tax</dt><dd>{{ format_platform_currency((float) $invoice->tax_amount, $invoice->currency) }}</dd></div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold text-base"><dt>Total</dt><dd>{{ $invoice->formattedTotal() }}</dd></div>
                            <div class="flex justify-between text-green-700"><dt>Paid</dt><dd>{{ format_platform_currency($invoice->amount_paid, $invoice->currency) }}</dd></div>
                            <div class="flex justify-between font-semibold {{ $invoice->balance_due > 0 ? 'text-amber-700' : 'text-green-700' }}"><dt>Balance due</dt><dd>{{ $invoice->formattedBalanceDue() }}</dd></div>
                        </dl>
                    </div>

                    @if($invoice->notes)
                        <div class="rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                            <p class="font-medium text-gray-900 mb-1">Notes</p>
                            <p class="whitespace-pre-wrap">{{ $invoice->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-900">Payments received</h2>
                </div>
                @if($invoice->payments->isEmpty())
                    <div class="p-6 text-sm text-gray-500">No payments recorded yet.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="listing-table min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($invoice->payments as $payment)
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ $payment->payment_date->format('M j, Y') }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-600">{{ $payment->paymentMethodLabel() }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-600">{{ $payment->reference ?: '—' }}</td>
                                        <td class="px-6 py-4 text-sm text-right font-medium text-green-700">{{ format_platform_currency((float) $payment->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Bill to</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">Company</dt><dd class="font-medium text-gray-900">{{ $invoice->company->name }}</dd></div>
                    @if($invoice->company->email)<div><dt class="text-gray-500">Email</dt><dd class="text-gray-900">{{ $invoice->company->email }}</dd></div>@endif
                    @if($invoice->company->phone)<div><dt class="text-gray-500">Phone</dt><dd class="text-gray-900">{{ $invoice->company->phone }}</dd></div>@endif
                    @if($invoice->company->address)<div><dt class="text-gray-500">Address</dt><dd class="text-gray-900 whitespace-pre-wrap">{{ $invoice->company->address }}</dd></div>@endif
                </dl>
            </div>

            @if($invoice->balance_due > 0 && ! in_array($invoice->status, ['void', 'paid'], true))
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-1">Receive payment</h2>
                    <p class="text-sm text-gray-500 mb-4">Balance due: <span class="font-semibold text-amber-700">{{ $invoice->formattedBalanceDue() }}</span></p>

                    <form action="{{ route('platform-invoices.payments.store', $invoice) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">Payment date</label>
                            <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('payment_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                            <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance_due }}" name="amount" id="amount" value="{{ old('amount', $invoice->balance_due) }}" required class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Method</label>
                            <select name="payment_method" id="payment_method" required class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($paymentMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method', 'bank_transfer') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="reference" class="block text-sm font-medium text-gray-700 mb-1">Reference</label>
                            <input type="text" name="reference" id="reference" value="{{ old('reference') }}" placeholder="Txn ID, cheque #, etc." class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="payment_notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" id="payment_notes" rows="2" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700">
                            <i class="fas fa-hand-holding-usd mr-2"></i> Record payment
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
