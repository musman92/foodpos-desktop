@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $todayBreakdown = ($todayStats ?? [])['net_profit_breakdown'] ?? null;
    $periodBreakdown = ($periodStats ?? [])['net_profit_breakdown'] ?? null;
    $hasNetProfitBreakdown = $todayBreakdown || $periodBreakdown;
@endphp
<div class="space-y-6" id="dashboard-content"
     @if($hasNetProfitBreakdown)
     x-data="dashboardNetProfitModal()"
     @keydown.escape.window="closeNetProfitBreakdown()"
     @endif
>
    <!-- Shift reminder -->
    @if($showShiftReminder ?? false)
        <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-lg">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-clock text-amber-500 text-xl"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-amber-900">Start your shift</h3>
                    <p class="mt-1 text-sm text-amber-800">Each cashier works on their own shift. Start yours before taking orders or payments.</p>
                    <div class="mt-4">
                        <a href="{{ route('shifts.create', ['branch_id' => $shiftReminderBranchId]) }}"
                           class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition inline-block">
                            Start my shift
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($selectedBranch)
        @php
            $todayLabel = local_now($selectedBranch->id)->format('j F');
            $canDash = fn (string $permission) => auth()->user()->hasAppPermission($permission);
        @endphp

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ $selectedBranch->name }}</p>
                </div>
                <div class="flex flex-col sm:flex-row flex-wrap gap-3 lg:justify-end">
                    @if(show_branch_ui() && $availableBranches->count() > 1)
                        <div class="min-w-[12rem]">
                            <label for="branch_id" class="block text-xs font-medium text-gray-500 mb-1">Branch</label>
                            <select name="branch_id" id="branch_id" class="filter-control w-full">
                                @foreach($availableBranches as $branch)
                                    <option value="{{ $branch->id }}" @selected($selectedBranch->id === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="branch_id" value="{{ $selectedBranch->id }}">
                    @endif
                    <div>
                        <label for="start_date" class="block text-xs font-medium text-gray-500 mb-1">Start date</label>
                        <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" class="filter-control w-full sm:w-auto">
                    </div>
                    <div>
                        <label for="end_date" class="block text-xs font-medium text-gray-500 mb-1">End date</label>
                        <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" class="filter-control w-full sm:w-auto">
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center h-11 px-4 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                        <i class="fas fa-search mr-2"></i>
                        Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Today -->
        @if($canDash('dashboard.today-stats') && isset($todayStats))
        <div>
            <div class="flex items-center gap-2 mb-3 text-sm font-medium text-gray-600">
                <i class="fas fa-calendar-day text-indigo-500"></i>
                <span>Today, {{ $todayLabel }}</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                @include('dashboard._stat-card', ['label' => 'Revenue', 'value' => format_currency($todayStats['revenue'] ?? 0), 'icon' => 'fa-sun', 'tone' => 'indigo'])
                @include('dashboard._stat-card', ['label' => 'Cost of Goods', 'value' => format_currency($todayStats['cost_of_goods'] ?? 0), 'icon' => 'fa-boxes-stacked', 'tone' => 'orange'])
                @include('dashboard._stat-card', [
                    'label' => 'Net Profit',
                    'value' => format_currency($todayStats['net_profit'] ?? 0),
                    'icon' => 'fa-arrow-trend-up',
                    'tone' => 'emerald',
                    'clickable' => (bool) $todayBreakdown,
                    'clickAction' => $todayBreakdown ? "openNetProfitBreakdown('today')" : null,
                    'ariaControls' => 'net-profit-breakdown-modal',
                ])
                @include('dashboard._stat-card', ['label' => 'Transactions', 'value' => number_format($todayStats['transactions'] ?? 0), 'icon' => 'fa-wave-square', 'tone' => 'sky'])
                @include('dashboard._stat-card', ['label' => 'Customers', 'value' => number_format($todayStats['customers'] ?? 0), 'icon' => 'fa-users', 'tone' => 'violet'])
                @include('dashboard._stat-card', ['label' => 'Average Receipt', 'value' => format_currency($todayStats['average_receipt'] ?? 0), 'icon' => 'fa-right-left', 'tone' => 'amber'])
            </div>
        </div>
        @endif

        <!-- Period summary -->
        @if($canDash('dashboard.period-stats') && isset($periodStats))
        <div>
            <div class="flex items-center gap-2 mb-3 text-sm font-medium text-gray-600">
                <i class="fas fa-calendar text-indigo-500"></i>
                <span>{{ $periodStats['label'] ?? ($startDate.' – '.$endDate) }}</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                @include('dashboard._stat-card', ['label' => 'Revenue', 'value' => format_currency($periodStats['revenue'] ?? 0), 'icon' => 'fa-sun', 'tone' => 'indigo', 'highlight' => true])
                @include('dashboard._stat-card', ['label' => 'Cost of Goods', 'value' => format_currency($periodStats['cost_of_goods'] ?? 0), 'icon' => 'fa-boxes-stacked', 'tone' => 'orange', 'highlight' => true])
                @include('dashboard._stat-card', [
                    'label' => 'Net Profit',
                    'value' => format_currency($periodStats['net_profit'] ?? 0),
                    'icon' => 'fa-arrow-trend-up',
                    'tone' => 'emerald',
                    'highlight' => true,
                    'clickable' => (bool) $periodBreakdown,
                    'clickAction' => $periodBreakdown ? "openNetProfitBreakdown('period')" : null,
                    'ariaControls' => 'net-profit-breakdown-modal',
                ])
                @include('dashboard._stat-card', ['label' => 'Transactions', 'value' => number_format($periodStats['transactions'] ?? 0), 'icon' => 'fa-wave-square', 'tone' => 'sky', 'highlight' => true])
                @include('dashboard._stat-card', ['label' => 'Customers', 'value' => number_format($periodStats['customers'] ?? 0), 'icon' => 'fa-users', 'tone' => 'violet', 'highlight' => true])
                @include('dashboard._stat-card', ['label' => 'Average Receipt', 'value' => format_currency($periodStats['average_receipt'] ?? 0), 'icon' => 'fa-right-left', 'tone' => 'amber', 'highlight' => true])
            </div>
        </div>
        @endif

        <!-- Revenue chart (selected period) -->
        @if($canDash('dashboard.revenue-chart') && $revenueChartDaily)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5" id="dashboard-revenue-panel">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Revenue</h2>
                <div class="inline-flex rounded-lg border border-gray-200 p-1 bg-gray-50" id="dashboard-revenue-granularity">
                    <button type="button" data-granularity="day" class="revenue-granularity-btn px-3 py-1.5 text-sm font-medium rounded-md bg-indigo-600 text-white">Day</button>
                    <button type="button" data-granularity="week" class="revenue-granularity-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600 hover:text-gray-900">Week</button>
                    <button type="button" data-granularity="month" class="revenue-granularity-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600 hover:text-gray-900">Month</button>
                </div>
            </div>
            <div class="h-72">
                <canvas id="dashboardRevenueChart"></canvas>
            </div>
        </div>
        @endif

        <!-- Expenses chart (selected period) -->
        @if($canDash('dashboard.expenses-chart') && $expensesChartDaily)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5" id="dashboard-expenses-panel">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Expenses</h2>
                <div class="inline-flex rounded-lg border border-gray-200 p-1 bg-gray-50" id="dashboard-expenses-granularity">
                    <button type="button" data-granularity="day" class="expenses-granularity-btn px-3 py-1.5 text-sm font-medium rounded-md bg-orange-600 text-white">Day</button>
                    <button type="button" data-granularity="week" class="expenses-granularity-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600 hover:text-gray-900">Week</button>
                    <button type="button" data-granularity="month" class="expenses-granularity-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600 hover:text-gray-900">Month</button>
                </div>
            </div>
            <div class="h-72">
                <canvas id="dashboardExpensesChart"></canvas>
            </div>
        </div>
        @endif

        <!-- Operational comparison (full width) -->
        @if($canDash('dashboard.operational-comparison') && isset($operationalComparison))
            @include('dashboard._operational-comparison', [
                'operationalComparison' => $operationalComparison,
                'periodStats' => $periodStats,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ])
        @endif

        <!-- Orders by type & funds overview -->
        @php
            $hasOrderTypeChart = $canDash('dashboard.order-types') && isset($orderTypeBreakdown);
            $hasFundsOverview = $canDash('dashboard.funds-overview') && isset($moneySourceBalances) && count($moneySourceBalances) > 0;
        @endphp
        @if($hasOrderTypeChart || $hasFundsOverview)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if($hasOrderTypeChart)
            <div class="{{ $hasFundsOverview ? 'lg:col-span-1' : 'lg:col-span-3' }} bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-900">Orders by Type</h2>
                        <p class="text-sm text-gray-500 mt-1">{{ $periodStats['label'] ?? ($startDate.' – '.$endDate) }}</p>
                    </div>
                    <div class="shrink-0 rounded-lg bg-indigo-50 border border-indigo-100 px-3 py-2 text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-600">Total orders</p>
                        <p class="text-xl font-bold tabular-nums text-indigo-900">{{ number_format($orderTypeBreakdown['total'] ?? 0) }}</p>
                    </div>
                </div>
                @if(($orderTypeBreakdown['total'] ?? 0) > 0)
                    <div class="h-56 sm:h-64">
                        <canvas id="dashboardOrderTypeChart"></canvas>
                    </div>
                @else
                    <p class="text-gray-500 text-center py-12 text-sm">No orders for this period.</p>
                @endif
            </div>
            @endif

            @if($hasFundsOverview)
            <div class="{{ $hasOrderTypeChart ? 'lg:col-span-2' : 'lg:col-span-3' }}">
                @include('dashboard._money-sources', [
                    'moneySourceBalances' => $moneySourceBalances,
                    'compact' => true,
                ])
            </div>
            @endif
        </div>
        @endif

        <!-- Customer receivables & supplier payables -->
        @if(($canDash('dashboard.receivables') && isset($customerReceivables)) || ($canDash('dashboard.payables') && isset($supplierPayables)))
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @if($canDash('dashboard.receivables') && isset($customerReceivables))
                @include('dashboard._outstanding-table', [
                    'type' => 'receivable',
                    'report' => $customerReceivables,
                    'selectedBranch' => $selectedBranch,
                ])
            @endif
            @if($canDash('dashboard.payables') && isset($supplierPayables))
                @include('dashboard._outstanding-table', [
                    'type' => 'payable',
                    'report' => $supplierPayables,
                    'selectedBranch' => $selectedBranch,
                ])
            @endif
        </div>
        @endif

        <!-- Top food items & low stock -->
        @if(($canDash('dashboard.top-items') && isset($topFoodItems)) || ($canDash('dashboard.low-stock') && isset($lowStockItems)))
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @if($canDash('dashboard.top-items') && isset($topFoodItems))
                @include('dashboard._top-food-items', [
                    'topFoodItems' => $topFoodItems,
                    'selectedBranch' => $selectedBranch,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ])
            @endif
            @if($canDash('dashboard.low-stock') && isset($lowStockItems))
                @include('dashboard._low-stock', [
                    'lowStockItems' => $lowStockItems,
                    'selectedBranch' => $selectedBranch,
                ])
            @endif
        </div>
        @endif
    @else
        <div class="bg-white rounded-lg shadow-lg p-12 text-center">
            <i class="fas fa-exclamation-triangle text-gray-300 text-6xl mb-4"></i>
            <p class="text-gray-500 text-lg">No branch selected or available.</p>
            <p class="text-gray-400 text-sm mt-2">Please contact your administrator to assign a branch.</p>
        </div>
    @endif

    @if($hasNetProfitBreakdown)
    <div x-show="open"
         x-cloak
         id="net-profit-breakdown-modal"
         class="fixed inset-0 z-50 flex justify-center"
         role="dialog"
         aria-modal="true"
         aria-labelledby="net-profit-breakdown-title"
         @click.self="closeNetProfitBreakdown()">
        <div class="absolute inset-0 bg-gray-900/50" @click="closeNetProfitBreakdown()"></div>
        <div class="relative h-full w-full md:w-[70%] bg-white shadow-2xl flex flex-col"
             @click.stop>
            <div class="flex items-start justify-between gap-3 px-4 sm:px-6 py-4 border-b border-gray-200 shrink-0">
                <div>
                    <h3 id="net-profit-breakdown-title" class="text-lg font-semibold text-gray-900">Net Profit breakdown</h3>
                    <p class="mt-0.5 text-sm text-gray-500" x-text="periodLabel"></p>
                </div>
                <button type="button"
                        class="h-9 w-9 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                        @click="closeNetProfitBreakdown()"
                        aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-5">
                <div x-show="activeKey === 'today'" x-cloak>
                    @include('dashboard._net-profit-breakdown-body', ['breakdown' => $todayBreakdown])
                </div>
                <div x-show="activeKey === 'period'" x-cloak>
                    @include('dashboard._net-profit-breakdown-body', ['breakdown' => $periodBreakdown])
                </div>
            </div>
        </div>
    </div>
    @endif

</div>

@if($hasNetProfitBreakdown)
<script>
function dashboardNetProfitModal() {
    return {
        open: false,
        activeKey: 'today',
        labels: {
            today: @js($todayBreakdown ? ('Today · ' . ($todayStats['label'] ?? '')) : ''),
            period: @js($periodBreakdown ? ($periodStats['label'] ?? '') : ''),
        },
        get periodLabel() {
            return this.labels[this.activeKey] || '';
        },
        openNetProfitBreakdown(key) {
            this.activeKey = key;
            this.open = true;
            document.documentElement.classList.add('overflow-hidden');
        },
        closeNetProfitBreakdown() {
            this.open = false;
            document.documentElement.classList.remove('overflow-hidden');
        },
    };
}
</script>
@endif

@if($selectedBranch && ((isset($revenueChartDaily) && $revenueChartDaily) || (isset($expensesChartDaily) && $expensesChartDaily) || (isset($orderTypeBreakdown) && ($orderTypeBreakdown['total'] ?? 0) > 0) || isset($operationalComparison)))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endif

@if((isset($revenueChartDaily) && $revenueChartDaily) || (isset($expensesChartDaily) && $expensesChartDaily) || (isset($orderTypeBreakdown) && ($orderTypeBreakdown['total'] ?? 0) > 0) || isset($operationalComparison))
@php $config = get_company_config(); @endphp
<script>
document.addEventListener('DOMContentLoaded', function() {
    var currencySymbol = @js(get_currency_symbol($config['currency'] ?? 'USD'));

    function aggregateDailySeries(dailyData, valueKey, granularity) {
        var dates = dailyData.dates || [];
        var values = dailyData[valueKey] || [];
        if (granularity === 'day') {
            return {
                labels: dailyData.labels || [],
                values: values,
            };
        }
        var buckets = {};
        dates.forEach(function(dateStr, index) {
            var d = new Date(dateStr + 'T12:00:00');
            var key;
            var label;
            if (granularity === 'week') {
                var weekStart = new Date(d);
                var day = (weekStart.getDay() + 6) % 7;
                weekStart.setDate(weekStart.getDate() - day);
                key = weekStart.toISOString().slice(0, 10);
                label = 'W/C ' + weekStart.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
            } else {
                key = dateStr.slice(0, 7);
                label = d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
            }
            if (!buckets[key]) {
                buckets[key] = { label: label, total: 0 };
            }
            buckets[key].total += Number(values[index] || 0);
        });
        var keys = Object.keys(buckets).sort();
        return {
            labels: keys.map(function(k) { return buckets[k].label; }),
            values: keys.map(function(k) { return Math.round(buckets[k].total * 100) / 100; }),
        };
    }

    function initPeriodChart(config) {
        var granularity = 'day';
        var chart = null;

        function setGranularity(mode) {
            granularity = mode;
            document.querySelectorAll(config.btnSelector).forEach(function(btn) {
                var active = btn.getAttribute('data-granularity') === mode;
                btn.classList.toggle(config.activeClass, active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('text-gray-600', !active);
                btn.classList.toggle('hover:text-gray-900', !active);
            });
            renderChart();
        }

        function formatChartAmount(value) {
            return currencySymbol + Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        function renderChart() {
            var canvas = document.getElementById(config.canvasId);
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }
            var series = aggregateDailySeries(config.dailyData, config.valueKey, granularity);
            if (chart) {
                chart.destroy();
            }
            chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: series.labels,
                    datasets: [{
                        label: config.label,
                        data: series.values,
                        borderColor: config.borderColor,
                        backgroundColor: config.backgroundColor,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        pointHitRadius: 14,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(17, 24, 39, 0.92)',
                            titleColor: '#f9fafb',
                            bodyColor: '#f9fafb',
                            borderColor: 'rgba(255, 255, 255, 0.08)',
                            borderWidth: 1,
                            titleFont: { size: 13, weight: '600' },
                            bodyFont: { size: 12, weight: '500' },
                            padding: 12,
                            cornerRadius: 10,
                            displayColors: false,
                            callbacks: {
                                title: function(items) {
                                    return items.length ? items[0].label : '';
                                },
                                label: function(ctx) {
                                    var value = ctx.parsed && ctx.parsed.y != null ? ctx.parsed.y : ctx.raw;
                                    return config.label + ': ' + formatChartAmount(value);
                                },
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return currencySymbol + Number(value).toLocaleString();
                                },
                            },
                        },
                    },
                },
            });
        }

        document.querySelectorAll(config.btnSelector).forEach(function(btn) {
            btn.addEventListener('click', function() {
                setGranularity(btn.getAttribute('data-granularity'));
            });
        });

        renderChart();
    }

    @if(isset($revenueChartDaily) && $revenueChartDaily)
    initPeriodChart({
        canvasId: 'dashboardRevenueChart',
        dailyData: @js($revenueChartDaily),
        valueKey: 'revenue',
        label: 'Revenue',
        btnSelector: '.revenue-granularity-btn',
        activeClass: 'bg-indigo-600',
        borderColor: 'rgb(99, 102, 241)',
        backgroundColor: 'rgba(99, 102, 241, 0.18)',
    });
    @endif

    @if(isset($expensesChartDaily) && $expensesChartDaily)
    initPeriodChart({
        canvasId: 'dashboardExpensesChart',
        dailyData: @js($expensesChartDaily),
        valueKey: 'expenses',
        label: 'Expenses',
        btnSelector: '.expenses-granularity-btn',
        activeClass: 'bg-orange-600',
        borderColor: 'rgb(249, 115, 22)',
        backgroundColor: 'rgba(249, 115, 22, 0.18)',
    });
    @endif

    @if(isset($orderTypeBreakdown) && ($orderTypeBreakdown['total'] ?? 0) > 0)
    var orderTypeData = @js($orderTypeBreakdown);
    var orderTypeCanvas = document.getElementById('dashboardOrderTypeChart');
    if (orderTypeCanvas && typeof Chart !== 'undefined') {
        new Chart(orderTypeCanvas, {
            type: 'pie',
            data: {
                labels: orderTypeData.labels,
                datasets: [{
                    data: orderTypeData.counts,
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.85)',
                        'rgba(16, 185, 129, 0.85)',
                        'rgba(249, 115, 22, 0.85)',
                    ],
                    borderColor: [
                        'rgb(99, 102, 241)',
                        'rgb(16, 185, 129)',
                        'rgb(249, 115, 22)',
                    ],
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { top: 4, bottom: 4 },
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        align: 'center',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 11, weight: '500' },
                            color: '#4b5563',
                            generateLabels: function(chart) {
                                var dataset = chart.data.datasets[0];
                                var total = orderTypeData.total || 0;
                                return chart.data.labels.map(function(label, index) {
                                    var value = Number(dataset.data[index] || 0);
                                    var pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                    var meta = chart.getDatasetMeta(0);
                                    var style = meta.controller.getStyle(index);
                                    return {
                                        text: label + ' · ' + value + ' (' + pct + '%)',
                                        fillStyle: style.backgroundColor,
                                        strokeStyle: style.borderColor,
                                        lineWidth: style.borderWidth,
                                        hidden: !chart.getDataVisibility(index),
                                        index: index,
                                    };
                                });
                            },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var total = orderTypeData.total || 0;
                                var value = Number(ctx.raw || 0);
                                var pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                return ctx.label + ': ' + value + ' (' + pct + '%)';
                            },
                        },
                    },
                    orderTypeSliceLabels: true,
                },
            },
            plugins: [{
                id: 'orderTypeSliceLabels',
                afterDatasetsDraw: function(chart) {
                    if (!chart.options.plugins.orderTypeSliceLabels) {
                        return;
                    }
                    var ctx = chart.ctx;
                    var dataset = chart.data.datasets[0];
                    var total = orderTypeData.total || 0;
                    chart.getDatasetMeta(0).data.forEach(function(arc, index) {
                        var value = Number(dataset.data[index] || 0);
                        if (!value) {
                            return;
                        }
                        var pos = arc.tooltipPosition();
                        ctx.save();
                        ctx.fillStyle = '#fff';
                        ctx.font = 'bold 13px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(String(value), pos.x, pos.y);
                        ctx.restore();
                    });
                },
            }],
        });
    }
    @endif

    @if(isset($operationalComparison))
    var operationalData = @js($operationalComparison);
    var operationalCanvas = document.getElementById('dashboardOperationalChart');
    if (operationalCanvas && typeof Chart !== 'undefined') {
        var operationalPalette = [
            { start: 'rgba(14, 165, 233, 0.95)', end: 'rgba(56, 189, 248, 0.55)' },
            { start: 'rgba(79, 70, 229, 0.95)', end: 'rgba(129, 140, 248, 0.55)' },
            { start: 'rgba(245, 158, 11, 0.95)', end: 'rgba(251, 191, 36, 0.55)' },
            { start: 'rgba(244, 63, 94, 0.95)', end: 'rgba(251, 113, 133, 0.55)' },
            { start: 'rgba(16, 185, 129, 0.95)', end: 'rgba(52, 211, 153, 0.55)' },
        ];

        function operationalBarGradient(context) {
            var chart = context.chart;
            var index = context.dataIndex;
            var palette = operationalPalette[index % operationalPalette.length];
            var area = chart.chartArea;
            if (!area) {
                return palette.start;
            }
            var gradient = chart.ctx.createLinearGradient(area.left, 0, area.right, 0);
            gradient.addColorStop(0, palette.end);
            gradient.addColorStop(1, palette.start);
            return gradient;
        }

        function formatOperationalAmount(value) {
            return currencySymbol + Number(value).toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            });
        }

        new Chart(operationalCanvas, {
            type: 'bar',
            data: {
                labels: operationalData.labels,
                datasets: [{
                    data: operationalData.values,
                    backgroundColor: operationalBarGradient,
                    borderRadius: 10,
                    borderSkipped: false,
                    barThickness: 22,
                    maxBarThickness: 26,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { right: 12 },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.92)',
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(ctx) {
                                return formatOperationalAmount(ctx.raw);
                            },
                        },
                    },
                    operationalValueLabels: true,
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: {
                            color: 'rgba(229, 231, 235, 0.9)',
                            drawTicks: false,
                        },
                        ticks: {
                            color: '#9ca3af',
                            font: { size: 11 },
                            maxTicksLimit: 5,
                            callback: function(value) {
                                return formatOperationalAmount(value);
                            },
                        },
                    },
                    y: {
                        border: { display: false },
                        grid: { display: false },
                        ticks: {
                            color: '#4b5563',
                            font: { size: 12, weight: '500' },
                            padding: 8,
                        },
                    },
                },
            },
            plugins: [{
                id: 'operationalValueLabels',
                afterDatasetsDraw: function(chart) {
                    if (!chart.options.plugins.operationalValueLabels) {
                        return;
                    }
                    var ctx = chart.ctx;
                    var dataset = chart.data.datasets[0];
                    var meta = chart.getDatasetMeta(0);
                    meta.data.forEach(function(bar, index) {
                        var value = Number(dataset.data[index] || 0);
                        if (!value) {
                            return;
                        }
                        var props = bar.getProps(['x', 'y', 'base'], true);
                        var labelX = Math.max(props.x + 8, props.base + 8);
                        ctx.save();
                        ctx.fillStyle = '#111827';
                        ctx.font = '600 11px ui-sans-serif, system-ui, sans-serif';
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(formatOperationalAmount(value), labelX, props.y);
                        ctx.restore();
                    });
                },
            }],
        });
    }
    @endif
});
</script>
@endif
@endsection
