@extends('layouts.app')

@section('title', 'Account Details')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Account Details</h1>
            <p class="mt-1 text-sm text-gray-500">View complete information about this account</p>
        </div>
        <div class="flex items-center space-x-3">
            @if($account->canBeEdited())
                <a href="{{ route('accounts.edit', $account) }}"
                   class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-edit mr-2"></i>
                    Edit Account
                </a>
            @endif
            <a href="{{ route('accounts.index') }}"
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
        </div>
    </div>

    <!-- Account Information Card -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r {{ $account->type === 'income' ? 'from-green-50 to-emerald-50' : 'from-red-50 to-rose-50' }}">
            <div class="flex items-center">
                <div class="flex-shrink-0 h-16 w-16">
                    <div class="h-16 w-16 rounded-full bg-gradient-to-br {{ $account->type === 'income' ? 'from-green-400 to-emerald-500' : 'from-red-400 to-rose-500' }} flex items-center justify-center">
                        <i class="fas {{ $account->type === 'income' ? 'fa-arrow-down' : 'fa-arrow-up' }} text-white text-xl"></i>
                    </div>
                </div>
                <div class="ml-6 flex-1">
                    <h2 class="text-2xl font-bold text-gray-900">{{ $account->name }}</h2>
                    <div class="mt-2 flex items-center space-x-3">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $account->type === 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ ucfirst($account->type) }}
                        </span>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $account->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $account->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        @if(!$account->is_deletable)
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                Default Account
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">
            <!-- Account Information -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Account Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Account Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $account->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Type</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $account->type === 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ ucfirst($account->type) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $account->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $account->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Account Type</dt>
                        <dd class="mt-1">
                            @if($account->is_deletable)
                                <span class="text-sm text-gray-900">User Created</span>
                            @else
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    Default System Account
                                </span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Transactions Summary -->
            @if($account->transactions->count() > 0)
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Transactions Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-sm font-medium text-gray-500">Total Transactions</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ $account->transactions->count() }}</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-sm font-medium text-gray-500">Total Amount</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">
                            {{ format_currency($account->transactions->sum('amount')) }}
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Timestamps -->
            <div class="pt-4 border-t border-gray-200">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Created At</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($account->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($account->updated_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

