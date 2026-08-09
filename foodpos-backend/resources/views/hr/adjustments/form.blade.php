@extends('layouts.app')

@section('title', 'Add Payroll Adjustment')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div><h1 class="text-2xl font-bold">Add bonus or deduction</h1><p class="text-sm text-gray-500">This will be included when payroll covering the effective date is generated.</p></div>
    @if($errors->any())<div class="p-4 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('hr.adjustments.store') }}" class="bg-white shadow rounded-lg p-6 space-y-5">
        @csrf
        @if(show_branch_ui())<div><label class="block text-sm font-medium mb-1">Branch *</label><select name="branch_id" required class="w-full h-11 px-3 rounded-lg border-gray-300">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)old('branch_id', current_branch_id()) === (int)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@else<input type="hidden" name="branch_id" value="{{ old('branch_id', current_branch_id()) }}">@endif
        <div><label class="block text-sm font-medium mb-1">Employee *</label><select name="employee_id" required class="w-full h-11 px-3 rounded-lg border-gray-300"><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((int)old('employee_id', $selectedEmployeeId) === (int)$employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium mb-1">Type *</label><select name="type" required class="w-full h-11 px-3 rounded-lg border-gray-300"><option value="bonus" @selected(old('type', $selectedType ?? 'bonus') === 'bonus')>Bonus (adds pay)</option><option value="deduction" @selected(old('type', $selectedType) === 'deduction')>Deduction (reduces pay)</option></select></div>
            <div><label class="block text-sm font-medium mb-1">Amount *</label><input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div class="col-span-2"><label class="block text-sm font-medium mb-1">Effective date *</label><input type="date" name="effective_date" required value="{{ old('effective_date', local_today(current_branch_id())) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
        </div>
        <div><label class="block text-sm font-medium mb-1">Reason / notes</label><textarea name="notes" rows="3" class="w-full px-3 py-2 rounded-lg border-gray-300">{{ old('notes') }}</textarea></div>
        <div class="flex justify-end gap-3 border-t pt-5"><a href="{{ route('hr.adjustments.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Cancel</a><button class="h-11 px-5 bg-indigo-600 text-white rounded-lg">Save adjustment</button></div>
    </form>
</div>
@endsection
