@extends('layouts.app')

@section('title', 'Purchases')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Purchases</h1>
            <p class="mt-1 text-sm text-gray-500">Manage your inventory purchases</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('purchase-returns.create') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-undo mr-2"></i>
                Purchase Return
            </a>
            <a href="{{ route('purchases.create') }}"
               class="inline-flex items-center px-4 py-2 h-12 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                <i class="fas fa-plus mr-2"></i>
                New Purchase
            </a>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', [
            'action' => route('purchases.index'),
            'paginator' => $purchases,
            'perPage' => $perPage,
        ])

        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Purchase #</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Date</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Supplier</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Total</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Payment</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($purchases as $purchase)
                        <tr>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $purchases->firstItem() + $loop->index }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">{{ $purchase->purchase_number }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-600">{{ format_date($purchase->purchase_date) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">{{ $purchase->supplier->name ?? '—' }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-900 tabular-nums">{{ format_currency($purchase->total_amount) }}</td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-700">
                                        {{ $purchase->payment_method === 'credit' ? 'Credit' : ucfirst($purchase->payment_method ?? '—') }}
                                    </span>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $purchase->payment_status === 'paid' ? 'bg-green-100 text-green-800' : ($purchase->payment_status === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                        {{ ucfirst($purchase->payment_status) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('purchases.show', $purchase) }}" class="text-indigo-600 hover:text-indigo-800" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('purchases.edit', $purchase) }}" class="text-gray-600 hover:text-gray-800" title="Edit"><i class="fas fa-edit"></i></a>
                                    <form action="{{ route('purchases.destroy', $purchase) }}"
                                          method="POST"
                                          class="inline purchase-delete-form"
                                          data-validate-url="{{ route('purchases.validate-delete', $purchase) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-shopping-cart text-gray-400 text-4xl mb-4"></i>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No purchases found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Get started by creating a new purchase.</p>
                                    <a href="{{ route('purchases.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        <i class="fas fa-plus mr-2"></i>
                                        New Purchase
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.listing-table-pagination', ['paginator' => $purchases])
    </div>
</div>

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
@endsection
