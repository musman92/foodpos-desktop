@extends('layouts.app')

@section('title', 'Purchase Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Purchase Details</h1>
            <p class="mt-1 text-sm text-gray-500">Purchase #{{ $purchase->purchase_number }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('purchase-returns.create', ['purchase_id' => $purchase->id]) }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-indigo-200 rounded-lg text-sm font-medium text-indigo-700 bg-white hover:bg-indigo-50">
                <i class="fas fa-undo mr-2"></i>
                Return
            </a>
            <a href="{{ route('purchases.edit', $purchase) }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-edit mr-2"></i>
                Edit
            </a>
            <form action="{{ route('purchases.destroy', $purchase) }}"
                  method="POST"
                  class="inline purchase-delete-form"
                  data-validate-url="{{ route('purchases.validate-delete', $purchase) }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 h-12 border border-red-200 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                    <i class="fas fa-trash mr-2"></i>
                    Delete
                </button>
            </form>
            <a href="{{ route('purchases.index') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
        </div>
    </div>
    <!-- Purchase Information Card -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ $purchase->purchase_number }}</h2>
                    <div class="mt-2 flex items-center space-x-3">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $purchase->payment_status === 'paid' ? 'bg-green-100 text-green-800' : ($purchase->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ ucfirst($purchase->payment_status) }}
                        </span>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                            {{ $purchase->payment_method === 'credit' ? 'Credit' : ucfirst($purchase->payment_method) }}
                        </span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-gray-900">{{ format_currency($purchase->total_amount) }}</div>
                    <div class="text-sm text-gray-500">Total Amount</div>
                </div>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">
            <!-- Purchase Information -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Purchase Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Purchase Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ format_date($purchase->purchase_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Supplier</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchase->supplier->name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Branch</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchase->branch->name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created By</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchase->creator->name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Payment source</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($purchase->moneySource)
                                {{ $purchase->moneySource->name }} ({{ $purchase->moneySource->type }})
                            @else
                                Credit
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Amount paid</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ format_currency($purchase->paid_amount) }}</dd>
                    </div>
                    @if($purchase->payment_status !== 'paid')
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Balance due</dt>
                        <dd class="mt-1 text-sm font-semibold text-amber-800">{{ format_currency($purchase->pending_amount) }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            <!-- Purchase Items -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Purchase Items</h3>
                <div class="overflow-x-auto">
                    <table class="listing-table min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expiry</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($purchase->items as $item)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        @if($item->item)
                                            {{ $item->item->name ?? 'N/A' }}
                                        @else
                                            N/A (Type: {{ $item->item_type }}, ID: {{ $item->item_id }})
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                            {{ ucfirst(str_replace('_', ' ', $item->item_type)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $item->quantity }} {{ $item->unit_name ?? '' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ format_currency($item->unit_price) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                                        {{ format_currency($item->total_price) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ $item->expiry_date ? format_date($item->expiry_date) : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-sm font-medium text-gray-900 text-right">Subtotal:</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ format_currency($purchase->subtotal) }}</td>
                                <td></td>
                            </tr>
                            @if($purchase->tax_amount > 0)
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-sm font-medium text-gray-900 text-right">Tax:</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ format_currency($purchase->tax_amount) }}</td>
                                <td></td>
                            </tr>
                            @endif
                            @if($purchase->discount_amount > 0)
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-sm font-medium text-gray-900 text-right">Discount:</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">-{{ format_currency($purchase->discount_amount) }}</td>
                                <td></td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-sm font-bold text-gray-900 text-right">Total:</td>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900">{{ format_currency($purchase->total_amount) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if($purchase->returns->isNotEmpty())
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Returns</h3>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Return #</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($purchase->returns->sortByDesc('return_date') as $return)
                                <tr>
                                    <td class="px-3 py-2">
                                        <a href="{{ route('purchase-returns.show', $return) }}" class="text-indigo-600 hover:text-indigo-800">
                                            {{ $return->return_number }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ format_date($return->return_date) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-900">{{ format_currency($return->total_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            @if($purchase->notes)
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Notes</h3>
                <p class="text-sm text-gray-700">{{ $purchase->notes }}</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    document.querySelectorAll('.purchase-delete-form').forEach((form) => {
        form.addEventListener('submit', async function handler(event) {
            event.preventDefault();
            const validateUrl = form.dataset.validateUrl;
            if (!validateUrl) {
                if (confirm('Delete this purchase and reverse its stock?')) {
                    form.removeEventListener('submit', handler);
                    form.submit();
                }
                return;
            }

            try {
                const response = await fetch(validateUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                const report = await response.json();
                if (!response.ok) {
                    alert('Could not validate this delete. Please try again.');
                    return;
                }

                if (report.blocked) {
                    alert((report.messages || []).map((m) => m.text).join('\n') || report.summary);
                    return;
                }

                const warnings = (report.messages || []).map((m) => '- ' + m.text).join('\n');
                const confirmed = confirm((report.summary || 'Delete this purchase?') + (warnings ? '\n\n' + warnings : ''));
                if (confirmed) {
                    form.removeEventListener('submit', handler);
                    form.submit();
                }
            } catch (error) {
                alert('Could not validate this delete. Please try again.');
            }
        });
    });
});
</script>
@endpush

