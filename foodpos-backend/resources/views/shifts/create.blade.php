@extends('layouts.app')

@section('title', 'Start New Shift')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Start New Shift</h1>
        <p class="text-gray-600 mt-1">Each cashier starts their own shift at this branch. Multiple desks can run at the same time.</p>
    </div>

    <form action="{{ route('shifts.store') }}" method="POST" class="bg-white rounded-lg shadow p-6 space-y-6">
        @csrf

        <!-- Branch Selection -->
        @if(show_branch_ui())
        <div>
            <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                Branch <span class="text-red-500">*</span>
            </label>
            <select name="branch_id" id="branch_id" required
                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                onchange="loadMoneySources(this.value)"
                value="{{ old('branch_id', $selectedBranchId) }}">
                <option value="">Select Branch</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ old('branch_id', $selectedBranchId) == $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
            @error('branch_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        @else
            <input type="hidden" name="branch_id" id="branch_id" value="{{ old('branch_id', $selectedBranchId) }}">
        @endif

        <!-- Shift Date -->
        <div>
            <label for="shift_date" class="block text-sm font-medium text-gray-700 mb-2">
                Shift Date <span class="text-red-500">*</span>
            </label>
            <input type="date" name="shift_date" id="shift_date" required
                value="{{ old('shift_date', date('Y-m-d')) }}"
                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            @error('shift_date')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Money Sources -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Opening Balances <span class="text-red-500">*</span>
            </label>
            <div id="money-sources-container" class="space-y-4">
                @if($moneySources->count() > 0)
                    @foreach($moneySources as $moneySource)
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div>
                                <label class="text-sm font-medium text-gray-900">{{ $moneySource->name }}</label>
                                <p class="text-xs text-gray-500">{{ $moneySource->type }}</p>
                            </div>
                            <div class="w-48">
                                <input type="number" 
                                    name="money_sources[{{ $moneySource->id }}]" 
                                    step="0.01" 
                                    min="0" 
                                    value="{{ old("money_sources.{$moneySource->id}", 0) }}"
                                    required
                                    class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="0.00">
                            </div>
                        </div>
                    @endforeach
                @elseif($hasBranchSelected)
                    <div class="text-center py-8 border-2 border-dashed border-yellow-300 rounded-lg bg-yellow-50">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl mb-2"></i>
                        <p class="text-yellow-800 font-medium mb-1">No money sources found</p>
                        <p class="text-sm text-yellow-700 mb-4">This branch doesn't have any active money sources assigned.</p>
                        <a href="{{ route('money-sources.index') }}" class="text-sm text-yellow-800 hover:text-yellow-900 underline">
                            Manage Money Sources
                        </a>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-info-circle text-2xl mb-2"></i>
                        <p>Please select a branch to load money sources</p>
                    </div>
                @endif
            </div>
            @error('money_sources')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Notes -->
        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                Opening Notes (Optional)
            </label>
            <textarea name="notes" id="notes" rows="3"
                class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="Any notes about this shift...">{{ old('notes') }}</textarea>
            @error('notes')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end space-x-4 pt-4 border-t">
            <a href="{{ route('shifts.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                Start Shift
            </button>
        </div>
    </form>
</div>

<script>
function loadMoneySources(branchId) {
    if (!branchId) {
        document.getElementById('money-sources-container').innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-info-circle text-2xl mb-2"></i>
                <p>Please select a branch to load money sources</p>
            </div>
        `;
        return;
    }

    // Reload page with branch_id parameter
    window.location.href = '{{ route("shifts.create") }}?branch_id=' + branchId;
}
</script>
@endsection

