@php
    $isWeekly = $reportMode === 'weekly';
    $title = $isWeekly ? 'Weekly Closing Report' : 'Monthly Closing Report';
    $routeName = $isWeekly ? 'reports.weekly-closing' : 'reports.monthly-closing';
    $pdfRouteName = $isWeekly ? 'reports.weekly-closing.pdf' : 'reports.monthly-closing.pdf';
    $excelRouteName = $isWeekly ? 'reports.weekly-closing.excel' : 'reports.monthly-closing.excel';
    $exportParams = request()->only($isWeekly ? ['branch_id', 'week_of', 'week_count'] : ['branch_id', 'month']);
    $weekdayLabels = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];
@endphp

@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="max-w-[90rem] mx-auto">
    <div class="mb-6">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
        <p class="mt-1 text-sm text-gray-500">
            Purchases, daily sales by payment method, and closing summary
            @if($isWeekly)
                for up to {{ \App\Support\PeriodClosingReport::MAX_WEEKS }} business weeks.
            @else
                for one calendar month.
            @endif
            Business week starts on <strong>{{ $weekdayLabels[$week_starts_on] ?? ucfirst($week_starts_on) }}</strong>.
        </p>
    </div>

    <form method="get" action="{{ route($routeName) }}" class="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            @if($availableBranches->isNotEmpty())
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                    <select name="branch_id" id="branch_id" class="block w-full filter-control md:w-48">
                        @if(show_branch_ui() && $availableBranches->count() > 1)
                            <option value="">All branches</option>
                        @endif
                        @foreach($availableBranches as $b)
                            <option value="{{ $b->id }}" {{ (request('branch_id', optional($selectedBranch)->id ?? null) == $b->id) ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($isWeekly)
                <div>
                    <label for="week_of" class="block text-sm font-medium text-gray-700 mb-1">Week of</label>
                    <input type="date" name="week_of" id="week_of" value="{{ $week_of }}" class="block w-full filter-control md:w-44" required>
                </div>
                <div>
                    <label for="week_count" class="block text-sm font-medium text-gray-700 mb-1">Number of weeks</label>
                    <select name="week_count" id="week_count" class="block w-full filter-control md:w-36">
                        @for($w = 1; $w <= \App\Support\PeriodClosingReport::MAX_WEEKS; $w++)
                            <option value="{{ $w }}" {{ (int) $week_count === $w ? 'selected' : '' }}>{{ $w }} {{ $w === 1 ? 'week' : 'weeks' }}</option>
                        @endfor
                    </select>
                </div>
            @else
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                    <input type="month" name="month" id="month" value="{{ $month }}" class="block w-full filter-control md:w-44" required>
                </div>
            @endif

            <div>
                <button type="submit" name="generate" value="1" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-file-invoice mr-2"></i>Generate Report
                </button>
            </div>
        </div>
    </form>

    @if($showReport && $report)
        <div class="flex justify-end gap-3 mb-4">
            <a href="{{ route($excelRouteName, $exportParams) }}"
               class="inline-flex items-center justify-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                <i class="fas fa-file-excel mr-2 text-green-600"></i>Export Excel
            </a>
            <a href="{{ route($pdfRouteName, $exportParams) }}"
               class="inline-flex items-center justify-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                <i class="fas fa-file-pdf mr-2 text-red-600"></i>Export PDF
            </a>
        </div>

        @include('reports._period-closing-body', ['report' => $report, 'selectedBranch' => $selectedBranch, 'availableBranches' => $availableBranches])

        @if(count($report['periods']) > 1)
            <div class="mt-6 bg-indigo-50 border-2 border-indigo-200 rounded-xl p-5">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-indigo-800 mb-3">Grand total ({{ currency_symbol() }})</h3>
                @include('reports._period-closing-summary', ['closing' => $report['grand_closing']])
            </div>
        @endif

        <p class="mt-4 text-xs text-gray-500">
            Stock in hand uses current inventory value at report time, not a historical snapshot.
            Purchases are item totals; expenses include recorded expenses and manual outgoing transactions.
        </p>
    @endif
</div>
@endsection
