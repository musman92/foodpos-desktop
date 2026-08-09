@extends('layouts.app')

@section('title', 'New Leave Request')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div><h1 class="text-2xl font-bold">New leave request</h1><p class="text-sm text-gray-500">Only scheduled working days are counted. Approval creates paid or unpaid attendance entries.</p></div>
    @if($errors->any())<div class="p-4 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('hr.leaves.store') }}" class="bg-white shadow rounded-lg p-6 space-y-5">
        @csrf
        @if(show_branch_ui())<div><label class="block text-sm font-medium mb-1">Branch *</label><select name="branch_id" required class="w-full h-11 px-3 rounded-lg border-gray-300">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)old('branch_id', $selectedBranchId) === (int)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@else<input type="hidden" name="branch_id" value="{{ old('branch_id', $selectedBranchId) }}">@endif
        <div><label class="block text-sm font-medium mb-1">Employee *</label><select name="employee_id" required class="w-full h-11 px-3 rounded-lg border-gray-300"><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((int)old('employee_id', $selectedEmployeeId) === (int)$employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
        <div><label class="block text-sm font-medium mb-1">Leave type *</label><select name="leave_type" required class="w-full h-11 px-3 rounded-lg border-gray-300"><option value="paid" @selected(old('leave_type', 'paid') === 'paid')>Paid leave</option><option value="unpaid" @selected(old('leave_type') === 'unpaid')>Unpaid leave</option></select></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium mb-1">Start date *</label><input type="date" name="start_date" required value="{{ old('start_date', $selectedStartDate) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-medium mb-1">End date *</label><input type="date" name="end_date" required value="{{ old('end_date', $selectedEndDate) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
        </div>
        <div><label class="block text-sm font-medium mb-1">Reason</label><textarea name="reason" rows="3" class="w-full px-3 py-2 rounded-lg border-gray-300">{{ old('reason') }}</textarea></div>
        <div class="flex justify-end gap-3 border-t pt-5"><a href="{{ route('hr.leaves.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Cancel</a><button class="h-11 px-5 bg-indigo-600 text-white rounded-lg">Create request</button></div>
    </form>
</div>
@endsection
