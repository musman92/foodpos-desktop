@extends('layouts.app')

@section('title', 'Payroll')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold">Payroll</h1><p class="text-sm text-gray-500">Generate, review, finalize and pay branch payroll.</p></div>
        @if(auth()->user()->hasAppPermission('payroll.store'))<a href="{{ route('hr.payroll.create') }}" class="h-11 px-4 bg-indigo-600 text-white rounded-lg flex items-center text-sm"><i class="fas fa-calculator mr-2"></i>Generate payroll</a>@endif
    </div>
    <form method="GET" class="bg-white shadow rounded-lg p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        @if(show_branch_ui())<select name="branch_id" class="filter-control"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)request('branch_id') === (int)$branch->id)>{{ $branch->name }}</option>@endforeach</select>@endif
        <select name="pay_frequency" class="filter-control"><option value="">All cycles</option>@foreach(\App\Models\EmployeeProfile::PAY_FREQUENCIES as $frequency)<option value="{{ $frequency }}" @selected(request('pay_frequency') === $frequency)>{{ ucfirst($frequency) }}</option>@endforeach</select>
        <select name="status" class="filter-control"><option value="">All statuses</option>@foreach(\App\Models\PayrollRun::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select>
        <div class="flex gap-2"><button class="h-11 px-4 bg-indigo-600 text-white rounded-lg">Filter</button><a href="{{ route('hr.payroll.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Reset</a></div>
    </form>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', ['action' => route('hr.payroll.index'), 'paginator' => $runs, 'perPage' => $perPage])
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-3 py-3 text-left">Payroll #</th><th class="px-3 py-3 text-left">Branch</th><th class="px-3 py-3 text-left">Period</th><th class="px-3 py-3 text-left">Cycle</th><th class="px-3 py-3 text-right">Employees</th><th class="px-3 py-3 text-right">Net</th><th class="px-3 py-3 text-right">Paid</th><th class="px-3 py-3 text-left">Status</th></tr></thead>
                <tbody class="divide-y">
                    @forelse($runs as $run)
                        <tr>
                            <td class="px-3 py-3"><a class="font-medium text-indigo-700" href="{{ route('hr.payroll.show', $run) }}">{{ $run->payroll_number }}</a></td>
                            <td class="px-3 py-3">{{ $run->branch->name ?? '—' }}</td>
                            <td class="px-3 py-3">{{ format_date($run->period_start) }} – {{ format_date($run->period_end) }}</td>
                            <td class="px-3 py-3 capitalize">{{ $run->pay_frequency }}</td>
                            <td class="px-3 py-3 text-right">{{ $run->employee_count }}</td>
                            <td class="px-3 py-3 text-right">{{ format_currency($run->net_total) }}</td>
                            <td class="px-3 py-3 text-right">{{ format_currency($run->paid_total) }}</td>
                            <td class="px-3 py-3 capitalize">{{ str_replace('_', ' ', $run->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">No payroll runs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.listing-table-pagination', ['paginator' => $runs])
    </div>
</div>
@endsection
