@extends('layouts.app')

@section('title', 'Payslip - '.$payrollItem->employee->name)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex justify-between items-center print:hidden">
        <a href="{{ route('hr.payroll.show', $payrollItem->payrollRun) }}" class="text-indigo-700"><i class="fas fa-arrow-left mr-2"></i>Back to payroll</a>
        <button onclick="window.print()" class="h-10 px-4 border rounded-lg"><i class="fas fa-print mr-2"></i>Print</button>
    </div>
    <div class="bg-white shadow rounded-lg p-8" id="payslip">
        <div class="flex justify-between border-b pb-5">
            <div><h1 class="text-2xl font-bold">Payslip</h1><p class="text-sm text-gray-500">{{ $payrollItem->payrollRun->payroll_number }}</p></div>
            <div class="text-right"><div class="font-semibold">{{ $payrollItem->payrollRun->branch->name ?? '—' }}</div><div class="text-sm text-gray-500">{{ format_date($payrollItem->payrollRun->period_start) }} – {{ format_date($payrollItem->payrollRun->period_end) }}</div></div>
        </div>
        <div class="grid grid-cols-2 gap-4 py-5 border-b text-sm">
            <div><span class="text-gray-500 block">Employee</span><strong>{{ $payrollItem->employee->name }}</strong></div>
            <div><span class="text-gray-500 block">Employee number</span>{{ $payrollItem->employee_number ?: '—' }}</div>
            <div><span class="text-gray-500 block">Pay cycle / rate</span><span class="capitalize">{{ $payrollItem->pay_frequency }}</span> · {{ format_currency($payrollItem->pay_rate) }}</div>
            <div><span class="text-gray-500 block">Status</span><span class="capitalize">{{ str_replace('_', ' ', $payrollItem->status) }}</span></div>
            <div><span class="text-gray-500 block">Attendance</span>{{ number_format($payrollItem->payable_days, 2) }} payable / {{ $payrollItem->scheduled_days }} scheduled days</div>
            <div><span class="text-gray-500 block">Hours</span>{{ number_format($payrollItem->worked_minutes / 60, 2) }} worked · {{ number_format($payrollItem->overtime_minutes / 60, 2) }} OT</div>
        </div>
        <table class="w-full text-sm my-5">
            <tbody class="divide-y">
                <tr><td class="py-3">Base wage / salary</td><td class="py-3 text-right">{{ format_currency($payrollItem->base_pay) }}</td></tr>
                <tr><td class="py-3">Overtime pay</td><td class="py-3 text-right">{{ format_currency($payrollItem->overtime_pay) }}</td></tr>
                <tr><td class="py-3">Bonus</td><td class="py-3 text-right">{{ format_currency($payrollItem->bonus_amount) }}</td></tr>
                <tr class="font-semibold"><td class="py-3">Gross pay</td><td class="py-3 text-right">{{ format_currency($payrollItem->gross_pay) }}</td></tr>
                <tr><td class="py-3">Deductions</td><td class="py-3 text-right text-red-700">-{{ format_currency($payrollItem->deduction_amount) }}</td></tr>
                <tr><td class="py-3">Advance recovered</td><td class="py-3 text-right text-red-700">-{{ format_currency($payrollItem->advance_recovery_amount) }}</td></tr>
                <tr class="text-lg font-bold"><td class="py-4">Net payable</td><td class="py-4 text-right">{{ format_currency($payrollItem->net_pay) }}</td></tr>
                <tr><td class="py-3">Paid</td><td class="py-3 text-right">{{ format_currency($payrollItem->paid_amount) }}</td></tr>
                <tr class="font-semibold"><td class="py-3">Remaining</td><td class="py-3 text-right">{{ format_currency($payrollItem->remainingAmount()) }}</td></tr>
            </tbody>
        </table>
        @if($payrollItem->notes)<div class="border-t pt-4 text-sm"><span class="text-gray-500">Notes:</span> {{ $payrollItem->notes }}</div>@endif
    </div>
</div>
@endsection
