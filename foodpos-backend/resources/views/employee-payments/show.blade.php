@extends('layouts.app')

@section('title', $employeePayment->payment_number)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold">{{ $employeePayment->payment_number }}</h1><p class="text-sm text-gray-500">Employee payment details</p></div>
        <div class="flex gap-2">
            @if(auth()->user()->hasAppPermission('employee-payments.destroy'))
                <form method="POST"
                      action="{{ route('employee-payments.destroy', $employeePayment) }}"
                      onsubmit="return confirm('Reverse and delete this employee payment? Cash, ledger, and settled bonuses/deductions will be restored.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="h-11 px-4 border border-red-300 text-red-700 rounded-lg inline-flex items-center">
                        <i class="fas fa-trash mr-2"></i>Delete
                    </button>
                </form>
            @endif
            <a href="{{ route('employee-payments.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Back</a>
        </div>
    </div>
    @if($errors->any())<div class="p-4 rounded-lg bg-red-50 text-red-700">{{ $errors->first() }}</div>@endif
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="p-6 bg-gradient-to-r from-green-50 to-emerald-50 border-b"><div class="text-sm text-gray-500 capitalize">{{ $employeePayment->kind }}</div><div class="text-3xl font-bold text-green-700">{{ format_currency($employeePayment->amount) }}</div></div>
        <dl class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
            <div><dt class="text-gray-500">Employee</dt><dd class="font-medium"><a class="text-indigo-700" href="{{ route('hr.employees.show', $employeePayment->employee->employeeProfile) }}">{{ $employeePayment->employee->name }}</a></dd></div>
            <div><dt class="text-gray-500">Payment date</dt><dd>{{ format_date($employeePayment->payment_date) }}</dd></div>
            <div><dt class="text-gray-500">Branch</dt><dd>{{ $employeePayment->branch->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Money source</dt><dd>{{ $employeePayment->moneySource->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Method / account</dt><dd>{{ ucfirst($employeePayment->payment_method) }} · {{ $employeePayment->account->name }}</dd></div>
            <div><dt class="text-gray-500">Recorded by</dt><dd>{{ $employeePayment->creator->name ?? '—' }}</dd></div>
            @if($employeePayment->payrollItem)<div><dt class="text-gray-500">Payroll</dt><dd><a class="text-indigo-700" href="{{ route('hr.payroll.show', $employeePayment->payrollItem->payrollRun) }}">{{ $employeePayment->payrollItem->payrollRun->payroll_number }}</a></dd></div>@endif
            @if($employeePayment->advance)<div><dt class="text-gray-500">Advance status</dt><dd class="capitalize">{{ str_replace('_', ' ', $employeePayment->advance->status) }} · {{ format_currency($employeePayment->advance->outstandingAmount()) }} outstanding</dd></div>@endif
            @if($employeePayment->transaction)<div><dt class="text-gray-500">Transaction</dt><dd><a class="text-indigo-700" href="{{ route('transactions.show', $employeePayment->transaction) }}">#{{ $employeePayment->transaction->id }}</a></dd></div>@endif
            @if($employeePayment->notes)<div class="md:col-span-2"><dt class="text-gray-500">Notes</dt><dd>{{ $employeePayment->notes }}</dd></div>@endif
        </dl>
    </div>
</div>
@endsection
