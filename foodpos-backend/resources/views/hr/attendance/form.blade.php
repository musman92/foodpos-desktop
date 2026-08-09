@extends('layouts.app')

@php $editing = $attendanceRecord && $attendanceRecord->exists; @endphp
@section('title', $editing ? 'Edit Attendance' : 'Record Attendance')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div><h1 class="text-2xl font-bold">{{ $editing ? 'Edit attendance' : 'Record attendance' }}</h1><p class="text-sm text-gray-500">Clock times or total worked hours can be used. Overtime is calculated from employee settings.</p></div>
    @if($errors->any())<div class="p-4 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ $editing ? route('hr.attendance.update', $attendanceRecord) : route('hr.attendance.store') }}" class="bg-white shadow rounded-lg p-6 space-y-5">
        @csrf @if($editing) @method('PUT') @endif
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @if(show_branch_ui())<div><label class="block text-sm font-medium mb-1">Branch *</label><select name="branch_id" required class="w-full h-11 px-3 rounded-lg border-gray-300">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)old('branch_id', $selectedBranchId) === (int)$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>@else<input type="hidden" name="branch_id" value="{{ old('branch_id', $selectedBranchId) }}">@endif
            <div><label class="block text-sm font-medium mb-1">Employee *</label><select name="employee_id" required class="w-full h-11 px-3 rounded-lg border-gray-300"><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((int)old('employee_id', $selectedEmployeeId) === (int)$employee->id)>{{ $employee->name }} ({{ $employee->employeeProfile->standard_hours_per_day }}h standard)</option>@endforeach</select></div>
            <div><label class="block text-sm font-medium mb-1">Date *</label><input type="date" name="attendance_date" required value="{{ old('attendance_date', $attendanceRecord?->attendance_date?->format('Y-m-d') ?? local_today($selectedBranchId)) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-medium mb-1">Status *</label><select name="status" required class="w-full h-11 px-3 rounded-lg border-gray-300">@foreach(\App\Models\AttendanceRecord::STATUSES as $status)<option value="{{ $status }}" @selected(old('status', $attendanceRecord?->status ?? 'present') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
            <div><label class="block text-sm font-medium mb-1">Clock in</label><input type="datetime-local" name="clock_in" value="{{ old('clock_in', $attendanceRecord?->clock_in?->format('Y-m-d\TH:i')) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-medium mb-1">Clock out</label><input type="datetime-local" name="clock_out" value="{{ old('clock_out', $attendanceRecord?->clock_out?->format('Y-m-d\TH:i')) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-medium mb-1">Break minutes</label><input type="number" min="0" name="break_minutes" value="{{ old('break_minutes', $attendanceRecord?->break_minutes ?? 0) }}" class="w-full h-11 px-3 rounded-lg border-gray-300"></div>
            <div><label class="block text-sm font-medium mb-1">Or total worked hours</label><input type="number" step="0.01" min="0" max="24" name="worked_hours" value="{{ old('worked_hours') }}" class="w-full h-11 px-3 rounded-lg border-gray-300" placeholder="Overrides clock calculation"><p class="text-xs text-gray-500 mt-1">Example: 12 for a 12-hour day.</p></div>
        </div>
        <div><label class="block text-sm font-medium mb-1">Notes</label><textarea name="notes" rows="3" class="w-full px-3 py-2 rounded-lg border-gray-300">{{ old('notes', $attendanceRecord?->notes) }}</textarea></div>
        <div class="flex justify-end gap-3 border-t pt-5"><a href="{{ route('hr.attendance.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Cancel</a><button class="h-11 px-5 bg-indigo-600 text-white rounded-lg">{{ $editing ? 'Update attendance' : 'Save attendance' }}</button></div>
    </form>
</div>
@endsection
