@extends('layouts.app')

@section('title', 'Employee Leave')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold">Employee leave</h1><p class="text-sm text-gray-500">Approved leave is posted to attendance and included in payroll.</p></div>
        @if(auth()->user()->hasAppPermission('leaves.store'))<a href="{{ route('hr.leaves.create') }}" class="h-11 px-4 bg-indigo-600 text-white rounded-lg flex items-center text-sm">New leave request</a>@endif
    </div>
    @if($errors->any())<div class="p-4 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif
    <form method="GET" class="bg-white shadow rounded-lg p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <select name="employee_id" class="filter-control"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((int)request('employee_id') === (int)$employee->id)>{{ $employee->name }}</option>@endforeach</select>
        <select name="leave_type" class="filter-control"><option value="">Paid & unpaid</option><option value="paid" @selected(request('leave_type') === 'paid')>Paid leave</option><option value="unpaid" @selected(request('leave_type') === 'unpaid')>Unpaid leave</option></select>
        <select name="status" class="filter-control"><option value="">All statuses</option>@foreach(\App\Models\EmployeeLeaveRequest::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <div class="flex gap-2"><button class="h-11 px-4 bg-indigo-600 text-white rounded-lg">Filter</button><a href="{{ route('hr.leaves.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Reset</a></div>
    </form>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', ['action' => route('hr.leaves.index'), 'paginator' => $leaves, 'perPage' => $perPage])
        <div class="overflow-x-auto"><table class="listing-table min-w-full text-sm divide-y"><thead class="bg-gray-50"><tr><th class="px-3 py-3 text-left">Employee</th><th class="px-3 py-3 text-left">Dates</th><th class="px-3 py-3 text-left">Type</th><th class="px-3 py-3 text-right">Days</th><th class="px-3 py-3 text-left">Status</th><th class="px-3 py-3 text-left">Reason</th><th class="px-3 py-3 text-right">Actions</th></tr></thead>
            <tbody class="divide-y">@forelse($leaves as $leave)<tr><td class="px-3 py-3 font-medium">{{ $leave->employee->name }}</td><td class="px-3 py-3">{{ format_date($leave->start_date) }} – {{ format_date($leave->end_date) }}</td><td class="px-3 py-3 capitalize">{{ $leave->leave_type }} leave</td><td class="px-3 py-3 text-right">{{ $leave->days }}</td><td class="px-3 py-3 capitalize">{{ $leave->status }}</td><td class="px-3 py-3">{{ $leave->reason ?: '—' }}</td><td class="px-3 py-3 text-right whitespace-nowrap">
                @if($leave->status === 'pending' && auth()->user()->hasAppPermission('leaves.update'))
                    <form method="POST" action="{{ route('hr.leaves.approve', $leave) }}" class="inline" onsubmit="return confirm('Approve this leave and update attendance?')">@csrf<button class="text-green-600 mx-1" title="Approve"><i class="fas fa-check"></i></button></form>
                    <form method="POST" action="{{ route('hr.leaves.reject', $leave) }}" class="inline" onsubmit="return confirm('Reject this leave request?')">@csrf<button class="text-amber-600 mx-1" title="Reject"><i class="fas fa-times"></i></button></form>
                @endif
                @if($leave->status === 'pending' && auth()->user()->hasAppPermission('leaves.destroy'))<form method="POST" action="{{ route('hr.leaves.destroy', $leave) }}" class="inline" onsubmit="return confirm('Delete this leave request?')">@csrf @method('DELETE')<button class="text-red-600 mx-1"><i class="fas fa-trash"></i></button></form>@endif
            </td></tr>@empty<tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">No leave requests found.</td></tr>@endforelse</tbody>
        </table></div>
        @include('partials.listing-table-pagination', ['paginator' => $leaves])
    </div>
</div>
@endsection
