@extends('layouts.app')

@section('title', 'Tax Details')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tax Details</h1>
            <p class="mt-1 text-sm text-gray-500">View complete information about this tax</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('taxes.edit', $tax) }}" 
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-edit mr-2"></i>
                Edit Tax
            </a>
            <a href="{{ route('taxes.index') }}" 
               class="inline-flex items-center px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to List
            </a>
        </div>
    </div>

    <!-- Tax Information Card -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-indigo-50">
            <div class="flex items-center">
                <div class="flex-shrink-0 h-16 w-16">
                    <div class="h-16 w-16 rounded-full bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center">
                        <i class="fas fa-percent text-white text-xl"></i>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <h2 class="text-2xl font-bold text-gray-900">{{ $tax->name }}</h2>
                    <div class="mt-1 flex items-center">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $tax->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $tax->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="ml-3 text-2xl font-bold text-gray-900">
                            {{ number_format($tax->percentage, 2) }}%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-6 space-y-6">
            <!-- Tax Information -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">Tax Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tax Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $tax->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Percentage</dt>
                        <dd class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($tax->percentage, 2) }}%</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $tax->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $tax->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Example Calculation -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">Example Calculation</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-medium text-gray-900">{{ format_currency(100) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax ({{ number_format($tax->percentage, 2) }}%):</span>
                        <span class="font-medium text-gray-900">{{ format_currency(100 * ($tax->percentage / 100)) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-200">
                        <span class="font-semibold text-gray-900">Total:</span>
                        <span class="font-bold text-gray-900">{{ format_currency(100 * (1 + $tax->percentage / 100)) }}</span>
                    </div>
                </div>
            </div>

            <!-- Timestamps -->
            <div class="pt-4 border-t border-gray-200">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Created At</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($tax->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Last Updated</dt>
                        <dd class="mt-1 text-xs text-gray-900">{{ format_datetime($tax->updated_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

