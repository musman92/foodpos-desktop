@extends('layouts.app')

@section('title', 'Customer Details')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Customer Details</h1>
            <p class="mt-1 text-sm text-gray-500">View customer information and delivery addresses</p>
        </div>
        <div class="flex items-center space-x-3 flex-wrap gap-2 justify-end">
            <a href="{{ route('customers.index') }}" 
               class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
            @if(auth()->user()->hasAppPermission('account-statements.index'))
                <a href="{{ route('account-statements.index', ['type' => 'customer', 'party_id' => $customer->id]) }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-file-invoice mr-2"></i>
                    Account statement
                </a>
            @endif
            @if(auth()->user()->hasAppPermission('customer-payments.store'))
                <a href="{{ route('customer-payments.create', ['customer_id' => $customer->id]) }}"
                   class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700">
                    <i class="fas fa-hand-holding-usd mr-2"></i>
                    Receive payment
                </a>
                <a href="{{ route('customer-payments.advance.create', ['customer_id' => $customer->id]) }}"
                   class="px-4 py-2 h-12 border border-emerald-300 rounded-lg text-sm font-medium text-emerald-800 bg-emerald-50 hover:bg-emerald-100">
                    <i class="fas fa-piggy-bank mr-2"></i>
                    Receive advance
                </a>
            @endif
            @if(auth()->user()->hasAppPermission('customers.adjust-balance'))
                <a href="{{ route('customers.balance-adjustment', $customer) }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-balance-scale mr-2"></i>
                    Adjust balance
                </a>
            @endif
            <a href="{{ route('customers.edit', $customer) }}" 
               class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                <i class="fas fa-edit mr-2"></i>
                Edit Customer
            </a>
        </div>
    </div>

    <!-- Customer Information Card -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-500 to-purple-600">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="h-16 w-16 rounded-full bg-white flex items-center justify-center">
                        <span class="text-indigo-600 font-bold text-2xl">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                    </div>
                </div>
                <div class="ml-4">
                    <h2 class="text-xl font-semibold text-white">
                        {{ $customer->name }}
                        @if($customer->is_default)
                            <span class="ml-2 px-2 py-1 text-xs font-semibold rounded-full bg-white text-indigo-600">Default Customer</span>
                        @endif
                    </h2>
                    @if($customer->email)
                        <p class="text-indigo-100">{{ $customer->email }}</p>
                    @endif
                </div>
                <div class="ml-auto">
                    <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $customer->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-user mr-2 text-indigo-600"></i>
                        Basic Information
                    </h3>
                    <dl class="space-y-3">
                        @if($customer->code)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Code</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $customer->code }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->name }}</dd>
                        </div>
                        @if($customer->email)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Email</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $customer->email }}</dd>
                            </div>
                        @endif
                        @if($customer->phone)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $customer->phone }}</dd>
                            </div>
                        @endif
                        @if($customer->date_of_birth)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Date of Birth</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ format_date($customer->date_of_birth) }}</dd>
                            </div>
                        @endif
                        @if($customer->gender)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Gender</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($customer->gender) }}</dd>
                            </div>
                        @endif
                        @if($customer->notes)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Notes</dt>
                                <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $customer->notes }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <!-- Additional Information -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-green-600"></i>
                        Additional Information
                    </h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Balance</dt>
                            <dd class="mt-1 text-sm font-semibold {{ (float) $customer->balance > 0 ? 'text-amber-700' : ((float) $customer->balance < 0 ? 'text-emerald-700' : 'text-gray-700') }}">
                                {{ format_currency($customer->balance) }}
                            </dd>
                            <dd class="mt-0.5 text-xs text-gray-500">{{ \App\Support\PartyBalance::customerStatusLabel((float) $customer->balance) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ format_datetime($customer->created_at) }}</dd>
                        </div>
                        @if($customer->updated_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ format_datetime($customer->updated_at) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Delivery Addresses -->
            @if($customer->addresses->count() > 0)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-map-marker-alt mr-2 text-purple-600"></i>
                        Delivery Addresses
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($customer->addresses as $address)
                            <div class="border border-gray-200 rounded-lg p-4 {{ $address->is_default ? 'border-indigo-300 bg-indigo-50' : '' }}">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center">
                                        <h4 class="text-sm font-semibold text-gray-900">
                                            {{ $address->label }}
                                        </h4>
                                        @if($address->is_default)
                                            <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                                <i class="fas fa-star mr-1"></i>Default
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="text-sm text-gray-600 space-y-1">
                                    @if($address->contact_name)
                                        <div>
                                            <i class="fas fa-user mr-1 text-gray-400"></i>
                                            <strong>Contact:</strong> {{ $address->contact_name }}
                                        </div>
                                    @endif
                                    @if($address->contact_phone)
                                        <div>
                                            <i class="fas fa-phone mr-1 text-gray-400"></i>
                                            {{ $address->contact_phone }}
                                        </div>
                                    @endif
                                    <div class="pt-1">
                                        <i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>
                                        {{ $address->address_line_1 }}
                                        @if($address->address_line_2)
                                            <br>{{ $address->address_line_2 }}
                                        @endif
                                        <br>{{ $address->city }}
                                        @if($address->state)
                                            , {{ $address->state }}
                                        @endif
                                        @if($address->postal_code)
                                            {{ $address->postal_code }}
                                        @endif
                                        @if($address->country)
                                            <br>{{ $address->country }}
                                        @endif
                                    </div>
                                    @if($address->delivery_instructions)
                                        <div class="pt-2 border-t border-gray-200">
                                            <i class="fas fa-info-circle mr-1 text-gray-400"></i>
                                            <strong>Instructions:</strong> {{ $address->delivery_instructions }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="text-center py-8">
                        <i class="fas fa-map-marker-alt text-gray-400 text-4xl mb-4"></i>
                        <p class="text-sm text-gray-500">No delivery addresses added yet.</p>
                    </div>
                
            @endif

            @if($customer->payments->isNotEmpty())
                <div class="mt-6 pt-6 border-t border-gray-200">
                    
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-hand-holding-usd mr-2 text-emerald-600"></i>
                            Recent payments
                        </h3>
                        @if(auth()->user()->hasAppPermission('customer-payments.index'))
                            <a href="{{ route('customer-payments.index', ['customer_id' => $customer->id]) }}" class="text-sm text-indigo-600 hover:text-indigo-800">View all</a>
                        @endif
                    
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Payment #</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Date</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Amount</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">Source</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($customer->payments as $payment)
                                    <tr>
                                        <td class="px-4 py-2">
                                            @if(auth()->user()->hasAppPermission('customer-payments.index'))
                                                <a href="{{ route('customer-payments.show', $payment) }}" class="text-indigo-600 hover:text-indigo-800">{{ $payment->payment_number }}</a>
                                            @else
                                                {{ $payment->payment_number }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">{{ format_date($payment->payment_date) }}</td>
                                        <td class="px-4 py-2 font-semibold text-green-600">{{ format_currency($payment->amount) }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ $payment->moneySource->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    
                
            @endif
        
    

@endsection

