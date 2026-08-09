@extends('layouts.app')

@section('title', $employeeProfile->user->name)

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $employeeProfile->user->name }}</h1>
            <p class="text-sm text-gray-500">{{ $employeeProfile->employee_number }} · {{ $employeeProfile->designation ?: 'Employee' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('hr.attendance.create', ['employee_id' => $employeeProfile->user_id]) }}" class="h-11 px-4 border rounded-lg flex items-center text-sm"><i class="fas fa-clock mr-2"></i>Attendance</a>
            <a href="{{ route('hr.leaves.create', ['employee_id' => $employeeProfile->user_id]) }}" class="h-11 px-4 border rounded-lg flex items-center text-sm"><i class="fas fa-calendar-minus mr-2"></i>Leave</a>
            <a href="{{ route('hr.adjustments.create', ['employee_id' => $employeeProfile->user_id]) }}" class="h-11 px-4 border rounded-lg flex items-center text-sm"><i class="fas fa-plus-minus mr-2"></i>Bonus / deduction</a>
            <a href="{{ route('employee-payments.create', ['employee_id' => $employeeProfile->user_id]) }}" class="h-11 px-4 rounded-lg bg-green-600 text-white flex items-center text-sm"><i class="fas fa-money-bill-wave mr-2"></i>Employee payment</a>
            @if(auth()->user()->hasAppPermission('account-statements.index'))
                <a href="{{ route('account-statements.index', ['type' => 'employee', 'party_id' => $employeeProfile->user_id]) }}" class="h-11 px-4 border rounded-lg flex items-center text-sm"><i class="fas fa-file-invoice mr-2"></i>Statement</a>
            @endif
            @if(auth()->user()->hasAppPermission('employees.update'))
                <a href="{{ route('hr.employees.edit', $employeeProfile) }}" class="h-11 px-4 rounded-lg bg-indigo-600 text-white flex items-center text-sm"><i class="fas fa-pen mr-2"></i>Edit</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white shadow rounded-lg p-4"><div class="text-xs text-gray-500">Pay cycle</div><div class="mt-1 font-semibold capitalize">{{ $employeeProfile->pay_frequency }}</div></div>
        <div class="bg-white shadow rounded-lg p-4"><div class="text-xs text-gray-500">Pay rate</div><div class="mt-1 font-semibold">{{ format_currency($employeeProfile->pay_rate) }}</div></div>
        <div class="bg-white shadow rounded-lg p-4"><div class="text-xs text-gray-500">Standard / OT</div><div class="mt-1 font-semibold">{{ number_format($employeeProfile->standard_hours_per_day, 2) }}h · {{ format_currency($employeeProfile->overtime_rate) }}/h</div></div>
        <div class="bg-white shadow rounded-lg p-4"><div class="text-xs text-gray-500">Employee ledger balance</div><div class="mt-1 font-semibold {{ $ledgerBalance >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ format_currency(abs($ledgerBalance)) }} {{ $ledgerBalance >= 0 ? 'payable' : 'advance' }}</div></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white shadow rounded-lg p-5">
            <h2 class="font-semibold mb-4">Employment details</h2>
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-500">Department</dt><dd>{{ $employeeProfile->department ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Status</dt><dd class="capitalize">{{ $employeeProfile->employment_status }}</dd></div>
                <div><dt class="text-gray-500">Hire date</dt><dd>{{ $employeeProfile->hire_date ? format_date($employeeProfile->hire_date) : '—' }}</dd></div>
                <div><dt class="text-gray-500">Short hours</dt><dd>{{ $employeeProfile->short_hours_policy === 'pro_rata' ? 'Pro-rata' : 'Full day when present' }}</dd></div>
                <div><dt class="text-gray-500">Phone</dt><dd>{{ $employeeProfile->user->phone ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Branches</dt><dd>{{ $employeeProfile->user->branches->pluck('name')->join(', ') ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">POS work type</dt><dd>{{ $employeeProfile->user->accountTypeLabel() }}</dd></div>
                <div>
                    <dt class="text-gray-500">Documents</dt>
                    <dd class="space-x-2">
                        @if($employeeProfile->cnic_attachment_path)
                            <a href="{{ route('hr.employees.documents.download', [$employeeProfile, 'cnic']) }}" class="text-indigo-700">CNIC</a>
                        @endif
                        @if($employeeProfile->other_attachment_path)
                            <a href="{{ route('hr.employees.documents.download', [$employeeProfile, 'other']) }}" class="text-indigo-700">Other</a>
                        @endif
                        @if(! $employeeProfile->cnic_attachment_path && ! $employeeProfile->other_attachment_path)
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
        <div class="bg-white shadow rounded-lg p-5">
            <h2 class="font-semibold mb-4">Outstanding advances</h2>
            <div class="space-y-2 text-sm">
                @forelse($employeeProfile->user->employeeAdvances as $advance)
                    <div class="flex justify-between border-b pb-2">
                        <span>{{ format_date($advance->advance_date) }} · {{ ucfirst(str_replace('_', ' ', $advance->status)) }}</span>
                        <span>{{ format_currency($advance->outstandingAmount()) }} / {{ format_currency($advance->amount) }}</span>
                    </div>
                @empty
                    <p class="text-gray-500">No advances.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b"><h2 class="font-semibold">Employee ledger</h2></div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-3 py-3 text-left">Date</th><th class="px-3 py-3 text-left">Description</th><th class="px-3 py-3 text-left">Type</th><th class="px-3 py-3 text-right">Credit</th><th class="px-3 py-3 text-right">Debit</th></tr></thead>
                <tbody class="divide-y">
                    @forelse($ledger as $entry)
                        <tr>
                            <td class="px-3 py-3">{{ format_date($entry->entry_date) }}</td>
                            <td class="px-3 py-3">{{ $entry->description }}</td>
                            <td class="px-3 py-3 capitalize">{{ str_replace('_', ' ', $entry->type) }}</td>
                            <td class="px-3 py-3 text-right text-green-700">{{ $entry->direction === 'credit' ? format_currency($entry->amount) : '—' }}</td>
                            <td class="px-3 py-3 text-right text-red-700">{{ $entry->direction === 'debit' ? format_currency($entry->amount) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500">No ledger entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.listing-table-pagination', ['paginator' => $ledger])
    </div>
</div>
@endsection
