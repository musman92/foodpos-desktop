@extends('layouts.app')

@section('title', 'Activity Logs')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Activity Logs</h1>
            <p class="mt-1 text-sm text-gray-500">
                Trail of shifts, orders, payments, transfers, withdrawals, and money-source changes.
            </p>
        </div>
        <div class="text-sm flex flex-wrap items-center gap-2">
            @if($loggingEnabled)
                <span class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-green-700 border border-green-200">
                    Logging is ON
                </span>
            @else
                <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-amber-800 border border-amber-200">
                    Logging is OFF — nothing is recorded
                </span>
            @endif
            @if(auth()->user()->isSuperAdmin() || auth()->user()->isCompanyAdmin())
                <form method="post" action="{{ route('activity-logs.toggle') }}" class="inline">
                    @csrf
                    @if(auth()->user()->isSuperAdmin())
                        <input type="hidden" name="company_id" value="{{ $companyId }}">
                    @endif
                    <input type="hidden" name="enabled" value="{{ $loggingEnabled ? 0 : 1 }}">
                    <button type="submit" class="inline-flex items-center h-9 px-3 rounded-lg text-sm font-medium {{ $loggingEnabled ? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
                        {{ $loggingEnabled ? 'Turn off' : 'Turn on now' }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    @unless($loggingEnabled)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Activity logging is currently <strong>off</strong> for this company, so POS orders and purchases will not appear here.
            Click <strong>Turn on now</strong>, then create a new order/purchase to verify.
        </div>
    @endunless

    <form method="get" class="bg-white rounded-lg shadow border border-gray-200 p-4">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="block w-full filter-control md:w-40">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="block w-full filter-control md:w-40">
            </div>
            @if(show_branch_ui())
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                <select name="branch_id" class="block w-full filter-control md:w-48">
                    <option value="">All</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                <select name="action" class="block w-full filter-control md:w-56">
                    <option value="">All</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[12rem]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Description or action…" class="block w-full filter-control">
            </div>
            <button type="submit" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                Apply
            </button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">When</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Action</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Description</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">User</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Branch</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Shift</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                        <tr class="align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ format_datetime($log->created_at, $log->branch_id) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-indigo-700">{{ $log->action }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $log->description }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $log->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $log->branch?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if($log->shift)
                                    #{{ $log->shift_id }} · {{ format_date($log->shift->shift_date) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if(!empty($log->properties))
                                    <details>
                                        <summary class="cursor-pointer text-indigo-600 hover:text-indigo-800">View</summary>
                                        <pre class="mt-2 max-w-md overflow-x-auto rounded bg-gray-50 p-2 text-xs text-gray-700">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">No activity logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
