@extends('layouts.app')

@section('title', $payrollRun->payroll_number)

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">{{ $payrollRun->payroll_number }}</h1>
            <p class="text-sm text-gray-500">{{ $payrollRun->branch->name ?? '—' }} · {{ format_date($payrollRun->period_start) }} – {{ format_date($payrollRun->period_end) }} · <span class="capitalize">{{ $payrollRun->pay_frequency }}</span></p>
        </div>
        <div class="flex gap-2">
            @if($payrollRun->isDraft() && auth()->user()->hasAppPermission('payroll.update'))
                <form method="POST" action="{{ route('hr.payroll.finalize', $payrollRun) }}" onsubmit="return confirm('Finalize payroll? Attendance and amounts will be locked, and employee ledger balances will be posted.')">@csrf<button class="h-11 px-4 rounded-lg bg-green-600 text-white text-sm font-medium"><i class="fas fa-check mr-2"></i>Finalize payroll</button></form>
            @endif
            @if($payrollRun->isDraft() && auth()->user()->hasAppPermission('payroll.destroy'))
                <form method="POST" action="{{ route('hr.payroll.destroy', $payrollRun) }}" onsubmit="return confirm('Delete this draft payroll?')">@csrf @method('DELETE')<button class="h-11 px-4 rounded-lg border border-red-300 text-red-700 text-sm">Delete draft</button></form>
            @endif
            <a href="{{ route('hr.payroll.index') }}" class="h-11 px-4 border rounded-lg flex items-center text-sm">Back</a>
        </div>
    </div>
    @if($errors->any())<div class="p-4 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        <div class="bg-white shadow rounded-lg p-4"><div class="text-xs text-gray-500">Status</div><div class="font-semibold capitalize">{{ str_replace('_', ' ', $payrollRun->status) }}</div></div>
        <div class="bg-white shadow rounded-lg p-4"><div class="text-xs text-gray-500">Employees</div><div class="font-semibold">{{ $payrollRun->employee_count }}</div></div>
        <div class="bg-white shadow rounded-lg p-4"><div class="text-xs text-gray-500">Gross</div><div class="font-semibold">{{ format_currency($payrollRun->gross_total) }}</div></div>
        <div class="bg-white shadow rounded-lg p-4"><div class="text-xs text-gray-500">Deductions</div><div class="font-semibold">{{ format_currency($payrollRun->deduction_total) }}</div></div>
        <div class="bg-white shadow rounded-lg p-4"><div class="text-xs text-gray-500">Advance recovery</div><div class="font-semibold">{{ format_currency($payrollRun->advance_recovery_total) }}</div></div>
        <div class="bg-white shadow rounded-lg p-4"><div class="text-xs text-gray-500">Net / paid</div><div class="font-semibold">{{ format_currency($payrollRun->net_total) }} / {{ format_currency($payrollRun->paid_total) }}</div></div>
    </div>

    <div class="space-y-4">
        @forelse($payrollRun->items as $item)
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-5 py-4 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <a href="{{ route('hr.payroll.payslip', $item) }}" class="font-semibold text-indigo-700">{{ $item->employee->name }}</a>
                        <span class="text-xs text-gray-500 ml-2">{{ $item->employee_number }}</span>
                        <div class="text-xs text-gray-500 mt-1">{{ number_format($item->worked_minutes / 60, 2) }}h worked · {{ number_format($item->overtime_minutes / 60, 2) }}h OT · {{ number_format($item->payable_days, 2) }}/{{ $item->scheduled_days }} payable days</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold">Net: {{ format_currency($item->net_pay) }}</span>
                        @if(in_array($item->status, ['finalized', 'partially_paid']) && $item->remainingAmount() > 0 && auth()->user()->hasAppPermission('employee-payments.store'))
                            <a href="{{ route('employee-payments.create', ['payroll_item_id' => $item->id]) }}" class="h-9 px-3 bg-green-600 text-white rounded-lg flex items-center text-xs">Pay {{ format_currency($item->remainingAmount()) }}</a>
                        @endif
                        <a href="{{ route('hr.payroll.payslip', $item) }}" class="h-9 px-3 border rounded-lg flex items-center text-xs">Payslip</a>
                    </div>
                </div>
                @if($payrollRun->isDraft())
                    <form method="POST" action="{{ route('hr.payroll.items.update', [$payrollRun, $item]) }}" class="p-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
                        @csrf @method('PUT')
                        <div><label class="block text-xs text-gray-500 mb-1">Base pay</label><input type="number" step="0.01" min="0" name="base_pay" value="{{ $item->base_pay }}" class="w-full h-10 px-3 rounded-lg border-gray-300 text-sm"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Overtime pay</label><input type="number" step="0.01" min="0" name="overtime_pay" value="{{ $item->overtime_pay }}" class="w-full h-10 px-3 rounded-lg border-gray-300 text-sm"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Bonus</label><input type="number" step="0.01" min="0" name="bonus_amount" value="{{ $item->bonus_amount }}" class="w-full h-10 px-3 rounded-lg border-gray-300 text-sm"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Deduction</label><input type="number" step="0.01" min="0" name="deduction_amount" value="{{ $item->deduction_amount }}" class="w-full h-10 px-3 rounded-lg border-gray-300 text-sm"></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Advance recovery</label><input type="number" step="0.01" min="0" name="advance_recovery_amount" value="{{ $item->advance_recovery_amount }}" class="w-full h-10 px-3 rounded-lg border-gray-300 text-sm"></div>
                        <div><button class="w-full h-10 rounded-lg bg-indigo-600 text-white text-sm">Recalculate</button></div>
                        <input type="hidden" name="notes" value="{{ $item->notes }}">
                    </form>
                @else
                    <div class="px-5 py-4 grid grid-cols-2 md:grid-cols-6 gap-4 text-sm">
                        <div><span class="text-gray-500 block text-xs">Base</span>{{ format_currency($item->base_pay) }}</div>
                        <div><span class="text-gray-500 block text-xs">OT pay</span>{{ format_currency($item->overtime_pay) }}</div>
                        <div><span class="text-gray-500 block text-xs">Bonus</span>{{ format_currency($item->bonus_amount) }}</div>
                        <div><span class="text-gray-500 block text-xs">Deduction</span>{{ format_currency($item->deduction_amount) }}</div>
                        <div><span class="text-gray-500 block text-xs">Advance</span>{{ format_currency($item->advance_recovery_amount) }}</div>
                        <div><span class="text-gray-500 block text-xs">Paid</span>{{ format_currency($item->paid_amount) }}</div>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white shadow rounded-lg p-12 text-center text-gray-500">No active employees match this branch and pay cycle. Add employee profiles or change the payroll period.</div>
        @endforelse
    </div>
</div>
@endsection
