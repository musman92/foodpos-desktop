@extends('layouts.app')

@section('title', 'Generate Payroll')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div><h1 class="text-2xl font-bold">Generate payroll</h1><p class="text-sm text-gray-500">Attendance, overtime, pending bonuses, deductions and advances will be calculated into a reviewable draft.</p></div>
    @if($errors->any())<div class="p-4 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('hr.payroll.store') }}" class="bg-white shadow rounded-lg p-6 space-y-5">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @if(show_branch_ui())<div><label class="block text-sm font-medium mb-1">Branch *</label><select name="branch_id" required class="w-full h-11 px-3 rounded-lg border-gray-300">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)old('branch_id', current_branch_id()) === (int)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@else<input type="hidden" name="branch_id" value="{{ old('branch_id', current_branch_id()) }}">@endif
            <div><label class="block text-sm font-medium mb-1">Pay cycle *</label><select name="pay_frequency" required class="w-full h-11 px-3 rounded-lg border-gray-300">@foreach($frequencies as $frequency)<option value="{{ $frequency }}" @selected(old('pay_frequency', 'monthly') === $frequency)>{{ ucfirst($frequency) }}</option>@endforeach</select></div>
            <div><label class="block text-sm font-medium mb-1">Period start *</label><input type="date" name="period_start" required value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-medium mb-1">Period end *</label><input type="date" name="period_end" required value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
        </div>
        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
            <strong>Calculation:</strong> base pay uses payable attendance days, overtime uses actual overtime hours × employee OT rate, then bonuses are added and deductions/advances are removed. OT hours remain visible when the OT rate is zero.
        </div>
        <div><label class="block text-sm font-medium mb-1">Notes</label><textarea name="notes" rows="3" class="w-full px-3 py-2 rounded-lg border-gray-300">{{ old('notes') }}</textarea></div>
        <div class="flex justify-end gap-3 border-t pt-5"><a href="{{ route('hr.payroll.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Cancel</a><button class="h-11 px-5 bg-indigo-600 text-white rounded-lg">Generate draft</button></div>
    </form>
</div>
@endsection
