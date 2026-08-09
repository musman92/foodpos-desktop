@extends('layouts.app')

@section('title', 'Employee Payments')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold">Employee payments</h1><p class="text-sm text-gray-500">Payroll payouts, direct wages, advances and paid bonuses.</p></div>
        @if(auth()->user()->hasAppPermission('employee-payments.store'))<a href="{{ route('employee-payments.create') }}" class="h-11 px-4 bg-indigo-600 text-white rounded-lg flex items-center text-sm"><i class="fas fa-plus mr-2"></i>Employee payment</a>@endif
    </div>
    <form method="GET" class="bg-white shadow rounded-lg p-4 grid grid-cols-1 md:grid-cols-5 gap-3">
        <select name="employee_id" class="filter-control"><option value="">All employees</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((int)request('employee_id') === (int)$employee->id)>{{ $employee->name }}</option>@endforeach</select>
        <select name="kind" class="filter-control"><option value="">All payment types</option>@foreach(\App\Models\EmployeePayment::KINDS as $kind)<option value="{{ $kind }}" @selected(request('kind') === $kind)>{{ ucfirst($kind) }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ request('from') }}" class="filter-control">
        <input type="date" name="to" value="{{ request('to') }}" class="filter-control">
        <div class="flex gap-2"><button class="h-11 px-4 bg-indigo-600 text-white rounded-lg">Filter</button><a href="{{ route('employee-payments.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Reset</a></div>
    </form>
    <div class="bg-white shadow rounded-lg overflow-hidden">
        @include('partials.listing-per-page-bar', ['action' => route('employee-payments.index'), 'paginator' => $payments, 'perPage' => $perPage])
        <div class="overflow-x-auto"><table class="listing-table min-w-full text-sm divide-y"><thead class="bg-gray-50"><tr><th class="px-3 py-3 text-left">Payment #</th><th class="px-3 py-3 text-left">Date</th><th class="px-3 py-3 text-left">Employee</th><th class="px-3 py-3 text-left">Type</th><th class="px-3 py-3 text-left">Money source</th><th class="px-3 py-3 text-right">Amount</th><th class="px-3 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y">
                @forelse($payments as $payment)
                    <tr>
                        <td class="px-3 py-3"><a class="text-indigo-700 font-medium" href="{{ route('employee-payments.show', $payment) }}">{{ $payment->payment_number }}</a></td>
                        <td class="px-3 py-3">{{ format_date($payment->payment_date) }}</td>
                        <td class="px-3 py-3">{{ $payment->employee->name }}</td>
                        <td class="px-3 py-3 capitalize">{{ $payment->kind }}</td>
                        <td class="px-3 py-3">{{ $payment->moneySource->name ?? '—' }}</td>
                        <td class="px-3 py-3 text-right">{{ format_currency($payment->amount) }}</td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('employee-payments.show', $payment) }}" class="text-indigo-600 mx-1" title="View"><i class="fas fa-eye"></i></a>
                            @if(auth()->user()->hasAppPermission('employee-payments.destroy'))
                                <form action="{{ route('employee-payments.destroy', $payment) }}"
                                      method="POST"
                                      class="inline"
                                      onsubmit="return confirm('Reverse and delete this employee payment? Cash, ledger, and settled bonuses/deductions will be restored.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 mx-1" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">No employee payments found.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @include('partials.listing-table-pagination', ['paginator' => $payments])
    </div>
</div>
@endsection
