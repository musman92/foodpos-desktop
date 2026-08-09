@extends('layouts.app')

@section('title', 'Pay Supplier Advance')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Pay supplier advance</h1>
            <p class="mt-1 text-sm text-gray-500">Record prepayment to a supplier (creates supplier credit for future purchases).</p>
        </div>

        <form action="{{ route('supplier-payments.advance.store') }}" method="POST" class="p-6 space-y-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">Supplier <span class="text-red-500">*</span></label>
                    <select name="supplier_id" id="supplier_id" required class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select a supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ (string) old('supplier_id', $selectedSupplierId) === (string) $supplier->id ? 'selected' : '' }}>
                                {{ $supplier->displayLabel() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if(show_branch_ui())
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">Branch</label>
                    <select name="branch_id" id="branch_id" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Company-wide</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ (string) old('branch_id', $selectedBranchId) === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                    <input type="hidden" name="branch_id" value="{{ old('branch_id', $selectedBranchId) }}">
                @endif
                <div>
                    <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">Payment date <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="account_id" class="block text-sm font-medium text-gray-700 mb-2">Account <span class="text-red-500">*</span></label>
                    <select name="account_id" id="account_id" required class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select an account</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ (string) old('account_id') === (string) $account->id ? 'selected' : '' }}>
                                {{ $account->name }} ({{ ucfirst($account->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="money_source_id" class="block text-sm font-medium text-gray-700 mb-2">Payment source <span class="text-red-500">*</span></label>
                    <select name="money_source_id" id="money_source_id" required class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select…</option>
                        @foreach($moneySources as $source)
                            <option value="{{ $source->id }}" {{ (string) old('money_source_id') === (string) $source->id ? 'selected' : '' }}>
                                {{ $source->name }} ({{ $source->type }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Amount <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" id="amount" step="0.01" min="0.01" required value="{{ old('amount') }}" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" id="notes" rows="3" class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('supplier-payments.index') }}" class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-4 py-2 h-12 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">Record advance</button>
            </div>
        </form>
    </div>
</div>
@endsection
