@extends('layouts.app')

@section('title', 'Payroll Adjustments')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold">Bonuses & deductions</h1><p class="text-sm text-gray-500">Pending items can be settled on direct employee payments or picked up by payroll.</p></div>
        @if(auth()->user()->hasAppPermission('payroll.store'))<a href="{{ route('hr.adjustments.create') }}" class="h-11 px-4 bg-indigo-600 text-white rounded-lg flex items-center text-sm">Add adjustment</a>@endif
    </div>
    <form method="GET" class="bg-white shadow rounded-lg p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <select name="employee_id" class="filter-control"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((int)request('employee_id') === (int)$employee->id)>{{ $employee->name }}</option>@endforeach</select>
        <select name="type" class="filter-control"><option value="">Bonus & deduction</option><option value="bonus" @selected(request('type') === 'bonus')>Bonus</option><option value="deduction" @selected(request('type') === 'deduction')>Deduction</option></select>
        <select name="status" class="filter-control"><option value="">All statuses</option>@foreach(['pending','partially_paid','paid','applied','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select>
        <div class="flex gap-2"><button class="h-11 px-4 bg-indigo-600 text-white rounded-lg">Filter</button><a href="{{ route('hr.adjustments.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Reset</a></div>
    </form>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', ['action' => route('hr.adjustments.index'), 'paginator' => $adjustments, 'perPage' => $perPage])
        <div class="overflow-x-auto"><table class="listing-table min-w-full text-sm divide-y"><thead class="bg-gray-50"><tr><th class="px-3 py-3 text-left">Date</th><th class="px-3 py-3 text-left">Employee</th><th class="px-3 py-3 text-left">Type</th><th class="px-3 py-3 text-right">Amount</th><th class="px-3 py-3 text-left">Status</th><th class="px-3 py-3 text-left">Notes</th><th class="px-3 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y">@forelse($adjustments as $item)<tr><td class="px-3 py-3">{{ format_date($item->effective_date) }}</td><td class="px-3 py-3">{{ $item->employee->name }}</td><td class="px-3 py-3 capitalize {{ $item->type === 'bonus' ? 'text-green-700' : 'text-red-700' }}">{{ $item->type }}</td><td class="px-3 py-3 text-right">{{ format_currency($item->amount) }}</td><td class="px-3 py-3 capitalize">{{ $item->status }}</td><td class="px-3 py-3">{{ $item->notes ?: '—' }}</td><td class="px-3 py-3 text-right">@if($item->status === 'pending' && auth()->user()->hasAppPermission('payroll.destroy'))<form method="POST" action="{{ route('hr.adjustments.destroy', $item) }}" onsubmit="return confirm('Delete this pending adjustment?')">@csrf @method('DELETE')<button class="text-red-600"><i class="fas fa-trash"></i></button></form>@endif</td></tr>@empty<tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">No payroll adjustments found.</td></tr>@endforelse</tbody>
        </table></div>
        @include('partials.listing-table-pagination', ['paginator' => $adjustments])
    </div>
</div>
@endsection
