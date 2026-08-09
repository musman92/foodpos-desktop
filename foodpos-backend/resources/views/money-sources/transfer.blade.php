@extends('layouts.app')

@section('title', 'Transfer Money')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">Transfer Money</h1>
            <p class="mt-1 text-sm text-gray-500">Transfer funds between money sources</p>
        </div>

        <form action="{{ route('money-sources.transfer.process', $moneySource) }}" method="POST" class="p-6 space-y-6">
            @csrf

            <!-- From Money Source (Read-only) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">From Money Source</label>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex items-center">
                        <i class="fas 
                            @if($moneySource->type === 'CASH') fa-money-bill-wave text-green-600
                            @elseif($moneySource->type === 'BANK') fa-university text-blue-600
                            @else fa-mobile-alt text-purple-600
                            @endif mr-3"></i>
                        <div>
                            <div class="font-medium text-gray-900">{{ $moneySource->name }}</div>
                            <div class="text-sm text-gray-500">{{ $moneySource->type }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- To Money Source -->
            <div>
                <label for="to_money_source_id" class="block text-sm font-medium text-gray-700 mb-2">
                    To Money Source <span class="text-red-500">*</span>
                </label>
                <select name="to_money_source_id" 
                        id="to_money_source_id" 
                        required
                        class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('to_money_source_id') border-red-500 @enderror">
                    <option value="">Select destination money source</option>
                    @foreach($otherMoneySources as $other)
                        <option value="{{ $other->id }}" {{ old('to_money_source_id') == $other->id ? 'selected' : '' }}>
                            {{ $other->name }} ({{ $other->type }})
                        </option>
                    @endforeach
                </select>
                @error('to_money_source_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Amount -->
            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                    Amount <span class="text-red-500">*</span>
                </label>
                <input type="number" 
                       name="amount" 
                       id="amount" 
                       step="0.01"
                       min="0.01"
                       required
                       value="{{ old('amount') }}"
                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('amount') border-red-500 @enderror"
                       placeholder="0.00">
                @error('amount')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Branch -->
            <div>
                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Branch <span class="text-red-500">*</span>
                </label>
                <select name="branch_id" 
                        id="branch_id" 
                        required
                        class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('branch_id') border-red-500 @enderror">
                    <option value="">Select branch</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ old('branch_id', auth()->user()->branch_id) == $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
                @error('branch_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Date -->
            <div>
                <label for="date" class="block text-sm font-medium text-gray-700 mb-2">
                    Transfer Date <span class="text-red-500">*</span>
                </label>
                <input type="date" 
                       name="date" 
                       id="date" 
                       required
                       value="{{ old('date', now()->toDateString()) }}"
                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('date') border-red-500 @enderror">
                @error('date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                    Notes (Optional)
                </label>
                <textarea name="notes" 
                          id="notes" 
                          rows="3"
                          class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror"
                          placeholder="Add any additional notes about this transfer">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                <a href="{{ route('money-sources.show', $moneySource) }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Cancel
                </a>
                <button type="submit" 
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                    <i class="fas fa-exchange-alt mr-2"></i>
                    Transfer Money
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
