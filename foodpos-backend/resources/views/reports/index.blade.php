@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Reports</h1>
        <p class="mt-1 text-sm text-gray-500">Generate common business reports. Select a report below.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @if(auth()->user()->hasAppPermission('reports.sales') || auth()->user()->hasAppPermission('reports.sales-by-category'))
        <a href="{{ route('reports.sales') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center group-hover:bg-green-200 transition">
                    <i class="fas fa-chart-line text-green-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Sales</h2>
            </div>
            <p class="text-sm text-gray-500">Revenue summary and sales by category for a date range, with optional category order drilldown.</p>
        </a>
        @endif

        @if(auth()->user()->hasAppPermission('reports.sales-by-item'))
        <a href="{{ route('reports.sales-by-item') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-lime-100 flex items-center justify-center group-hover:bg-lime-200 transition">
                    <i class="fas fa-utensils text-lime-700 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Sales by Item</h2>
            </div>
            <p class="text-sm text-gray-500">Filter by one or more categories and menu items to view every order in which they were sold.</p>
        </a>
        @endif

        <a href="{{ route('reports.top-selling') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition">
                    <i class="fas fa-trophy text-amber-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Top Selling Items</h2>
            </div>
            <p class="text-sm text-gray-500">Best-selling menu items and deals by quantity and revenue.</p>
        </a>

        <a href="{{ route('reports.daily') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition">
                    <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Daily Sales</h2>
            </div>
            <p class="text-sm text-gray-500">Day-by-day sales and order count for any date range.</p>
        </a>

        <a href="{{ route('reports.z-report') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-slate-200 transition">
                    <i class="fas fa-file-invoice text-slate-700 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Z Report</h2>
            </div>
            <p class="text-sm text-gray-500">End-of-shift sales, payment breakdown, and cash drawer reconciliation per cashier shift.</p>
        </a>

        <a href="{{ route('reports.payment-methods') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition">
                    <i class="fas fa-money-bill-wave text-indigo-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Payment Methods</h2>
            </div>
            <p class="text-sm text-gray-500">Revenue and order count by payment method (money source).</p>
        </a>

        @if(auth()->user()->hasAppPermission('reports.transactions-by-money-source'))
        <a href="{{ route('reports.transactions-by-money-source') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-cyan-100 flex items-center justify-center group-hover:bg-cyan-200 transition">
                    <i class="fas fa-exchange-alt text-cyan-700 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Transactions by Money Source</h2>
            </div>
            <p class="text-sm text-gray-500">All in &amp; out transactions per money source for any date range.</p>
        </a>
        @endif

        @if(auth()->user()->hasAppPermission('reports.foc'))
        <a href="{{ route('reports.foc') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-rose-100 flex items-center justify-center group-hover:bg-rose-200 transition">
                    <i class="fas fa-gift text-rose-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">FOC</h2>
            </div>
            <p class="text-sm text-gray-500">Complimentary orders and total value given away for any date range.</p>
        </a>
        @endif

        <a href="{{ route('reports.gross-margin') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition">
                    <i class="fas fa-percentage text-emerald-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Gross Margin</h2>
            </div>
            <p class="text-sm text-gray-500">Sale price, recipe cost, and gross margin per menu item with search and sorting.</p>
        </a>

        @if(auth()->user()->hasAppPermission('reports.consumption'))
        <a href="{{ route('reports.consumption') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 transition">
                    <i class="fas fa-box-open text-orange-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Consumption</h2>
            </div>
            <p class="text-sm text-gray-500">How much inventory was used and its purchase cost — ingredients and tracked menu items.</p>
        </a>

        <a href="{{ route('reports.ingredient-ledger') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition">
                    <i class="fas fa-clipboard-list text-amber-700 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Ingredient Ledger</h2>
            </div>
            <p class="text-sm text-gray-500">Full timeline for one ingredient: purchases, sales usage, adjustments, running balance, and current batches.</p>
        </a>
        @endif

        <a href="{{ route('reports.profit-loss') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-violet-100 flex items-center justify-center group-hover:bg-violet-200 transition">
                    <i class="fas fa-file-invoice-dollar text-violet-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Profit &amp; Loss</h2>
            </div>
            <p class="text-sm text-gray-500">Income statement with revenue, COGS, operating expenses, and net profit for any date range.</p>
        </a>

        <a href="{{ route('reports.order-history') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-sky-100 flex items-center justify-center group-hover:bg-sky-200 transition">
                    <i class="fas fa-receipt text-sky-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Order History</h2>
            </div>
            <p class="text-sm text-gray-500">Filter orders by customer, waiter, rider, type, bill number, and date range. Export to PDF.</p>
        </a>

        <a href="{{ route('reports.weekly-closing') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-teal-100 flex items-center justify-center group-hover:bg-teal-200 transition">
                    <i class="fas fa-calendar-week text-teal-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Weekly Closing</h2>
            </div>
            <p class="text-sm text-gray-500">Available stock, daily sales by payment method, and weekly closing for up to 4 business weeks.</p>
        </a>

        <a href="{{ route('reports.monthly-closing') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-cyan-100 flex items-center justify-center group-hover:bg-cyan-200 transition">
                    <i class="fas fa-calendar-alt text-cyan-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Monthly Closing</h2>
            </div>
            <p class="text-sm text-gray-500">Same closing layout for one calendar month — available stock, daily sales, and month-end summary.</p>
        </a>

        <a href="{{ route('reports.accounts-receivable') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition">
                    <i class="fas fa-hand-holding-usd text-amber-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Accounts Receivable</h2>
            </div>
            <p class="text-sm text-gray-500">Outstanding customer credit balances — amounts customers owe you.</p>
        </a>

        <a href="{{ route('reports.accounts-payable') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-rose-100 flex items-center justify-center group-hover:bg-rose-200 transition">
                    <i class="fas fa-truck-loading text-rose-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Accounts Payable</h2>
            </div>
            <p class="text-sm text-gray-500">Outstanding supplier balances — amounts you owe vendors.</p>
        </a>

        <a href="{{ route('reports.customer-credits') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition">
                    <i class="fas fa-piggy-bank text-emerald-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Customer Credits</h2>
            </div>
            <p class="text-sm text-gray-500">Customer advances and prepayments available for future sales.</p>
        </a>

        <a href="{{ route('reports.supplier-prepayments') }}" class="group block bg-white rounded-xl shadow border border-gray-200 p-6 hover:shadow-lg hover:border-indigo-200 transition">
            <div class="flex items-center mb-3">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-teal-100 flex items-center justify-center group-hover:bg-teal-200 transition">
                    <i class="fas fa-wallet text-teal-600 text-xl"></i>
                </div>
                <h2 class="ml-3 text-lg font-semibold text-gray-900 group-hover:text-indigo-600">Supplier Prepayments</h2>
            </div>
            <p class="text-sm text-gray-500">Amounts prepaid to suppliers available against future purchases.</p>
        </a>
    </div>
</div>
@endsection
