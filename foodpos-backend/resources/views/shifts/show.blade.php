@extends('layouts.app')

@section('title', 'Shift Details')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Shift Details</h1>
            <p class="text-gray-600 mt-1">View shift information and balances</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('shifts.z-report', $shift) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-file-invoice mr-2"></i> Z Report
            </a>
            @if($shift->status === 'active')
                <a href="{{ route('shifts.edit', $shift) }}" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-times-circle mr-2"></i> Close Shift
                </a>
            @endif
            <a href="{{ route('shifts.index') }}" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>
    </div>

    <!-- Shift Status -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Shift Information</h2>
            @if($shift->status === 'active')
                <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                    Active
                </span>
            @else
                <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800">
                    Closed
                </span>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-sm text-gray-600">Branch</p>
                <p class="font-medium text-gray-900">{{ $shift->branch->name }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Shift Date</p>
                <p class="font-medium text-gray-900">{{ $shift->shift_date->format('M d, Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Opened At</p>
                <p class="font-medium text-gray-900">{{ $shift->opened_at->format('M d, Y H:i') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Opened By</p>
                <p class="font-medium text-gray-900">{{ $shift->openedBy->name }}</p>
            </div>
            @if($shift->status === 'closed')
                <div>
                    <p class="text-sm text-gray-600">Closed At</p>
                    <p class="font-medium text-gray-900">{{ $shift->closed_at->format('M d, Y H:i') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Closed By</p>
                    <p class="font-medium text-gray-900">{{ $shift->closedBy->name }}</p>
                </div>
            @endif
        </div>

        @if($shift->opening_notes)
            <div class="mt-4 pt-4 border-t">
                <p class="text-sm text-gray-600 mb-1">Opening Notes</p>
                <p class="text-gray-900">{{ $shift->opening_notes }}</p>
            </div>
        @endif

        @if($shift->closing_notes)
            <div class="mt-4 pt-4 border-t">
                <p class="text-sm text-gray-600 mb-1">Closing Notes</p>
                <p class="text-gray-900">{{ $shift->closing_notes }}</p>
            </div>
        @endif
    </div>

    <!-- Money Sources -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Money Sources</h2>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Money Source</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Opening Balance</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Expected Balance</th>
                        @if($shift->status === 'closed')
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Closing Balance</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Difference</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($shift->moneySources as $moneySource)
                        @php
                            $openingBalance = $moneySource->pivot->opening_balance ?? 0;
                            $expectedBalance = $shift->status === 'active' 
                                ? ($expectedBalances[$moneySource->id] ?? $openingBalance)
                                : ($moneySource->pivot->expected_balance ?? $openingBalance);
                            $closingRaw = $moneySource->pivot->closing_balance ?? null;
                            $closingBalance = $closingRaw !== null && $closingRaw !== '' ? (float) $closingRaw : null;
                            $difference = (float) ($moneySource->pivot->difference ?? 0);
                        @endphp
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $moneySource->name }}</div>
                                <div class="text-xs text-gray-500">{{ $moneySource->type }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                {{ number_format($openingBalance, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-blue-600 font-medium">
                                {{ number_format($expectedBalance, 2) }}
                            </td>
                            @if($shift->status === 'closed')
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                    {{ $closingBalance !== null ? number_format($closingBalance, 2) : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium {{ $difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($difference, 2) }}
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Cash Summary (if closed) -->
    @if($shift->status === 'closed')
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Cash Summary</h2>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Expected Cash</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($shift->expected_cash, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Actual Cash</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($shift->actual_cash, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Difference</p>
                    <p class="text-2xl font-bold {{ $shift->cash_difference >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format($shift->cash_difference, 2) }}
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

