@extends('layouts.app')

@section('title', 'Z Report')

@section('content')
@php
    $shift = $report['shift'];
    $title = $report['is_interim'] ? 'Interim Z Report' : 'Z Report';
@endphp
<div class="max-w-5xl mx-auto space-y-6 z-report-page">
    <div class="flex items-center justify-between no-print">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $title }}</h1>
            <p class="text-gray-600 mt-1">Shift #{{ $shift->id }} — {{ $shift->branch->name }}</p>
        </div>
        <div class="flex space-x-3">
            <button type="button" onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-print mr-2"></i> Print
            </button>
            <a href="{{ route('shifts.z-report.pdf', $shift) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                <i class="fas fa-file-pdf mr-2"></i> Download PDF
            </a>
            <a href="{{ route('reports.z-report') }}" class="bg-gray-100 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-arrow-left mr-2"></i> Back to Reports
            </a>
            <a href="{{ route('shifts.show', $shift) }}" class="bg-gray-100 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-clock mr-2"></i> Shift Details
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 print-shadow-none">
        <div class="text-center border-b border-gray-200 pb-4 mb-6">
            <h2 class="text-xl font-bold text-gray-900 uppercase">{{ $shift->company?->name ?? config('app.name') }}</h2>
            <p class="text-lg font-semibold text-gray-800 mt-2">{{ $title }}</p>
        </div>

        @include('shifts._z-report-body', ['report' => $report, 'isPdf' => false])
    </div>
</div>

<style>
    @media print {
        .no-print, nav, aside, header, footer, .sidebar { display: none !important; }
        .z-report-page { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
        .print-shadow-none { box-shadow: none !important; border: none !important; padding: 0 !important; }
        body { background: #fff !important; }
    }

    .z-report-body .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: #111827;
        margin: 1.5rem 0 0.75rem;
        padding-bottom: 0.25rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .z-report-body .report-meta p {
        margin: 0.25rem 0;
        font-size: 0.875rem;
        color: #374151;
    }

    .z-report-body .interim-note {
        color: #b45309;
        margin-top: 0.5rem;
    }

    .z-report-body .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .z-report-body .data-table th,
    .z-report-body .data-table td {
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
    }

    .z-report-body .data-table th {
        background: #f9fafb;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #6b7280;
    }

    .z-report-body .data-table .amount {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .z-report-body .data-table tr.highlight td {
        background: #f3f4f6;
    }

    .z-report-body .positive { color: #059669; }
    .z-report-body .negative { color: #dc2626; }
    .z-report-body .muted { color: #6b7280; font-size: 0.75rem; }
</style>
@endsection
