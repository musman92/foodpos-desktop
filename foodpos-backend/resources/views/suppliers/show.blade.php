@extends('layouts.app')

@section('title', 'Supplier Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Supplier Details</h1>
            <p class="mt-1 text-sm text-gray-500">View complete information about this supplier</p>
        </div>
        <div class="flex items-center space-x-3 flex-wrap gap-2 justify-end">
            @if(auth()->user()->hasAppPermission('account-statements.index'))
                <a href="{{ route('account-statements.index', ['type' => 'supplier', 'party_id' => $supplier->id]) }}"
                   class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-file-invoice mr-2"></i>
                    Account statement
                </a>
            @endif
            @if(auth()->user()->hasAppPermission('supplier-payments.store'))
                <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplier->id]) }}"
                   class="inline-flex items-center px-4 py-2 h-12 border border-transparent rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700">
                    <i class="fas fa-money-bill-wave mr-2"></i>
                    Pay supplier
                </a>
                <a href="{{ route('supplier-payments.advance.create', ['supplier_id' => $supplier->id]) }}"
                   class="inline-flex items-center px-4 py-2 h-12 border border-emerald-300 rounded-lg text-sm font-medium text-emerald-800 bg-emerald-50 hover:bg-emerald-100">
                    <i class="fas fa-piggy-bank mr-2"></i>
                    Pay advance
                </a>
            @endif
            @if(auth()->user()->hasAppPermission('suppliers.adjust-balance'))
                <a href="{{ route('suppliers.balance-adjustment', $supplier) }}"
                   class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-balance-scale mr-2"></i>
                    Adjust balance
                </a>
            @endif
            <a href="{{ route('suppliers.edit', $supplier) }}" 
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-edit mr-2"></i>
                Edit Supplier
            </a>
            <a href="{{ route('suppliers.index') }}" 
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
        </div>
    </div>

    <!-- Supplier Information Card -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 h-16 w-16">
                    <div class="h-16 w-16 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center">
                        <span class="text-white font-bold text-xl">{{ strtoupper(substr($supplier->name, 0, 1)) }}</span>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <h2 class="text-2xl font-bold text-gray-900">{{ $supplier->name }}</h2>
                    <div class="mt-1 flex items-center">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $supplier->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ ucfirst($supplier->status) }}
                        </span>
                        @if($supplier->tax_id)
                            <span class="ml-3 text-sm text-gray-500">
                                <i class="fas fa-id-card mr-1"></i>
                                Tax ID: {{ $supplier->tax_id }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">
            <!-- Company Information -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Company Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if($supplier->code)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Code</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $supplier->code }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Company Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $supplier->name }}</dd>
                    </div>
                    @if($supplier->tax_id)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tax ID</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $supplier->tax_id }}</dd>
                        </div>
                    @endif
                    @if($supplier->address)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Address</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $supplier->address }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Contact Information -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Contact Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if($supplier->contact_person)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Contact Person</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $supplier->contact_person }}</dd>
                        </div>
                    @endif
                    @if($supplier->email)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="mailto:{{ $supplier->email }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $supplier->email }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if($supplier->phone)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="tel:{{ $supplier->phone }}" class="text-indigo-600 hover:text-indigo-900">
                                    <i class="fas fa-phone mr-1"></i>
                                    {{ $supplier->phone }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    @if($supplier->whatsapp)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">WhatsApp</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $supplier->whatsapp) }}" 
                                   target="_blank" 
                                   class="text-green-600 hover:text-green-900">
                                    <i class="fab fa-whatsapp mr-1"></i>
                                    {{ $supplier->whatsapp }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Financial Information -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Financial Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Balance</dt>
                        <dd class="mt-1 text-lg font-semibold {{ (float) $supplier->balance > 0 ? 'text-amber-700' : ((float) $supplier->balance < 0 ? 'text-emerald-700' : 'text-gray-700') }}">
                            {{ format_currency($supplier->balance) }}
                        </dd>
                        <dd class="mt-0.5 text-xs text-gray-500">{{ \App\Support\PartyBalance::supplierStatusLabel((float) $supplier->balance) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Additional Notes -->
            @if($supplier->notes)
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Notes</h3>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $supplier->notes }}</p>
                </div>
            @endif

            <!-- Timestamps -->
            <div class="pt-4 border-t border-gray-200">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Created At</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($supplier->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($supplier->updated_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

