@extends('layouts.app')

@section('title', 'Consumption Report')

@section('content')
@php
    $branchLabel = $selectedBranch?->name
        ?? ($availableBranches->count() > 1 ? 'All branches' : ($availableBranches->first()?->name ?? '—'));
    $exportParams = $exportParams ?? array_filter([
        'branch_id' => optional($selectedBranch)->id,
        'from' => $from,
        'to' => $to,
        'search' => $search !== '' ? $search : null,
        'category_id' => ! empty($categoryId) ? $categoryId : null,
        'menu_item_id' => ! empty($menuItemId) ? $menuItemId : null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<style>
    .consumption-print-only {
        display: none;
    }

    @media print {
        @page {
            size: portrait;
            margin: 10mm;
        }

        body {
            background: #fff !important;
        }

        .consumption-no-print,
        body > .flex.h-screen > .hidden.lg\:flex,
        body > .flex.h-screen > .flex-1.flex.flex-col > div:first-child,
        body > .flex.h-screen .fixed.inset-y-0,
        main > .mb-4,
        [x-show="sidebarOpen"] {
            display: none !important;
        }

        body > .flex.h-screen,
        body > .flex.h-screen > .flex-1,
        main {
            display: block !important;
            height: auto !important;
            overflow: visible !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
        }

        .max-w-7xl {
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        #consumption-report-printable {
            box-shadow: none !important;
            border: none !important;
            border-radius: 0 !important;
            overflow: visible !important;
        }

        #consumption-report-printable .consumption-table-scroll {
            overflow: visible !important;
        }

        #consumption-report-printable table {
            width: 100% !important;
            table-layout: auto !important;
            font-size: 8px !important;
        }

        #consumption-report-printable th,
        #consumption-report-printable td {
            padding: 3px 4px !important;
            color: #111 !important;
            white-space: nowrap !important;
        }

        #consumption-report-printable thead {
            background: #fff !important;
        }

        #consumption-report-printable th {
            border-bottom: 2px solid #111 !important;
            font-size: 7px !important;
        }

        #consumption-report-printable tfoot td {
            border-top: 2px solid #111 !important;
            font-weight: bold !important;
        }

        .consumption-print-only {
            display: block !important;
        }
    }
</style>

<div class="max-w-7xl mx-auto">
    <div class="mb-6 consumption-no-print">
        <a href="{{ route('reports.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block">
            <i class="fas fa-arrow-left mr-1"></i> Back to Reports
        </a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900">Consumption Report</h1>
                <p class="mt-1 text-sm text-gray-500">Inventory used in the selected period — quantities and purchase cost for cashflow planning.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button type="button"
                        onclick="window.print()"
                        class="inline-flex items-center justify-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                    <i class="fas fa-print mr-2 text-gray-600"></i>Print
                </button>
                <a href="{{ route('reports.consumption.excel', $exportParams) }}"
                   class="inline-flex items-center justify-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                    <i class="fas fa-file-excel mr-2 text-green-600"></i>Export Excel
                </a>
                <a href="{{ route('reports.consumption.pdf', $exportParams) }}"
                   class="inline-flex items-center justify-center h-10 px-4 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
                    <i class="fas fa-file-pdf mr-2 text-red-600"></i>Download PDF
                </a>
            </div>
        </div>
    </div>

    <div class="consumption-no-print">
        @include('reports._filters')
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 consumption-no-print">
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Total consumption value</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($summary['total_cost'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">From sales</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['sales_cost'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">From adjustments</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['adjustment_cost'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Items</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($summary['item_count']) }}</p>
        </div>
    </div>

    <div id="consumption-report-printable" class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
        <div class="consumption-print-only" style="padding: 0 0 12px 0;">
            <div style="text-align: center; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 14px;">
                <p style="font-size: 20px; font-weight: bold; margin: 0 0 6px 0; text-transform: uppercase;">{{ $businessName }}</p>
                @if(!empty($businessAddress))
                    <p style="font-size: 11px; color: #333; margin: 0 0 3px 0; line-height: 1.45;">{{ $businessAddress }}</p>
                @endif
                @if(!empty($businessPhone))
                    <p style="font-size: 11px; color: #333; margin: 0; line-height: 1.45;">Tel: {{ $businessPhone }}</p>
                @endif
            </div>
            <table style="width: 100%; margin: 0 0 12px 0; border-collapse: collapse;">
                <tr>
                    <td style="vertical-align: top; width: 58%;">
                        <p style="font-size: 16px; font-weight: bold; margin: 0;">Consumption Report</p>
                        <p style="font-size: 10px; color: #555; margin: 4px 0 0 0;">{{ format_date($from) }} – {{ format_date($to) }}</p>
                    </td>
                    <td style="vertical-align: top; text-align: right; width: 42%;">
                        <p style="font-size: 10px; color: #444; margin: 0;"><strong>Generated:</strong> {{ format_datetime($generatedAt) }}</p>
                        <p style="font-size: 10px; color: #444; margin: 4px 0 0 0;"><strong>Branch:</strong> {{ $branchLabel }}</p>
                    </td>
                </tr>
            </table>
            <table style="width: 100%; margin-bottom: 14px; border-collapse: collapse; font-size: 11px;">
                <tr>
                    <td style="padding: 6px 8px; border: 1px solid #ccc; font-weight: bold;">Total value</td>
                    <td style="padding: 6px 8px; border: 1px solid #ccc; font-weight: bold;">{{ number_format($summary['total_cost'], 2) }}</td>
                    <td style="padding: 6px 8px; border: 1px solid #ccc; font-weight: bold;">Sales / Adjustments</td>
                    <td style="padding: 6px 8px; border: 1px solid #ccc;">{{ number_format($summary['sales_cost'], 2) }} / {{ number_format($summary['adjustment_cost'], 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="px-4 py-3 border-b border-gray-200 consumption-no-print">
            <h2 class="text-lg font-semibold text-gray-900">Consumption detail</h2>
        </div>
        @if($rows->isEmpty())
            <p class="p-6 text-sm text-gray-500">No inventory consumption recorded for this period.</p>
        @else
            <div class="overflow-x-auto consumption-table-scroll">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty used</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Remaining stock</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg unit cost</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total cost</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sales cost</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Adjustment cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($rows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $row['item_type_label'] }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <a href="{{ route('reports.consumption.detail', array_merge($exportParams, [
                                        'itemType' => $row['item_type'],
                                        'itemId' => $row['item_id'],
                                    ])) }}"
                                       class="text-indigo-700 hover:text-indigo-900 hover:underline">
                                        {{ $row['name'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $row['code'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $row['category'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right tabular-nums whitespace-nowrap">
                                    {{ number_format($row['quantity'], 2) }}
                                    @if(!empty($row['quantity_unit']))
                                        <span class="text-gray-500 font-normal">{{ $row['quantity_unit'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right tabular-nums whitespace-nowrap">
                                    {{ number_format($row['remaining_stock'] ?? 0, 2) }}
                                    @if(!empty($row['remaining_stock_unit']))
                                        <span class="text-gray-500 font-normal">{{ $row['remaining_stock_unit'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ number_format($row['avg_unit_cost'], 2) }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">{{ number_format($row['total_cost'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format($row['sales_cost'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format($row['adjustment_cost'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="7" class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">Total</td>
                            <td class="px-4 py-3 text-sm font-bold text-indigo-600 text-right">{{ number_format($summary['total_cost'], 2) }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">{{ number_format($summary['sales_cost'], 2) }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">{{ number_format($summary['adjustment_cost'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
