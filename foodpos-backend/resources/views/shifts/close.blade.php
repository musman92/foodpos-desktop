@extends('layouts.app')

@section('title', 'Close Shift')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Close Shift</h1>
        <p class="text-gray-600 mt-1">Enter closing balances for all money sources</p>
    </div>

    <!-- Shift Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-600">Branch</p>
                <p class="font-medium text-gray-900">{{ $shift->branch->name }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Opened At</p>
                <p class="font-medium text-gray-900">{{ $shift->opened_at->format('M d, Y H:i') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Opened By</p>
                <p class="font-medium text-gray-900">{{ $shift->openedBy->name }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Shift Date</p>
                <p class="font-medium text-gray-900">{{ $shift->shift_date->format('M d, Y') }}</p>
            </div>
        </div>
    </div>

    <form action="{{ route('shifts.update', $shift) }}" method="POST" class="bg-white rounded-lg shadow p-6 space-y-6">
        @csrf
        @method('PUT')

        <!-- Closing Date and Time -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="closing_date" class="block text-sm font-medium text-gray-700 mb-2">
                    Closing Date <span class="text-red-500">*</span>
                </label>
                <input type="date" name="closing_date" id="closing_date" required
                    value="{{ old('closing_date', date('Y-m-d')) }}"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                @error('closing_date')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="closing_time" class="block text-sm font-medium text-gray-700 mb-2">
                    Closing Time <span class="text-red-500">*</span>
                </label>
                <input type="time" name="closing_time" id="closing_time" required
                    value="{{ old('closing_time', date('H:i')) }}"
                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                @error('closing_time')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Money Sources Closing Balances -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Closing Balances <span class="text-red-500">*</span>
            </label>
            <div class="space-y-4">
                @foreach($shift->moneySources as $moneySource)
                    @php
                        $openingBalance = $moneySource->pivot->opening_balance ?? 0;
                        $expectedBalance = $expectedBalances[$moneySource->id] ?? $openingBalance;
                    @endphp
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <label class="text-sm font-medium text-gray-900">{{ $moneySource->name }}</label>
                                <p class="text-xs text-gray-500">{{ $moneySource->type }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mt-3">
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Opening Balance</p>
                                <p class="text-sm font-medium text-gray-900">{{ number_format($openingBalance, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Expected Balance</p>
                                <p class="text-sm font-medium text-blue-600">{{ number_format($expectedBalance, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Closing Balance</p>
                                <input type="number" 
                                    name="money_sources[{{ $moneySource->id }}]" 
                                    step="0.01" 
                                    min="0" 
                                    value="{{ old("money_sources.{$moneySource->id}", $expectedBalance) }}"
                                    data-expected="{{ $expectedBalance }}"
                                    required
                                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    onchange="calculateDifference(this)"
                                    oninput="calculateDifference(this)">
                            </div>
                        </div>
                        <div class="mt-2">
                            <p class="text-xs text-gray-500">Difference: <span id="diff-{{ $moneySource->id }}" class="font-medium">0.00</span></p>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('money_sources')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Notes -->
        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                Closing Notes (Optional)
            </label>
            <textarea name="notes" id="notes" rows="3"
                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Any notes about closing this shift...">{{ old('notes', $shift->opening_notes) }}</textarea>
            @error('notes')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-4 pt-4 border-t">
            <a href="{{ route('shifts.show', $shift) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                Close Shift
            </button>
        </div>
    </form>
</div>

<script>
function calculateDifference(input) {
    const expectedBalance = parseFloat(input.getAttribute('data-expected')) || 0;
    const closingBalance = parseFloat(input.value);
    const closing = Number.isFinite(closingBalance) ? closingBalance : 0;
    const difference = closing - expectedBalance;
    const match = input.name.match(/\[(\d+)\]/);
    const id = match ? match[1] : null;
    const diffElement = id ? document.getElementById('diff-' + id) : null;
    if (! diffElement) {
        return;
    }
    diffElement.textContent = difference.toFixed(2);
    diffElement.className = difference >= 0 ? 'font-medium text-green-600' : 'font-medium text-red-600';
}
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[name^="money_sources["]').forEach(function (input) {
        calculateDifference(input);
    });
});
</script>
@endsection

