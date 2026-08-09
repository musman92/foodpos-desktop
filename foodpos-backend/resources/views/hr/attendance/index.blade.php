@extends('layouts.app')

@section('title', 'Attendance')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold">Attendance</h1><p class="text-sm text-gray-500">Check employees in, manage breaks, and finish their workday.</p></div>
        @if(auth()->user()->hasAppPermission('attendance.store'))
            <a href="{{ route('hr.attendance.create', ['branch_id' => $branchId]) }}" class="h-11 px-4 rounded-lg border border-gray-300 bg-white text-gray-700 flex items-center text-sm font-medium"><i class="fas fa-pen mr-2"></i>Manual entry</a>
        @endif
    </div>

    @if($errors->has('attendance'))
        <div class="p-4 rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm">{{ $errors->first('attendance') }}</div>
    @endif

    <section class="bg-white shadow rounded-xl overflow-hidden">
        <div class="p-5 border-b border-gray-200 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fas fa-user-clock"></i></span>
                    <div>
                        <h2 class="font-semibold text-gray-900">Today’s team</h2>
                        <p class="text-sm text-gray-500">{{ format_date($boardDate) }} · {{ $boardEmployees->count() }} employees</p>
                    </div>
                </div>
            </div>
            @if(show_branch_ui())
            <form method="GET" action="{{ route('hr.attendance.index') }}" class="flex items-center gap-2">
                <label for="attendance-board-branch" class="text-sm font-medium text-gray-600">Branch</label>
                <select id="attendance-board-branch" name="branch_id" onchange="this.form.submit()" class="filter-control min-w-48">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </form>
            @endif
        </div>

        <div class="p-5 grid grid-cols-1 xl:grid-cols-2 gap-4">
            @forelse($boardEmployees as $employee)
                @php($todayRecord = $todayRecords->get($employee->id))
                <article class="rounded-xl border border-gray-200 p-4 flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex items-center gap-3 min-w-0 sm:flex-1">
                        <div class="w-11 h-11 shrink-0 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center font-semibold">
                            {{ strtoupper(mb_substr($employee->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-900 truncate">{{ $employee->name }}</h3>
                            <p class="text-xs text-gray-500 truncate">
                                {{ $employee->employeeProfile?->designation ?: $employee->employeeProfile?->employee_number ?: 'Employee' }}
                            </p>
                            @if($todayRecord)
                                <div class="mt-1 text-xs">
                                    @if(in_array($todayRecord->status, ['paid_leave', 'unpaid_leave']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-50 text-blue-700"><i class="fas fa-calendar-check mr-1"></i>{{ $todayRecord->status === 'paid_leave' ? 'Paid leave' : 'Unpaid leave' }}</span>
                                    @elseif($todayRecord->status === 'absent')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-50 text-red-700"><i class="fas fa-user-xmark mr-1"></i>Absent</span>
                                    @elseif($todayRecord->clock_out)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-700"><i class="fas fa-circle-check mr-1"></i>Finished · {{ format_time($todayRecord->clock_in) }}–{{ format_time($todayRecord->clock_out) }}</span>
                                    @elseif($todayRecord->break_started_at)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-50 text-amber-700"><i class="fas fa-mug-hot mr-1"></i>On break · {{ format_time($todayRecord->break_started_at) }}</span>
                                    @elseif($todayRecord->clock_in)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700"><i class="fas fa-circle mr-1 text-[7px]"></i>Check in {{ format_time($todayRecord->clock_in) }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">Recorded manually</span>
                                    @endif
                                </div>
                            @else
                                <p class="mt-1 text-xs text-gray-400">Not marked yet</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                        @if(!$todayRecord)
                            @if(auth()->user()->hasAppPermission('attendance.store'))
                                <form method="POST" action="{{ route('hr.attendance.action', $employee->id) }}">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                                    <input type="hidden" name="action" value="check_in">
                                    <button class="h-9 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium"><i class="fas fa-right-to-bracket mr-1"></i>Check in</button>
                                </form>
                            @endif
                            @if(auth()->user()->hasAppPermission('leaves.store'))
                                <a href="{{ route('hr.leaves.create', ['branch_id' => $branchId, 'employee_id' => $employee->id, 'start_date' => $boardDate, 'end_date' => $boardDate]) }}" class="h-9 px-3 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium flex items-center"><i class="fas fa-calendar-minus mr-1"></i>Leave</a>
                            @endif
                            @if(auth()->user()->hasAppPermission('attendance.store'))
                                <form method="POST" action="{{ route('hr.attendance.action', $employee->id) }}" onsubmit="return confirm('Mark {{ addslashes($employee->name) }} absent today?')">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                                    <input type="hidden" name="action" value="absent">
                                    <button class="h-9 px-3 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium"><i class="fas fa-user-xmark mr-1"></i>Absent</button>
                                </form>
                            @endif
                        @elseif($todayRecord->status === 'present' && $todayRecord->clock_in && !$todayRecord->clock_out && auth()->user()->hasAppPermission('attendance.update'))
                            @if($todayRecord->break_started_at)
                                <form method="POST" action="{{ route('hr.attendance.action', $employee->id) }}">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                                    <input type="hidden" name="action" value="end_break">
                                    <button class="h-9 px-3 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-sm font-medium"><i class="fas fa-play mr-1"></i>Back to work</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('hr.attendance.action', $employee->id) }}">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                                    <input type="hidden" name="action" value="start_break">
                                    <button class="h-9 px-3 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 text-sm font-medium"><i class="fas fa-mug-hot mr-1"></i>Start break</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('hr.attendance.action', $employee->id) }}" onsubmit="return confirm('Check {{ addslashes($employee->name) }} out now?')">
                                @csrf
                                <input type="hidden" name="branch_id" value="{{ $branchId }}">
                                <input type="hidden" name="action" value="check_out">
                                <button class="h-9 px-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium"><i class="fas fa-right-from-bracket mr-1"></i>Check out</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="xl:col-span-2 py-10 text-center text-gray-500">
                    <i class="fas fa-users text-2xl text-gray-300 mb-2"></i>
                    <p>No active employees are assigned to this branch.</p>
                </div>
            @endforelse
        </div>
    </section>

    <div class="flex items-center justify-between">
        <div><h2 class="text-lg font-semibold text-gray-900">Attendance history</h2><p class="text-sm text-gray-500">Review and correct previous entries.</p></div>
    </div>

    <form method="GET" class="bg-white shadow rounded-lg p-4 grid grid-cols-1 md:grid-cols-5 gap-3">
        @if(show_branch_ui())<select name="branch_id" class="filter-control"><option value="">Current branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)$branchId === (int)$branch->id)>{{ $branch->name }}</option>@endforeach</select>@else<input type="hidden" name="branch_id" value="{{ $branchId }}">@endif
        <select name="employee_id" class="filter-control"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((int)request('employee_id') === (int)$employee->id)>{{ $employee->name }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ request('from') }}" class="filter-control">
        <input type="date" name="to" value="{{ request('to') }}" class="filter-control">
        <div class="flex gap-2"><button class="h-11 px-4 bg-indigo-600 text-white rounded-lg">Filter</button><a href="{{ route('hr.attendance.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Reset</a></div>
    </form>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', ['action' => route('hr.attendance.index'), 'paginator' => $records, 'perPage' => $perPage])
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-3 py-3 text-left">Date</th><th class="px-3 py-3 text-left">Employee</th><th class="px-3 py-3 text-left">Status</th><th class="px-3 py-3 text-left">Clock in / out</th><th class="px-3 py-3 text-right">Worked</th><th class="px-3 py-3 text-right">Regular</th><th class="px-3 py-3 text-right">Overtime</th><th class="px-3 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y">
                    @forelse($records as $record)
                        <tr>
                            <td class="px-3 py-3">{{ format_date($record->attendance_date) }}</td>
                            <td class="px-3 py-3 font-medium">{{ $record->employee->name }}</td>
                            <td class="px-3 py-3 capitalize">{{ str_replace('_', ' ', $record->status) }}</td>
                            <td class="px-3 py-3 text-gray-600">{{ $record->clock_in ? format_time($record->clock_in) : '—' }} – {{ $record->clock_out ? format_time($record->clock_out) : '—' }}</td>
                            <td class="px-3 py-3 text-right">{{ number_format($record->worked_minutes / 60, 2) }}h</td>
                            <td class="px-3 py-3 text-right">{{ number_format($record->regular_minutes / 60, 2) }}h</td>
                            <td class="px-3 py-3 text-right {{ $record->overtime_minutes > 0 ? 'text-amber-700 font-medium' : '' }}">{{ number_format($record->overtime_minutes / 60, 2) }}h</td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                @if(auth()->user()->hasAppPermission('attendance.update'))<a href="{{ route('hr.attendance.edit', $record) }}" class="text-indigo-600 mx-1"><i class="fas fa-pen"></i></a>@endif
                                @if(auth()->user()->hasAppPermission('attendance.destroy'))<form method="POST" action="{{ route('hr.attendance.destroy', $record) }}" class="inline" onsubmit="return confirm('Delete this attendance record?')">@csrf @method('DELETE')<button class="text-red-600 mx-1"><i class="fas fa-trash"></i></button></form>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">No attendance records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.listing-table-pagination', ['paginator' => $records])
    </div>
</div>
@endsection
