@extends('layouts.app')

@section('title', 'Transaction Details')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Transaction Details</h1>
            <p class="mt-1 text-sm text-gray-500">View complete information about this transaction</p>
        </div>
        <div class="flex items-center space-x-3">
            @if($transaction->canBeModifiedBy(auth()->user()) && auth()->user()->hasAppPermission('transactions.update'))
                <a href="{{ route('transactions.edit', $transaction) }}"
                   class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-pen mr-2"></i>
                    Edit
                </a>
            @endif
            @if($transaction->canBeModifiedBy(auth()->user()) && auth()->user()->hasAppPermission('transactions.destroy'))
                <form action="{{ route('transactions.destroy', $transaction) }}"
                      method="POST"
                      onsubmit="return confirm('Delete this manually entered transaction? This will change the related account and money-source balances.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 h-12 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                        <i class="fas fa-trash mr-2"></i>
                        Delete
                    </button>
                </form>
            @endif
            @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                <a href="{{ route('transactions.adjustment.create', $transaction) }}" 
                   class="inline-flex items-center px-4 py-2 h-12 border border-transparent rounded-lg text-sm font-medium text-white bg-orange-600 hover:bg-orange-700">
                    <i class="fas fa-adjust mr-2"></i>
                    Create Adjustment
                </a>
            @endif
            <a href="{{ route('transactions.index') }}" 
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
        </div>
    </div>

    <!-- Transaction Information Card -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r {{ $transaction->type === 'in' ? 'from-green-50 to-emerald-50' : 'from-red-50 to-rose-50' }}">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-16 w-16">
                        <div class="h-16 w-16 rounded-full bg-gradient-to-br {{ $transaction->type === 'in' ? 'from-green-400 to-emerald-500' : 'from-red-400 to-rose-500' }} flex items-center justify-center">
                            <i class="fas {{ $transaction->type === 'in' ? 'fa-arrow-down' : 'fa-arrow-up' }} text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-6">
                        <h2 class="text-2xl font-bold text-gray-900">
                            {{ $transaction->type === 'in' ? '+' : '-' }}{{ format_currency($transaction->amount) }}
                        </h2>
                        <div class="mt-2 flex items-center space-x-3">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $transaction->type === 'in' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ strtoupper($transaction->type) }}
                            </span>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                {{ ucfirst($transaction->payment_method) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">
            <!-- Transaction Information -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Transaction Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Account</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-medium">{{ $transaction->account->name ?? 'N/A' }}</dd>
                        <dd class="text-xs text-gray-500">{{ $transaction->account->type ?? '' }}</dd>
                    </div>
                    @if($transaction->branch)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Branch</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $transaction->branch->name ?? 'N/A' }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Amount</dt>
                        <dd class="mt-1 text-sm font-semibold {{ $transaction->type === 'in' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $transaction->type === 'in' ? '+' : '-' }}{{ format_currency($transaction->amount) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Payment Method</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                {{ ucfirst($transaction->payment_method) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ format_date($transaction->date) }}</dd>
                    </div>
                    @if($transaction->reference_type)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Reference Type</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ ucfirst($transaction->reference_type) }}
                            </span>
                        </dd>
                    </div>
                    @endif
                    @if($transaction->ref_id)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Reference ID</dt>
                        <dd class="mt-1 text-sm text-gray-900">#{{ $transaction->ref_id }}</dd>
                    </div>
                    @endif
                    @if($transaction->notes)
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Notes</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $transaction->notes }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            <!-- Timestamps -->
            <div class="pt-4 border-t border-gray-200">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Created By</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ $transaction->creator->name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Created At</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($transaction->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($transaction->updated_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

