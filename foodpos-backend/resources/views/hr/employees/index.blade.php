@extends('layouts.app')

@section('title', 'Employees')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Employees</h1>
            <p class="mt-1 text-sm text-gray-500">HR profiles, pay settings and employee balances.</p>
        </div>
        @if(auth()->user()->hasAppPermission('employees.store'))
                    <a href="{{ route('hr.employees.create') }}" class="inline-flex items-center justify-center h-11 px-4 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                <i class="fas fa-user-plus mr-2"></i>Add employee
            </a>
        @endif
    </div>

    <form method="GET" class="bg-white shadow rounded-lg p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, email or phone" class="filter-control">
        @if(show_branch_ui())
        <select name="branch_id" class="filter-control">
            <option value="">All branches</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((int) request('branch_id') === (int) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @endif
        <select name="status" class="filter-control">
            <option value="">All statuses</option>
            @foreach(\App\Models\EmployeeProfile::EMPLOYMENT_STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="h-11 px-4 rounded-lg bg-indigo-600 text-white text-sm font-medium">Filter</button>
            <a href="{{ route('hr.employees.index') }}" class="h-11 px-4 rounded-lg border border-gray-300 flex items-center text-sm">Reset</a>
        </div>
    </form>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', ['action' => route('hr.employees.index'), 'paginator' => $employees, 'perPage' => $perPage])
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left">Employee</th>
                        <th class="px-3 py-3 text-left">Designation</th>
                        <th class="px-3 py-3 text-left">Branches</th>
                        <th class="px-3 py-3 text-left">Pay cycle</th>
                        <th class="px-3 py-3 text-right">Rate</th>
                        <th class="px-3 py-3 text-right">Balance</th>
                        <th class="px-3 py-3 text-left">Status</th>
                        <th class="px-3 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($employees as $profile)
                        @continue(! $profile->user)
                        @php $balance = (float) ($ledgerBalances[(int) $profile->user_id] ?? 0); @endphp
                        <tr>
                            <td class="px-3 py-3">
                                <a href="{{ route('hr.employees.show', $profile) }}" class="font-medium text-indigo-700 hover:text-indigo-900">{{ $profile->user->name }}</a>
                                <div class="text-xs text-gray-500">{{ $profile->employee_number ?: 'No employee number' }}</div>
                            </td>
                            <td class="px-3 py-3 text-gray-600">{{ $profile->designation ?: '—' }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $profile->user->branches->pluck('name')->join(', ') ?: ($profile->user->branch?->name ?? '—') }}</td>
                            <td class="px-3 py-3 capitalize">{{ $profile->pay_frequency }}</td>
                            <td class="px-3 py-3 text-right">{{ format_currency($profile->pay_rate) }}</td>
                            <td class="px-3 py-3 text-right {{ abs($balance) < 0.009 ? 'text-gray-600' : ($balance > 0 ? 'text-green-700' : 'text-red-700') }}">
                                {{ format_currency(abs($balance)) }}
                                <div class="text-xs {{ abs($balance) < 0.009 ? 'text-gray-400' : ($balance > 0 ? 'text-green-600' : 'text-red-600') }}">
                                    {{ abs($balance) < 0.009 ? 'settled' : ($balance > 0 ? 'payable' : 'advance') }}
                                </div>
                            </td>
                            <td class="px-3 py-3 capitalize">{{ $profile->employment_status }}</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('hr.employees.show', $profile) }}" class="text-indigo-600 mx-1" title="View"><i class="fas fa-eye"></i></a>
                                @if(auth()->user()->hasAppPermission('employees.update'))
                                    <a href="{{ route('hr.employees.edit', $profile) }}" class="text-gray-600 mx-1" title="Edit"><i class="fas fa-pen"></i></a>
                                @endif
                                @if(auth()->user()->hasAppPermission('employee-payments.store'))
                                    <a href="{{ route('employee-payments.create', ['employee_id' => $profile->user_id]) }}" class="text-green-600 mx-1" title="Pay"><i class="fas fa-money-bill-wave"></i></a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">No employees found. Add employees here first; enable login later from Users if needed.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.listing-table-pagination', ['paginator' => $employees])
    </div>
</div>
@endsection
