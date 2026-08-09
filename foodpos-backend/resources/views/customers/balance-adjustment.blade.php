@extends('layouts.app')

@section('title', 'Adjust Customer Balance')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('customers.show', $customer) }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
            <i class="fas fa-arrow-left mr-1"></i> Back to customer
        </a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">Adjust balance</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $customer->name }}</p>
    </div>

    <div class="bg-white shadow rounded-lg p-6 space-y-6">
        <div class="rounded-lg bg-gray-50 border border-gray-200 p-4 text-sm">
            <p class="text-gray-600">Current balance: <span class="font-semibold text-gray-900">{{ format_currency($customer->balance) }}</span></p>
            <p class="mt-1 text-gray-500">{{ \App\Support\PartyBalance::customerStatusLabel((float) $customer->balance) }}</p>
            <p class="mt-2 text-xs text-gray-500">{{ \App\Support\PartyBalance::customerOpeningHint() }}</p>
        </div>

        <form action="{{ route('customers.balance-adjustment.store', $customer) }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label for="new_balance" class="block text-sm font-medium text-gray-700 mb-2">New balance <span class="text-red-500">*</span></label>
                <input type="number" name="new_balance" id="new_balance" step="0.01" required
                       value="{{ old('new_balance', $customer->balance) }}"
                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('new_balance') border-red-500 @enderror">
                @error('new_balance')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                <textarea name="reason" id="reason" rows="3"
                          class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Why is this balance being corrected?">{{ old('reason') }}</textarea>
            </div>
            <div class="flex justify-end gap-3">
                <a href="{{ route('customers.show', $customer) }}" class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-4 py-2 h-12 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">Save adjustment</button>
            </div>
        </form>
    </div>
</div>
@endsection
